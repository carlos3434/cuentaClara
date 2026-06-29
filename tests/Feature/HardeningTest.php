<?php

namespace Tests\Feature;

use App\Jobs\ValidateReceiptJob;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Ai\Contracts\ReceiptVision;
use App\Services\Ai\ReceiptExtraction;
use App\Services\Ai\ReceiptRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    // --- Rate limiting --------------------------------------------------

    public function test_public_upload_is_rate_limited(): void
    {
        config()->set('cuentaclara.rate_limits.uploads', 2);
        Queue::fake();
        Storage::fake(config('cuentaclara.receipts_disk'));
        $event = Event::factory()->create();

        $payload = fn () => [
            'name' => 'José',
            'image' => UploadedFile::fake()->image('v.jpg'),
        ];

        $this->post("/e/{$event->slug}/receipts", $payload())->assertRedirect();
        $this->post("/e/{$event->slug}/receipts", $payload())->assertRedirect();
        $this->post("/e/{$event->slug}/receipts", $payload())->assertStatus(429);
    }

    public function test_login_is_rate_limited(): void
    {
        config()->set('cuentaclara.rate_limits.login', 2);
        User::factory()->create(['email' => 'caro@example.com', 'password' => Hash::make('password123')]);

        $bad = ['email' => 'caro@example.com', 'password' => 'wrong'];

        $this->post('/login', $bad)->assertRedirect();   // 1
        $this->post('/login', $bad)->assertRedirect();   // 2
        $this->post('/login', $bad)->assertStatus(429);  // 3 → throttled
    }

    // --- Close / reopen -------------------------------------------------

    public function test_organizer_can_close_and_reopen_an_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        $this->actingAs($owner)->post("/events/{$event->slug}/close")->assertRedirect();
        $this->assertSame('closed', $event->fresh()->status);

        $this->actingAs($owner)->post("/events/{$event->slug}/reopen")->assertRedirect();
        $this->assertSame('active', $event->fresh()->status);
    }

    public function test_another_organizer_cannot_close_your_event(): void
    {
        $event = Event::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->post("/events/{$event->slug}/close")
            ->assertForbidden();
    }

    public function test_closed_event_rejects_uploads(): void
    {
        Queue::fake();
        Storage::fake(config('cuentaclara.receipts_disk'));
        $event = Event::factory()->create(['status' => 'closed']);

        $this->post("/e/{$event->slug}/receipts", [
            'name' => 'José',
            'image' => UploadedFile::fake()->image('v.jpg'),
        ])->assertNotFound();

        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_public_page_reflects_closed_status(): void
    {
        $event = Event::factory()->create(['status' => 'closed']);

        $this->get("/e/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('event.status', 'closed'));
    }

    // --- AI validation logging ------------------------------------------

    public function test_validation_is_logged(): void
    {
        Log::spy();

        $event = Event::factory()->create(['share_cents' => 4000, 'total_cents' => 40000, 'headcount' => 10]);
        $participant = Participant::factory()->for($event)->create();
        $receipt = Receipt::factory()->for($event)->for($participant)->create(['status' => 'submitted']);

        $this->app->instance(ReceiptVision::class, new class implements ReceiptVision {
            public function extract(Receipt $receipt): ReceiptExtraction
            {
                return new ReceiptExtraction(true, 4000, 'PEN', '2026-06-24', 'yape', 'Caro', 0.95, 'ok');
            }
        });

        (new ValidateReceiptJob($receipt))->handle(app(ReceiptVision::class), new ReceiptRuleEngine());

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context = []) => $message === 'receipt.validated'
                && ($context['verdict'] ?? null) === 'validated'
                && array_key_exists('latency_ms', $context))
            ->once();
    }
}
