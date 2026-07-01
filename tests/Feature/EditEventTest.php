<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_edit_screen_of_any_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->get("/events/{$event->slug}/edit")
            ->assertOk();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cena de fin de año',
            'event_date' => now()->addDays(10)->toDateString(),
            'total_amount' => '600',
            'headcount' => 5,
            'recipient_name' => 'Caro',
            'recipient_handle' => '999111222',
            'accepted_methods' => ['yape', 'plin'],
            'pay_deadline' => now()->addDays(8)->toDateString(),
            'slug' => 'nuevo-enlace',
        ], $overrides);
    }

    public function test_organizer_can_edit_their_event_and_share_is_recomputed(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create([
            'total_cents' => 48000,
            'headcount' => 12,
            'share_cents' => 4000,
        ]);

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->payload())
            ->assertRedirect("/events/nuevo-enlace/review");

        $event->refresh();
        $this->assertSame('nuevo-enlace', $event->slug);
        $this->assertSame('Cena de fin de año', $event->name);
        $this->assertSame(60000, $event->total_cents);
        $this->assertSame(5, $event->headcount);
        // 60000 / 5 = 12000
        $this->assertSame(12000, $event->share_cents);
    }

    public function test_another_organizer_cannot_edit_your_event(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->put("/events/{$event->slug}", $this->payload())
            ->assertForbidden();
    }

    public function test_slug_must_be_unique_across_events(): void
    {
        $owner = User::factory()->create();
        $taken = Event::factory()->for($owner)->create(['slug' => 'taken-slug']);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->payload(['slug' => 'taken-slug']))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'slug' => $event->slug]);
        $this->assertNotSame('taken-slug', $event->fresh()->slug);
    }

    public function test_keeping_the_same_slug_is_allowed(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create(['slug' => 'mi-evento']);

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->payload(['slug' => 'mi-evento', 'name' => 'Nuevo nombre']))
            ->assertRedirect("/events/mi-evento/review");

        $this->assertSame('Nuevo nombre', $event->fresh()->name);
    }

    public function test_slug_rejects_invalid_characters(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->payload(['slug' => 'Con Espacios!']))
            ->assertSessionHasErrors('slug');
    }
}
