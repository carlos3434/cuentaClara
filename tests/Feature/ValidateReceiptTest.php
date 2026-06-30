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
use RuntimeException;
use Tests\TestCase;

/**
 * Feature-level coverage of the validation JOB and its dispatch. The rule
 * engine itself is unit-tested in tests/Unit/ReceiptRuleEngineTest.
 */
class ValidateReceiptTest extends TestCase
{
    use RefreshDatabase;

    // --- Job ------------------------------------------------------------

    public function test_job_validates_a_matching_receipt_in_auto_mode(): void
    {
        config(['cuentaclara.review_mode' => 'auto']);
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);

        $this->bindVision($this->extractionWithAmount(4000, confidence: 0.95));

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status->value);
        $this->assertSame('ai', $receipt->decided_by->value);
        $this->assertSame(4000, $receipt->extracted_amount_cents);
        $this->assertNotNull($receipt->decided_at);
        $this->assertNull($receipt->reason_code);
    }

    public function test_job_routes_a_mismatch_to_review_in_auto_mode(): void
    {
        config(['cuentaclara.review_mode' => 'auto']);
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);

        $this->bindVision($this->extractionWithAmount(3000, confidence: 0.95));

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('needs_review', $receipt->status->value);
        $this->assertSame('amount_mismatch', $receipt->reason_code->value);
    }

    public function test_job_in_manual_mode_extracts_but_never_auto_approves(): void
    {
        config(['cuentaclara.review_mode' => 'manual']);
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);

        $this->bindVision($this->extractionWithAmount(4000, confidence: 0.95));

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('submitted', $receipt->status->value); // stays in the queue
        $this->assertNull($receipt->decided_by);                 // human still decides
        $this->assertSame(4000, $receipt->extracted_amount_cents); // but the reading is stored
    }

    public function test_job_does_not_override_an_organizer_decision(): void
    {
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);
        $receipt->update(['status' => 'validated', 'decided_by' => 'organizer']);

        $this->bindVision($this->extractionWithAmount(3000, confidence: 0.1));

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status->value);
        $this->assertSame('organizer', $receipt->decided_by->value);
    }

    public function test_job_flags_a_receipt_whose_operation_was_already_uploaded(): void
    {
        config(['cuentaclara.review_mode' => 'auto']); // even auto must not approve a duplicate
        $event = Event::factory()->create(['share_cents' => 4000, 'total_cents' => 40000, 'headcount' => 10]);

        $firstParticipant = Participant::factory()->for($event)->create(['name' => 'Ana']);
        Receipt::factory()->for($event)->for($firstParticipant)
            ->create(['status' => 'validated', 'operation_hash' => Receipt::hashOperation('2287273')]);

        $second = Receipt::factory()->for($event)
            ->for(Participant::factory()->for($event)->create(['name' => 'Beto']))
            ->create(['status' => 'submitted']);

        $this->bindVision($this->extractionWithAmount(4000, operation: '2287273'));
        (new ValidateReceiptJob($second))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $second->refresh();
        $this->assertSame('needs_review', $second->status->value);
        $this->assertSame('duplicate_operation', $second->reason_code->value);
        $this->assertNull($second->decided_by);          // not auto-approved
        $this->assertStringContainsString('Ana', $second->ai_explanation);
    }

    public function test_duplicate_detection_ignores_punctuation_in_the_operation_number(): void
    {
        config(['cuentaclara.review_mode' => 'auto']);
        $event = Event::factory()->create(['share_cents' => 4000, 'total_cents' => 40000, 'headcount' => 10]);

        Receipt::factory()->for($event)->for(Participant::factory()->for($event)->create(['name' => 'Ana']))
            ->create(['status' => 'validated', 'operation_hash' => Receipt::hashOperation('784.444.018.0481')]);

        $second = Receipt::factory()->for($event)
            ->for(Participant::factory()->for($event)->create())
            ->create(['status' => 'submitted']);

        // Same digits, different formatting (spaces instead of dots).
        $this->bindVision($this->extractionWithAmount(4000, operation: '784 444 018 0481'));
        (new ValidateReceiptJob($second))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $second->refresh();
        $this->assertSame('needs_review', $second->status->value);
        $this->assertSame('duplicate_operation', $second->reason_code->value);
    }

    public function test_the_same_operation_in_a_different_event_is_not_a_duplicate(): void
    {
        config(['cuentaclara.review_mode' => 'auto']);
        $other = Event::factory()->create();
        Receipt::factory()->for($other)->for(Participant::factory()->for($other))
            ->create(['status' => 'validated', 'operation_hash' => Receipt::hashOperation('2287273')]);

        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);
        $this->bindVision($this->extractionWithAmount(4000, operation: '2287273'));
        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status->value); // not flagged
    }

    public function test_a_missing_operation_number_is_never_a_duplicate(): void
    {
        config(['cuentaclara.review_mode' => 'auto']);
        $event = Event::factory()->create(['share_cents' => 4000, 'total_cents' => 40000, 'headcount' => 10]);
        Receipt::factory()->for($event)->for(Participant::factory()->for($event))
            ->create(['status' => 'validated', 'operation_hash' => null]);

        $receipt = Receipt::factory()->for($event)->for(Participant::factory()->for($event))
            ->create(['status' => 'submitted']);

        $this->bindVision($this->extractionWithAmount(4000, operation: null));
        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status->value); // not flagged
    }

    public function test_ai_failure_routes_to_review_never_rejects(): void
    {
        $receipt = $this->makeSubmittedReceipt(shareCents: 4000);

        (new ValidateReceiptJob($receipt))->failed(new RuntimeException('AI down'));

        $receipt->refresh();
        $this->assertSame('needs_review', $receipt->status->value);
        $this->assertSame('ai_unavailable', $receipt->reason_code->value);
    }

    // --- Dispatch on upload ---------------------------------------------

    public function test_uploading_a_receipt_dispatches_extraction(): void
    {
        // Extraction runs in both modes (it only assists); the mode just
        // decides whether the reading can auto-approve, inside the job.
        config(['cuentaclara.review_mode' => 'manual']);
        Queue::fake();
        Storage::fake(config('cuentaclara.receipts_disk'));
        $event = Event::factory()->create();

        $this->post("/e/{$event->slug}/receipts", [
            'name' => 'José',
            'image' => UploadedFile::fake()->image('voucher.jpg'),
        ]);

        Queue::assertPushed(ValidateReceiptJob::class);
        $this->assertSame('submitted', Receipt::firstOrFail()->status->value);
    }

    // --- Helpers --------------------------------------------------------

    private function extractionWithAmount(int $amountCents, float $confidence = 0.95, ?string $operation = null): ReceiptExtraction
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
            operation: $operation,
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
