<?php

namespace App\Jobs;

use App\Models\Receipt;
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
        if (! $receipt || $receipt->decided_by === 'organizer') {
            return;
        }

        $startedAt = microtime(true);
        $extraction = $vision->extract($receipt);
        $decision = $engine->decide($receipt->event, $extraction);

        Log::info('receipt.validated', [
            'receipt_id' => $receipt->id,
            'event_id' => $receipt->event_id,
            'driver' => config('cuentaclara.ai.driver'),
            'verdict' => $decision['verdict'],
            'reason_code' => $decision['reason_code'],
            'confidence' => $extraction->confidence,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $receipt->update([
            'status' => $decision['verdict'],
            'reason_code' => $decision['reason_code'],
            'extracted_amount_cents' => $extraction->amountCents,
            'extracted_currency' => $extraction->currency,
            'extracted_date' => $extraction->date,
            'extracted_method' => $extraction->method,
            'extracted_recipient' => $extraction->recipient,
            'confidence' => $extraction->confidence,
            'ai_explanation' => $extraction->explanation,
            'ai_raw' => $extraction->raw,
            'decided_by' => 'ai',
            'decided_at' => now(),
        ]);
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
            'status' => 'needs_review',
            'reason_code' => 'ai_unavailable',
        ]);
    }
}
