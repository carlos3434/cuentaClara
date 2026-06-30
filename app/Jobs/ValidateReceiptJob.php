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

        // Always store what was read — it assists the organizer's review.
        $update = [
            'extracted_amount_cents' => $extraction->amountCents,
            'extracted_currency' => $extraction->currency,
            'extracted_date' => $extraction->date,
            'extracted_method' => $extraction->method,
            'extracted_recipient' => $extraction->recipient,
            'extracted_operation' => $extraction->operation,
            'confidence' => $extraction->confidence,
            'ai_explanation' => $extraction->explanation,
            'ai_raw' => $extraction->raw,
        ];

        // Only act on the verdict in 'auto' mode. In 'manual' mode the AI never
        // decides money — the receipt stays in the queue for human confirmation.
        $reviewMode = Setting::get('review_mode', config('cuentaclara.review_mode'));
        if ($reviewMode === 'auto') {
            $update['status'] = $decision['verdict'];
            $update['reason_code'] = $decision['reason_code'];
            $update['decided_by'] = DecidedBy::Ai;
            $update['decided_at'] = now();
        }

        $receipt->update($update);
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
