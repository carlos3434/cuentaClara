<?php

namespace App\Services\Ai;

use App\Enums\ReasonCode;
use App\Enums\ReceiptStatus;
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
     * @return array{verdict: ReceiptStatus, reason_code: ?ReasonCode}
     */
    public function decide(Event $event, ReceiptExtraction $x): array
    {
        if (! $x->isReceipt) {
            return $this->review(ReasonCode::NotAReceipt);
        }

        if ($x->amountCents === null) {
            return $this->review(ReasonCode::AmountUnreadable);
        }

        if (! $this->amountMatches($event, $x->amountCents)) {
            return $this->review(ReasonCode::AmountMismatch);
        }

        if (! $this->methodAccepted($event, $x->method)) {
            return $this->review(ReasonCode::MethodNotAccepted);
        }

        if ($x->confidence < (float) config('cuentaclara.ai.confidence_threshold')) {
            return $this->review(ReasonCode::LowConfidence);
        }

        return ['verdict' => ReceiptStatus::Validated, 'reason_code' => null];
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
     * The detected payment method must be one the organizer accepts
     * (i.e. a real Yape/Plin/transfer receipt, not just any image).
     * A null/unreadable method isn't penalized here — is_payment_receipt
     * already vouches it's a receipt; we don't block good-faith uploads.
     */
    private function methodAccepted(Event $event, ?string $method): bool
    {
        $accepted = $event->accepted_methods ?? [];

        if ($method === null || $accepted === []) {
            return true;
        }

        return in_array($method, $accepted, true);
    }

    /**
     * @return array{verdict: ReceiptStatus, reason_code: ReasonCode}
     */
    private function review(ReasonCode $reason): array
    {
        return ['verdict' => ReceiptStatus::NeedsReview, 'reason_code' => $reason];
    }
}
