<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    // --- Access control -------------------------------------------------

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_organizers_cannot_access_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create()) // role defaults to organizer
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_sees_the_dashboard_with_per_event_payments(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->create([
            'user_id' => User::factory()->create()->id,
            'share_cents' => 4000,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Dashboard')
                ->where('review_mode', 'manual')
                ->where('totals.events', 1)
                ->has('events', 1)
                ->where('events.0.id', $event->id));
    }

    // --- Review-mode toggle --------------------------------------------

    public function test_admin_can_switch_review_mode_to_auto(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings', ['review_mode' => 'auto'])
            ->assertRedirect();

        $this->assertSame('auto', Setting::get('review_mode'));
    }

    public function test_review_mode_setting_overrides_the_config_default(): void
    {
        config(['cuentaclara.review_mode' => 'manual']);
        Setting::put('review_mode', 'auto');

        $this->assertSame('auto', Setting::get('review_mode', config('cuentaclara.review_mode')));
    }

    public function test_settings_update_rejects_an_invalid_mode(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings', ['review_mode' => 'sometimes'])
            ->assertSessionHasErrors('review_mode');
    }

    // --- User management -------------------------------------------------

    public function test_admin_can_create_an_organizer(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/users', [
                'name' => 'Nueva Organizadora',
                'email' => 'org@cuentaclara.test',
                'password' => 'secret123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'org@cuentaclara.test',
            'role' => 'organizer',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_deactivate_and_reactivate_an_organizer(): void
    {
        $admin = $this->admin();
        $organizer = User::factory()->create();

        $this->actingAs($admin)->post("/admin/users/{$organizer->id}/toggle")->assertRedirect();
        $this->assertFalse($organizer->fresh()->is_active);

        $this->actingAs($admin)->post("/admin/users/{$organizer->id}/toggle")->assertRedirect();
        $this->assertTrue($organizer->fresh()->is_active);
    }

    public function test_an_admin_cannot_be_toggled_from_the_users_screen(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post("/admin/users/{$other->id}/toggle")
            ->assertForbidden();

        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_users_index_lists_only_organizers(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/Users')
                ->has('users', 2)); // the admin is not listed
    }

    // --- Auth: deactivated accounts -------------------------------------

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_admin_is_redirected_to_the_admin_area_on_login(): void
    {
        $admin = $this->admin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin');
    }
}
