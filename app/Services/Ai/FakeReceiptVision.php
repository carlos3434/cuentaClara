<?php

namespace App\Services\Ai;

use App\Models\Receipt;
use App\Services\Ai\Contracts\ReceiptVision;

/**
 * Deterministic stand-in used in local dev and tests. Reads the expected
 * share off the event so uploaded receipts auto-validate — letting the whole
 * flow be demoed without a real vision provider or API key.
 */
class FakeReceiptVision implements ReceiptVision
{
    public function extract(Receipt $receipt): ReceiptExtraction
    {
        $event = $receipt->event;

        return new ReceiptExtraction(
            isReceipt: true,
            amountCents: $event->share_cents,
            currency: 'PEN',
            date: now()->toDateString(),
            method: $event->accepted_methods[0] ?? 'yape',
            recipient: $event->recipient_name,
            confidence: 0.95,
            explanation: '[fake] Voucher reconocido automáticamente.',
            raw: ['driver' => 'fake'],
        );
    }
}
