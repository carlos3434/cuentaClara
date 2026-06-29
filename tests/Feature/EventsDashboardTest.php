<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/events')->assertRedirect('/login');
    }

    public function test_dashboard_renders_with_an_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/events')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events', 0));
    }

    public function test_dashboard_lists_only_the_authenticated_organizers_events(): void
    {
        $owner = User::factory()->create();
        Event::factory()->count(2)->create(['user_id' => $owner->id]);

        $other = User::factory()->create();
        Event::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)
            ->get('/events')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Index')
                ->has('events', 2)
                ->has('events.0', fn (Assert $event) => $event
                    ->has('slug')
                    ->has('name')
                    ->has('total_cents')
                    ->has('share_cents')
                    ->has('headcount')
                    ->has('status')
                    ->has('public_url')
                    ->has('share_url')
                    ->etc()));
    }

    public function test_dashboard_shows_newest_events_first(): void
    {
        $owner = User::factory()->create();
        $older = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Older']);
        $newer = Event::factory()->create(['user_id' => $owner->id, 'name' => 'Newer']);

        $this->actingAs($owner)
            ->get('/events')
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.0.name', 'Newer')
                ->where('events.1.name', 'Older'));
    }
}
