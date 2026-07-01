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

    private function organizerPayload(array $overrides = []): array
    {
        return array_merge([
            'event_date' => now()->addDays(10)->toDateString(),
            'pay_deadline' => now()->addDays(8)->toDateString(),
            'total_amount' => '600',
        ], $overrides);
    }

    private function adminPayload(array $overrides = []): array
    {
        return array_merge($this->organizerPayload(), [
            'name' => 'Cena de fin de año',
            'headcount' => 5,
            'recipient_name' => 'Caro',
            'recipient_handle' => '999111222',
            'accepted_methods' => ['yape', 'plin'],
            'slug' => 'nuevo-enlace',
        ], $overrides);
    }

    public function test_admin_can_open_the_edit_screen_of_any_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->get("/events/{$event->slug}/edit")
            ->assertOk();
    }

    public function test_organizer_can_edit_dates_and_amount_and_share_recomputes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create([
            'total_cents' => 48000,
            'headcount' => 12,
            'share_cents' => 4000,
        ]);

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->organizerPayload(['total_amount' => '600']))
            ->assertRedirect("/events/{$event->slug}/review");

        $event->refresh();
        $this->assertSame(60000, $event->total_cents);
        // headcount unchanged (12) → 60000 / 12 = 5000
        $this->assertSame(12, $event->headcount);
        $this->assertSame(5000, $event->share_cents);
    }

    public function test_organizer_cannot_change_admin_only_fields(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create([
            'name' => 'Original',
            'slug' => 'original-slug',
            'headcount' => 12,
        ]);

        // Craft a request with forbidden fields; they must be ignored.
        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->organizerPayload([
                'name' => 'Hackeado',
                'slug' => 'hackeado',
                'headcount' => 1,
            ]))
            ->assertRedirect("/events/{$event->slug}/review");

        $event->refresh();
        $this->assertSame('Original', $event->name);
        $this->assertSame('original-slug', $event->slug);
        $this->assertSame(12, $event->headcount);
    }

    public function test_organizer_cannot_edit_another_organizers_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $intruder = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->put("/events/{$event->slug}", $this->organizerPayload())
            ->assertForbidden();
    }

    public function test_admin_can_edit_all_fields_including_the_link(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create([
            'total_cents' => 48000,
            'headcount' => 12,
        ]);

        $this->actingAs($admin)
            ->put("/events/{$event->slug}", $this->adminPayload(['total_amount' => '600', 'headcount' => 5]))
            ->assertRedirect('/events/nuevo-enlace/review');

        $event->refresh();
        $this->assertSame('nuevo-enlace', $event->slug);
        $this->assertSame('Cena de fin de año', $event->name);
        $this->assertSame(60000, $event->total_cents);
        $this->assertSame(5, $event->headcount);
        // 60000 / 5 = 12000
        $this->assertSame(12000, $event->share_cents);
    }

    public function test_admin_slug_must_be_unique(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Event::factory()->for($owner)->create(['slug' => 'taken-slug']);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->put("/events/{$event->slug}", $this->adminPayload(['slug' => 'taken-slug']))
            ->assertSessionHasErrors('slug');
    }

    public function test_deadline_in_the_past_is_allowed_when_editing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->put("/events/{$event->slug}", $this->organizerPayload([
                'pay_deadline' => now()->subDays(3)->toDateString(),
            ]))
            ->assertRedirect("/events/{$event->slug}/review");
    }

    public function test_admin_can_keep_the_same_slug(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create(['slug' => 'mismo-slug']);

        $this->actingAs($admin)
            ->put("/events/{$event->slug}", $this->adminPayload([
                'slug' => 'mismo-slug',
                'name' => 'Nombre actualizado',
            ]))
            ->assertRedirect('/events/mismo-slug/review');

        $event->refresh();
        $this->assertSame('mismo-slug', $event->slug);
        $this->assertSame('Nombre actualizado', $event->name);
    }

    public function test_admin_slug_rejects_invalid_characters(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $event = Event::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->put("/events/{$event->slug}", $this->adminPayload(['slug' => 'Bad_Slug!']))
            ->assertSessionHasErrors('slug');
    }
}
