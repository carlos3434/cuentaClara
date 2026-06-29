<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_event_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/events/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Events/Create'));
    }

    public function test_organizer_can_create_an_event_and_is_sent_to_the_share_link(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/events', $this->validPayload());

        $event = Event::firstOrFail();

        $this->assertDatabaseHas('events', [
            'user_id' => $user->id,
            'name' => 'BBQ Cumpleaños Caro',
            'total_cents' => 48000,
            'headcount' => 12,
            'share_cents' => 4000, // 48000 / 12
            'recipient_name' => 'Caro',
            'recipient_handle' => '999888777',
            'status' => 'active',
        ]);

        $this->assertSame(['yape', 'plin'], $event->accepted_methods);
        $this->assertNotEmpty($event->slug);

        $response->assertRedirect(route('organizer.events.created', $event));
    }

    public function test_organizer_can_attach_an_expense_receipt_at_creation(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('cuentaclara.receipts_disk'));
        $user = User::factory()->create();

        $payload = $this->validPayload();
        $payload['expense_image'] = \Illuminate\Http\UploadedFile::fake()->image('cancha.jpg');
        $payload['expense_note'] = 'Alquiler de cancha';

        $this->actingAs($user)->post('/events', $payload)->assertRedirect();

        $event = Event::firstOrFail();
        $expense = $event->expenses()->firstOrFail();
        $this->assertSame('Alquiler de cancha', $expense->note);
        $this->assertNotNull($expense->s3_key);
        \Illuminate\Support\Facades\Storage::disk(config('cuentaclara.receipts_disk'))->assertExists($expense->s3_key);
    }

    public function test_event_creates_without_an_expense_receipt(): void
    {
        $this->actingAs(User::factory()->create())->post('/events', $this->validPayload())->assertRedirect();

        $this->assertDatabaseCount('event_expenses', 0);
    }

    public function test_each_event_gets_a_unique_unguessable_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/events', $this->validPayload());
        $this->actingAs($user)->post('/events', $this->validPayload());

        $slugs = Event::pluck('slug');

        $this->assertCount(2, $slugs->unique());
        $this->assertGreaterThanOrEqual(8, strlen($slugs->first()));
    }

    public function test_event_creation_requires_valid_fields(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from('/events/create')
            ->post('/events', [
            'name' => '',
            'headcount' => 0,
            'accepted_methods' => [],
            'pay_deadline' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors([
            'name',
            'event_date',
            'total_amount',
            'headcount',
            'recipient_name',
            'accepted_methods',
            'pay_deadline',
        ]);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_public_event_page_shows_only_safe_data(): void
    {
        $event = Event::factory()->create([
            'name' => 'Pichanga del viernes',
            'recipient_name' => 'Caro',
        ]);

        $this->get("/e/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Event')
                ->where('event.name', 'Pichanga del viernes')
                ->where('event.recipient_name', 'Caro')
                ->where('event.share_cents', $event->share_cents)
                // Internal fields must not leak to the public payload.
                ->missing('event.user_id')
                ->missing('event.id'));
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/e/does-not-exist')->assertNotFound();
    }

    public function test_guests_cannot_reach_the_organizer_area(): void
    {
        $this->get('/events/create')->assertRedirect('/login');
        $this->post('/events', $this->validPayload())->assertRedirect('/login');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_an_organizer_cannot_view_another_organizers_share_page(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('organizer.events.created', $event))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'BBQ Cumpleaños Caro',
            'event_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 480,
            'headcount' => 12,
            'recipient_name' => 'Caro',
            'recipient_handle' => '999888777',
            'accepted_methods' => ['yape', 'plin'],
            'pay_deadline' => now()->addDays(5)->toDateString(),
        ];
    }
}
