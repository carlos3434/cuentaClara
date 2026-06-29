<?php

namespace App\Services\Ai;

use App\Models\Event;

/**
 * Turns an extraction into a verdict. Pure and deterministic — this is the
 * money-affecting logic and is fully unit-tested.
 *
 * Lean MVP (docs/13): the only verdicts are `validated` and `needs_review`.
 * The single gating rule is amount + confidence; date/recipient/method are
 * extracted for the organizer but never auto-reject a good-faith upload.
 */
class ReceiptRuleEngine
{
    /**
     * @return array{verdict: string, reason_code: ?string}
     */
    public function decide(Event $event, ReceiptExtraction $x): array
    {
        if (! $x->isReceipt) {
            return $this->review('not_a_receipt');
        }

        if ($x->amountCents === null) {
            return $this->review('amount_unreadable');
        }

        if (! $this->amountMatches($event, $x->amountCents)) {
            return $this->review('amount_mismatch');
        }

        if ($x->confidence < (float) config('cuentaclara.ai.confidence_threshold')) {
            return $this->review('low_confidence');
        }

        return ['verdict' => 'validated', 'reason_code' => null];
    }

    /**
     * A payment matches if it covers the share, allowing for the rounding
     * remainder of the equal split (one participant may owe a few extra cents).
     * Underpayment (partial) and overpayment are deferred to v2 → needs_review.
     */
    private function amountMatches(Event $event, int $amountCents): bool
    {
        $share = $event->share_cents;
        $remainder = max(0, $event->total_cents - $share * $event->headcount);

        return $amountCents >= $share && $amountCents <= $share + $remainder;
    }

    /**
     * @return array{verdict: string, reason_code: string}
     */
    private function review(string $reason): array
    {
        return ['verdict' => 'needs_review', 'reason_code' => $reason];
    }
}
