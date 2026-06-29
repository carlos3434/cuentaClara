<?php

namespace Tests\Feature\Flows;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Ai\Contracts\ReceiptVision;
use App\Services\Ai\ReceiptExtraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * End-to-end flow (integration) tests. Unlike the per-endpoint feature tests,
 * these walk a full journey across several requests and exercise the REAL
 * pipeline wired together: the queue runs sync (phpunit.xml), so uploading a
 * receipt actually dispatches ValidateReceiptJob → ReceiptVision → the
 * deterministic ReceiptRuleEngine. The queue is intentionally NOT faked here.
 */
class CollectionJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('cuentaclara.receipts_disk'));
    }

    // --- Happy path: creation → upload → AI validates → confirmed ---------

    public function test_happy_path_from_creation_to_confirmed_payment(): void
    {
        [$organizer, $event] = $this->organizerWithEvent();
        $this->assertSame(4000, $event->share_cents); // 48000 / 12

        // Participant uploads; the fake vision reads the matching share, so the
        // job (run inline) validates it through the real rule engine.
        $this->upload($event, 'José')->assertRedirect("/e/{$event->slug}");

        $receipt = Receipt::firstOrFail();
        $this->assertSame('validated', $receipt->status->value);
        $this->assertSame('ai', $receipt->decided_by->value);

        // Participant revisiting the link sees their payment confirmed.
        $participant = Participant::firstOrFail();
        $this->withCookie("cc_p_{$event->id}", $participant->session_token)
            ->get("/e/{$event->slug}")
            ->assertInertia(fn (Assert $p) => $p->where('participant.badge', 'confirmed'));

        // Organizer dashboard reflects the collected amount.
        $this->actingAs($organizer)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $p) => $p
                ->where('summary.paid_count', 1)
                ->where('summary.collected_cents', 4000)
                ->where('summary.pending_cents', 44000)
                ->where('participants.0.status', 'paid'));
    }

    // --- Exception path: AI flags → organizer reviews → approves ----------

    public function test_flagged_upload_is_resolved_by_organizer_approval(): void
    {
        [$organizer, $event] = $this->organizerWithEvent();
        $this->visionReturns(new ReceiptExtraction(false, null, null, null, null, null, 0.2, 'no es un voucher'));

        $this->upload($event, 'Lucía')->assertRedirect();

        $receipt = Receipt::firstOrFail();
        $this->assertSame('needs_review', $receipt->status->value);
        $this->assertSame('not_a_receipt', $receipt->reason_code->value);

        // Shows in the review queue, nothing collected yet.
        $this->actingAs($organizer)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $p) => $p
                ->where('summary.review_count', 1)
                ->where('summary.collected_cents', 0));

        // Organizer overrides → approve. The decision sticks (decided_by organizer).
        $this->actingAs($organizer)
            ->post("/events/{$event->slug}/receipts/{$receipt->id}/approve")
            ->assertRedirect();

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status->value);
        $this->assertSame('organizer', $receipt->decided_by->value);

        $this->actingAs($organizer)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $p) => $p
                ->where('summary.paid_count', 1)
                ->where('summary.collected_cents', 4000));
    }

    public function test_organizer_rejection_keeps_the_payment_uncollected(): void
    {
        [$organizer, $event] = $this->organizerWithEvent();
        $this->visionReturns(new ReceiptExtraction(true, 1000, 'PEN', '2026-06-24', 'yape', 'Otro', 0.9, 'monto menor'));

        $this->upload($event, 'Marco'); // 1000 ≠ 4000 → needs_review (amount_mismatch)
        $receipt = Receipt::firstOrFail();
        $this->assertSame('needs_review', $receipt->status->value);

        $this->actingAs($organizer)
            ->post("/events/{$event->slug}/receipts/{$receipt->id}/reject")
            ->assertRedirect();

        $receipt->refresh();
        $this->assertSame('rejected', $receipt->status->value);

        // Participant sees "Revisar"; nothing collected.
        $participant = Participant::firstOrFail();
        $this->withCookie("cc_p_{$event->id}", $participant->session_token)
            ->get("/e/{$event->slug}")
            ->assertInertia(fn (Assert $p) => $p->where('participant.badge', 'review'));

        $this->actingAs($organizer)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $p) => $p->where('summary.collected_cents', 0));
    }

    // --- Cash, close, and multi-payer totals ------------------------------

    public function test_marking_cash_collects_without_an_upload(): void
    {
        [$organizer, $event] = $this->organizerWithEvent();
        $participant = Participant::factory()->for($event)->create();

        $this->actingAs($organizer)
            ->post("/events/{$event->slug}/participants/{$participant->id}/cash")
            ->assertRedirect();

        $this->actingAs($organizer)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $p) => $p
                ->where('summary.paid_count', 1)
                ->where('summary.collected_cents', 4000));
    }

    public function test_closing_the_event_blocks_further_uploads(): void
    {
        [$organizer, $event] = $this->organizerWithEvent();

        $this->actingAs($organizer)->post("/events/{$event->slug}/close")->assertRedirect();

        $this->upload($event, 'Tarde')->assertNotFound();
        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_collected_and_pending_track_multiple_payers(): void
    {
        [$organizer, $event] = $this->organizerWithEvent(); // share 4000, total 48000

        $this->upload($event, 'Ana');
        $this->upload($event, 'Beto');
        $this->upload($event, 'Caro');

        $this->actingAs($organizer)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $p) => $p
                ->where('summary.paid_count', 3)
                ->where('summary.collected_cents', 12000)
                ->where('summary.pending_cents', 36000));
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * @return array{0: User, 1: Event}
     */
    private function organizerWithEvent(array $overrides = []): array
    {
        $organizer = User::factory()->create();

        $this->actingAs($organizer)->post('/events', array_merge([
            'name' => 'BBQ Caro',
            'event_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 480,
            'headcount' => 12,
            'recipient_name' => 'Caro',
            'accepted_methods' => ['yape', 'plin'],
            'pay_deadline' => now()->addDays(5)->toDateString(),
        ], $overrides))->assertRedirect();

        return [$organizer, Event::latest('id')->firstOrFail()];
    }

    private function upload(Event $event, string $name): TestResponse
    {
        return $this->post("/e/{$event->slug}/receipts", [
            'name' => $name,
            'image' => UploadedFile::fake()->image('voucher.jpg'),
        ]);
    }

    /**
     * Pin what the vision model "reads" for the inline job in this test.
     */
    private function visionReturns(ReceiptExtraction $extraction): void
    {
        $this->app->instance(ReceiptVision::class, new class($extraction) implements ReceiptVision {
            public function __construct(private ReceiptExtraction $extraction) {}

            public function extract(Receipt $receipt): ReceiptExtraction
            {
                return $this->extraction;
            }
        });
    }
}
