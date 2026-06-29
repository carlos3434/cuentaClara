<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\Ai\ReceiptExtraction;
use App\Services\Ai\ReceiptRuleEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The deterministic, money-affecting core. Booted (for config()) but isolated:
 * no DB — events and extractions are built in memory.
 */
class ReceiptRuleEngineTest extends TestCase
{
    #[DataProvider('verdictCases')]
    public function test_decide(
        int $share,
        int $total,
        int $headcount,
        ?int $amount,
        bool $isReceipt,
        float $confidence,
        string $verdict,
        ?string $reason,
    ): void {
        $event = new Event(['share_cents' => $share, 'total_cents' => $total, 'headcount' => $headcount]);

        $decision = (new ReceiptRuleEngine())->decide($event, $this->extraction($amount, $isReceipt, $confidence));

        $this->assertSame($verdict, $decision['verdict']);
        $this->assertSame($reason, $decision['reason_code']);
    }

    public static function verdictCases(): array
    {
        // share 4000, total 48000, headcount 12 → remainder 0
        return [
            'exact + high conf → validated' => [4000, 48000, 12, 4000, true, 0.95, 'validated', null],
            'underpaid → review' => [4000, 48000, 12, 3000, true, 0.95, 'needs_review', 'amount_mismatch'],
            'overpaid → review' => [4000, 48000, 12, 5000, true, 0.95, 'needs_review', 'amount_mismatch'],
            'just below threshold → review' => [4000, 48000, 12, 4000, true, 0.849, 'needs_review', 'low_confidence'],
            'at threshold → validated' => [4000, 48000, 12, 4000, true, 0.85, 'validated', null],
            'not a receipt → review' => [4000, 48000, 12, 4000, false, 0.99, 'needs_review', 'not_a_receipt'],
            'unreadable amount → review' => [4000, 48000, 12, null, true, 0.99, 'needs_review', 'amount_unreadable'],
        ];
    }

    public function test_amount_match_allows_the_split_rounding_remainder(): void
    {
        // 100 / 3 → share 33, remainder 1 → 33 and 34 both acceptable, 35 not.
        $event = new Event(['share_cents' => 33, 'total_cents' => 100, 'headcount' => 3]);
        $engine = new ReceiptRuleEngine();

        $this->assertSame('validated', $engine->decide($event, $this->extraction(33, true, 0.95))['verdict']);
        $this->assertSame('validated', $engine->decide($event, $this->extraction(34, true, 0.95))['verdict']);
        $this->assertSame('needs_review', $engine->decide($event, $this->extraction(35, true, 0.95))['verdict']);
    }

    public function test_amount_is_checked_before_confidence(): void
    {
        // A mismatching amount reports amount_mismatch even when confidence is low.
        $event = new Event(['share_cents' => 4000, 'total_cents' => 48000, 'headcount' => 12]);

        $decision = (new ReceiptRuleEngine())->decide($event, $this->extraction(3000, true, 0.10));

        $this->assertSame('amount_mismatch', $decision['reason_code']);
    }

    public function test_flags_a_method_the_organizer_does_not_accept(): void
    {
        $event = new Event(['share_cents' => 4000, 'total_cents' => 48000, 'headcount' => 12, 'accepted_methods' => ['yape']]);

        $decision = (new ReceiptRuleEngine())->decide($event, $this->extraction(4000, true, 0.95, 'plin'));

        $this->assertSame('needs_review', $decision['verdict']);
        $this->assertSame('method_not_accepted', $decision['reason_code']);
    }

    public function test_allows_an_accepted_method(): void
    {
        $event = new Event(['share_cents' => 4000, 'total_cents' => 48000, 'headcount' => 12, 'accepted_methods' => ['yape', 'plin']]);

        $this->assertSame('validated', (new ReceiptRuleEngine())->decide($event, $this->extraction(4000, true, 0.95, 'plin'))['verdict']);
    }

    public function test_does_not_block_when_method_is_unreadable(): void
    {
        $event = new Event(['share_cents' => 4000, 'total_cents' => 48000, 'headcount' => 12, 'accepted_methods' => ['yape']]);

        // is_payment_receipt already vouches it's a receipt; null method shouldn't block.
        $this->assertSame('validated', (new ReceiptRuleEngine())->decide($event, $this->extraction(4000, true, 0.95, null))['verdict']);
    }

    private function extraction(?int $amountCents, bool $isReceipt, float $confidence, ?string $method = 'yape'): ReceiptExtraction
    {
        return new ReceiptExtraction($isReceipt, $amountCents, 'PEN', '2026-06-24', $method, 'Caro', $confidence, '');
    }
}
