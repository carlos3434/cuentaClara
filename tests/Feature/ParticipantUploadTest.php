<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParticipantUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Validation runs async; keep these tests focused on upload + storage.
        Queue::fake();
    }

    private function disk(): string
    {
        return config('cuentaclara.receipts_disk');
    }

    public function test_public_page_has_no_participant_without_a_cookie(): void
    {
        $event = Event::factory()->create();

        $this->get("/e/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Event')
                ->where('participant', null)
                ->where('event.slug', $event->slug));
    }

    public function test_a_new_participant_can_identify_and_upload_in_one_step(): void
    {
        Storage::fake($this->disk());
        $event = Event::factory()->create();

        $response = $this->post("/e/{$event->slug}/receipts", [
            'name' => 'José',
            'image' => UploadedFile::fake()->image('voucher.jpg'),
        ]);

        $response->assertRedirect("/e/{$event->slug}");
        $response->assertSessionHas('uploaded', true);
        $response->assertCookie("cc_p_{$event->id}");

        $participant = Participant::firstOrFail();
        $this->assertSame('José', $participant->name);
        $this->assertSame($event->id, $participant->event_id);
        $this->assertSame('pending', $participant->status->value);

        $receipt = Receipt::firstOrFail();
        $this->assertSame($participant->id, $receipt->participant_id);
        $this->assertSame('submitted', $receipt->status->value);
        $this->assertNotNull($receipt->s3_key);

        Storage::disk($this->disk())->assertExists($receipt->s3_key);
    }

    public function test_upload_requires_a_name_for_a_first_time_participant(): void
    {
        Storage::fake($this->disk());
        $event = Event::factory()->create();

        $this->post("/e/{$event->slug}/receipts", [
            'image' => UploadedFile::fake()->image('voucher.jpg'),
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_upload_requires_an_image(): void
    {
        $event = Event::factory()->create();

        $this->post("/e/{$event->slug}/receipts", [
            'name' => 'José',
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_non_image_files_are_rejected(): void
    {
        Storage::fake($this->disk());
        $event = Event::factory()->create();

        $this->post("/e/{$event->slug}/receipts", [
            'name' => 'José',
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_a_returning_participant_uploads_again_without_re_identifying(): void
    {
        Storage::fake($this->disk());
        $event = Event::factory()->create();
        $participant = Participant::factory()->for($event)->create(['name' => 'Lucía']);

        $response = $this->withCookie("cc_p_{$event->id}", $participant->session_token)
            ->post("/e/{$event->slug}/receipts", [
                'image' => UploadedFile::fake()->image('voucher2.jpg'),
            ]);

        $response->assertRedirect("/e/{$event->slug}");

        // No duplicate participant was created.
        $this->assertDatabaseCount('participants', 1);
        $this->assertSame(1, $participant->receipts()->count());
    }

    public function test_returning_participant_sees_their_pending_badge(): void
    {
        $event = Event::factory()->create();
        $participant = Participant::factory()->for($event)->create(['name' => 'Lucía']);
        Receipt::factory()->for($event)->for($participant)->create(['status' => 'submitted']);

        $this->withCookie("cc_p_{$event->id}", $participant->session_token)
            ->get("/e/{$event->slug}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('participant.name', 'Lucía')
                ->where('participant.badge', 'pending'));
    }

    public function test_session_token_is_never_exposed_publicly(): void
    {
        $event = Event::factory()->create();
        $participant = Participant::factory()->for($event)->create();

        $this->withCookie("cc_p_{$event->id}", $participant->session_token)
            ->get("/e/{$event->slug}")
            ->assertInertia(fn (Assert $page) => $page->missing('participant.session_token'));
    }

    public function test_uploads_are_rejected_for_non_active_events(): void
    {
        Storage::fake($this->disk());
        $event = Event::factory()->create(['status' => 'closed']);

        $this->post("/e/{$event->slug}/receipts", [
            'name' => 'José',
            'image' => UploadedFile::fake()->image('voucher.jpg'),
        ])->assertNotFound();

        $this->assertDatabaseCount('receipts', 0);
    }
}
