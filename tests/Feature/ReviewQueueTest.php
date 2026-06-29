<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $event = Event::factory()->create();

        $this->get("/events/{$event->slug}/review")->assertRedirect('/login');
    }

    public function test_an_organizer_cannot_review_another_organizers_event(): void
    {
        $event = Event::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->get("/events/{$event->slug}/review")
            ->assertForbidden();
    }

    public function test_review_page_lists_queue_and_totals(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $owner->id,
            'share_cents' => 4000,
            'total_cents' => 48000,
            'headcount' => 12,
        ]);

        // Two participants paid; one needs review.
        $this->paidParticipant($event, 'validated');
        $this->paidParticipant($event, 'cash');
        $review = Participant::factory()->for($event)->create(['name' => 'Lucía']);
        Receipt::factory()->for($event)->for($review)->create(['status' => 'needs_review', 'reason_code' => 'amount_mismatch']);

        $this->actingAs($owner)
            ->get("/events/{$event->slug}/review")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizer/Review')
                ->where('summary.paid_count', 2)
                ->where('summary.collected_cents', 8000)   // 2 × 4000
                ->where('summary.pending_cents', 40000)     // 48000 − 8000
                ->where('summary.review_count', 1)
                ->has('review', 1)
                ->has('participants', 3));
    }

    public function test_review_page_exposes_reminder_fields(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'user_id' => $owner->id,
            'recipient_name' => 'Caro',
            'recipient_handle' => '999888777',
        ]);

        $this->actingAs($owner)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.recipient_name', 'Caro')
                ->where('event.recipient_handle', '999888777')
                ->where('event.public_url', route('public.events.show', $event))
                ->has('event.pay_deadline'));
    }

    public function test_organizer_can_approve_a_receipt(): void
    {
        [$owner, $event, $receipt] = $this->reviewableReceipt();

        $this->actingAs($owner)
            ->post("/events/{$event->slug}/receipts/{$receipt->id}/approve")
            ->assertRedirect();

        $receipt->refresh();
        $this->assertSame('validated', $receipt->status);
        $this->assertSame('organizer', $receipt->decided_by);
        $this->assertNull($receipt->reason_code);
        $this->assertNotNull($receipt->decided_at);
    }

    public function test_organizer_can_reject_a_receipt(): void
    {
        [$owner, $event, $receipt] = $this->reviewableReceipt();

        $this->actingAs($owner)
            ->post("/events/{$event->slug}/receipts/{$receipt->id}/reject")
            ->assertRedirect();

        $receipt->refresh();
        $this->assertSame('rejected', $receipt->status);
        $this->assertSame('organizer', $receipt->decided_by);
        $this->assertSame('organizer_rejected', $receipt->reason_code);
    }

    public function test_organizer_can_mark_a_participant_as_paid_in_cash(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $participant = Participant::factory()->for($event)->create();

        $this->actingAs($owner)
            ->post("/events/{$event->slug}/participants/{$participant->id}/cash")
            ->assertRedirect();

        $this->assertDatabaseHas('receipts', [
            'participant_id' => $participant->id,
            'status' => 'cash',
            'decided_by' => 'organizer',
            's3_key' => null,
        ]);
    }

    public function test_cannot_act_on_a_receipt_from_another_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $otherEvent = Event::factory()->create(['user_id' => $owner->id]);
        $receipt = Receipt::factory()->for($otherEvent)
            ->for(Participant::factory()->for($otherEvent))
            ->create(['status' => 'needs_review']);

        $this->actingAs($owner)
            ->post("/events/{$event->slug}/receipts/{$receipt->id}/approve")
            ->assertNotFound();
    }

    public function test_owner_can_stream_a_receipt_image_but_others_cannot(): void
    {
        $disk = config('cuentaclara.receipts_disk');
        Storage::fake($disk);

        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $participant = Participant::factory()->for($event)->create();
        Storage::disk($disk)->put('events/1/receipts/v.jpg', 'binary');
        $receipt = Receipt::factory()->for($event)->for($participant)
            ->create(['s3_key' => 'events/1/receipts/v.jpg']);

        $this->actingAs($owner)
            ->get("/events/{$event->slug}/receipts/{$receipt->id}/image")
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get("/events/{$event->slug}/receipts/{$receipt->id}/image")
            ->assertForbidden();
    }

    private function paidParticipant(Event $event, string $status): void
    {
        $p = Participant::factory()->for($event)->create();
        Receipt::factory()->for($event)->for($p)->create(['status' => $status]);
    }

    /**
     * @return array{0: User, 1: Event, 2: Receipt}
     */
    private function reviewableReceipt(): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $participant = Participant::factory()->for($event)->create();
        $receipt = Receipt::factory()->for($event)->for($participant)
            ->create(['status' => 'needs_review', 'reason_code' => 'amount_mismatch']);

        return [$owner, $event, $receipt];
    }
}
