<?php

namespace App\Jobs;

use App\Enums\DecidedBy;
use App\Enums\ReasonCode;
use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\Setting;
use App\Services\Ai\Contracts\ReceiptVision;
use App\Services\Ai\ReceiptRuleEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ValidateReceiptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public Receipt $receipt) {}

    public function handle(ReceiptVision $vision, ReceiptRuleEngine $engine): void
    {
        $receipt = $this->receipt->fresh();

        // Don't re-decide a receipt a human already ruled on.
        if (! $receipt || $receipt->decided_by === DecidedBy::Organizer) {
            return;
        }

        $startedAt = microtime(true);
        $extraction = $vision->extract($receipt);
        $decision = $engine->decide($receipt->event, $extraction);

        Log::info('receipt.validated', [
            'receipt_id' => $receipt->id,
            'event_id' => $receipt->event_id,
            'driver' => config('cuentaclara.ai.driver'),
            'verdict' => $decision['verdict']->value,
            'reason_code' => $decision['reason_code']?->value,
            'confidence' => $extraction->confidence,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        // Always store what was read — it assists the organizer's review. The
        // operation number is stored only as a hash (duplicate detection
        // without keeping the clear value).
        $operationHash = Receipt::hashOperation($extraction->operation);

        $update = [
            'extracted_amount_cents' => $extraction->amountCents,
            'extracted_currency' => $extraction->currency,
            'extracted_date' => $extraction->date,
            'extracted_method' => $extraction->method,
            'extracted_recipient' => $extraction->recipient,
            'operation_hash' => $operationHash,
            'confidence' => $extraction->confidence,
            'ai_explanation' => $extraction->explanation,
            'ai_raw' => $extraction->raw,
        ];

        // A voucher whose operation number was already uploaded for this event
        // is a likely duplicate — surface it for review and never auto-approve,
        // regardless of mode (it's a fraud/mistake signal, not a money verdict).
        $duplicateOf = $this->duplicateOf($receipt, $operationHash);

        $reviewMode = Setting::get('review_mode', config('cuentaclara.review_mode'));

        if ($duplicateOf) {
            $update['status'] = ReceiptStatus::NeedsReview;
            $update['reason_code'] = ReasonCode::DuplicateOperation;
            $update['ai_explanation'] = 'Mismo N° de operación que el pago de '
                .($duplicateOf->participant?->name ?? 'otro participante').'.';
        } elseif ($reviewMode === 'auto') {
            // Only act on the verdict in 'auto' mode. In 'manual' mode the AI
            // never decides money — it stays in the queue for human confirmation.
            $update['status'] = $decision['verdict'];
            $update['reason_code'] = $decision['reason_code'];
            $update['decided_by'] = DecidedBy::Ai;
            $update['decided_at'] = now();
        }

        $receipt->update($update);
    }

    /**
     * Another non-rejected receipt in the same event sharing this operation
     * hash, if any. A null hash (no readable operation number) never matches.
     */
    private function duplicateOf(Receipt $receipt, ?string $operationHash): ?Receipt
    {
        if ($operationHash === null) {
            return null;
        }

        return Receipt::query()
            ->where('event_id', $receipt->event_id)
            ->where('id', '!=', $receipt->id)
            ->where('operation_hash', $operationHash)
            ->where('status', '!=', ReceiptStatus::Rejected->value)
            ->with('participant')
            ->first();
    }

    /**
     * AI failure must never auto-reject a good-faith upload — route to a human.
     */
    public function failed(?Throwable $e): void
    {
        Log::warning('receipt.validation_failed', [
            'receipt_id' => $this->receipt->id,
            'driver' => config('cuentaclara.ai.driver'),
            'error' => $e?->getMessage(),
        ]);

        $this->receipt->fresh()?->update([
            'status' => ReceiptStatus::NeedsReview,
            'reason_code' => ReasonCode::AiUnavailable,
        ]);
    }
}
