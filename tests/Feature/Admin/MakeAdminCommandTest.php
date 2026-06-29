<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_an_existing_user(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->artisan('admin:make', ['email' => $user->email])
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->is_active);
    }

    public function test_it_creates_a_new_admin_when_the_email_is_unknown(): void
    {
        $this->artisan('admin:make', [
            'email' => 'boss@cuentaclara.test',
            '--name' => 'Boss',
            '--password' => 'secret123',
        ])->assertSuccessful();

        $user = User::where('email', 'boss@cuentaclara.test')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertSame('Boss', $user->name);
    }
}
