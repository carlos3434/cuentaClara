<?php

namespace Tests\Feature;

use App\Jobs\ValidateReceiptJob;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use App\Services\Ai\Contracts\ReceiptVision;
use App\Services\Ai\ReceiptExtraction;
use App\Services\Ai\ReceiptRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ValidateReceiptTest extends TestCase
{
    use RefreshDatabase;

    // --- Rule engine (the deterministic core) ---------------------------

    #[DataProvider('verdictCases')]
    public function test_rule_engine_decides_verdict(
        int $shareCents,
        int $totalCents,
        int $headcount,
        ?int $amountCents,
        bool $isReceipt,
        float $confidence,
        string $expectedVerdict,
        ?string $expectedReason,
    ): void {
        $event = Event::factory()->make([
            'share_cents' => $shareCents,
            'total_cents' => $totalCents,
            'headcount' => $headcount,
        ]);

        $extraction = new ReceiptExtraction(
            isReceipt: $isReceipt,
            amountCents: $amountCents,
            currency: 'PEN',
            date: '2026-06-24',
            method: 'yape',
            recipient: 'Caro',
            confidence: $confidence,
            explanation: '',
        );

        $decision = (new ReceiptRuleEngine())->decide($event, $extraction);

        $this->assertSame($expectedVerdict, $decision['verdict']);
        $this->assertSame($expectedReason, $decision['reason_code']);
    }

    public static function verdictCases(): array
    {
        // share 4000, total 48000, headcount 12 → remainder 0
        return [
            'exact match, high conf → validated' => [4000, 48000, 12, 4000, true, 0.95, 'validated', null],
            'underpaid → needs_review' => [4000, 48000, 12, 3000, true, 0.95, 'needs_review', 'amount_mismatch'],
            'overpaid → needs_review' => [4000, 48000, 12, 5000, true, 0.95, 'needs_review', 'amount_mismatch'],
            'low confidence → needs_review' => [4000, 48000, 12, 4000, true, 0.50, 'needs_review', 'low_confidence'],
            'not a receipt → needs_review' => [4000, 48000, 12, 4000, false, 0.99, 'needs_review', 'not_a_receipt'],
            'unreadable amount → needs_review' => [4000, 48000, 12, null, true, 0.99, 'needs_review', 'amount_unreadable'],
        ];
    }

    public function test_rule_engine_allows_rounding_remainder(): void
    {
        // 100 / 3 → share 33 (cents-level: total 100, share 33, remainder 1)
        $event = Event::factory()->make([
            'share_cents' => 33,
            'total_cents' => 100,
            'headcount' => 3,
        ]);

        $engine = new ReceiptRuleEngine();

        $atShare = $this->extractionWithAmount(33);
        $atSharePlusRemainder = $this->extractionWithAmount(34);
        $overRemainder = $this->extractionWithAmount(35);

        $this->assertSame('validated', $engine->decide($event, $atShare)['verdict']);
        $this->assertSame('validated', $engine->decide($event, $atSharePlusRemainder)['verdict']);
        $this->assertSame('needs_review', $engine->decide($event, $overRemainder)['verdict']);
    }

    // --- Job ------------------------------------------------------------

    public function test_job_validates_a_matching_receipt(): void
    {
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);

        $this->bindVision($this->extractionWithAmount(4000, confidence: 0.95));

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status);
        $this->assertSame('ai', $receipt->decided_by);
        $this->assertSame(4000, $receipt->extracted_amount_cents);
        $this->assertNotNull($receipt->decided_at);
        $this->assertNull($receipt->reason_code);
    }

    public function test_job_routes_a_mismatch_to_review(): void
    {
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);

        $this->bindVision($this->extractionWithAmount(3000, confidence: 0.95));

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('needs_review', $receipt->status);
        $this->assertSame('amount_mismatch', $receipt->reason_code);
    }

    public function test_job_does_not_override_an_organizer_decision(): void
    {
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);
        $receipt->update(['status' => 'validated', 'decided_by' => 'organizer']);

        $this->bindVision($this->extractionWithAmount(3000, confidence: 0.1));

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status);
        $this->assertSame('organizer', $receipt->decided_by);
    }

    public function test_ai_failure_routes_to_review_never_rejects(): void
    {
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);

        (new ValidateReceiptJob($receipt))->failed(new RuntimeException('AI down'));

        $receipt->refresh();
        $this->assertSame('needs_review', $receipt->status);
        $this->assertSame('ai_unavailable', $receipt->reason_code);
    }

    // --- Dispatch on upload ---------------------------------------------

    public function test_uploading_a_receipt_dispatches_validation(): void
    {
        Queue::fake();
        Storage::fake(config('cuentaclara.receipts_disk'));
        $event = Event::factory()->create();

        $this->post("/e/{$event->slug}/receipts", [
            'name' => 'José',
            'image' => UploadedFile::fake()->image('voucher.jpg'),
        ]);

        Queue::assertPushed(ValidateReceiptJob::class);
    }

    // --- Helpers --------------------------------------------------------

    private function extractionWithAmount(int $amountCents, float $confidence = 0.95): ReceiptExtraction
    {
        return new ReceiptExtraction(
            isReceipt: true,
            amountCents: $amountCents,
            currency: 'PEN',
            date: '2026-06-24',
            method: 'yape',
            recipient: 'Caro',
            confidence: $confidence,
            explanation: 'stub',
        );
    }

    private function bindVision(ReceiptExtraction $extraction): void
    {
        $this->app->instance(ReceiptVision::class, new class($extraction) implements ReceiptVision {
            public function __construct(private ReceiptExtraction $extraction) {}

            public function extract(Receipt $receipt): ReceiptExtraction
            {
                return $this->extraction;
            }
        });
    }

    private function makeSubmittedReceipt(int $shareCents): Receipt
    {
        $event = Event::factory()->create([
            'share_cents' => $shareCents,
            'total_cents' => $shareCents * 10,
            'headcount' => 10,
        ]);
        $participant = Participant::factory()->for($event)->create();

        return Receipt::factory()->for($event)->for($participant)->create(['status' => 'submitted']);
    }
}
