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
                ->has('events.data', 0)
                ->where('events.total', 0)
                ->where('events.next_page', null));
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
                ->where('events.total', 2)
                ->has('events.data', 2)
                ->has('events.data.0', fn (Assert $event) => $event
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
        Event::factory()->create(['user_id' => $owner->id, 'name' => 'Older']);
        Event::factory()->create(['user_id' => $owner->id, 'name' => 'Newer']);

        $this->actingAs($owner)
            ->get('/events')
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.data.0.name', 'Newer')
                ->where('events.data.1.name', 'Older'));
    }

    public function test_dashboard_paginates_and_load_more_returns_the_rest(): void
    {
        $owner = User::factory()->create();
        Event::factory()->count(12)->create(['user_id' => $owner->id]);

        // First page: 10 of 12, with a pointer to page 2.
        $this->actingAs($owner)
            ->get('/events')
            ->assertInertia(fn (Assert $page) => $page
                ->has('events.data', 10)
                ->where('events.total', 12)
                ->where('events.next_page', 2));

        // "Ver más" → JSON with the remaining 2 and no further pages.
        $this->actingAs($owner)
            ->getJson('/events/more?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('next_page', null)
            ->assertJsonPath('total', 12);
    }

    public function test_load_more_requires_authentication(): void
    {
        $this->get('/events/more')->assertRedirect('/login');
    }
}
