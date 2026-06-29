<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdminCommand extends Command
{
    protected $signature = 'admin:make {email} {--name=Admin} {--password=}';

    protected $description = 'Promote an existing user to admin, or create a new admin user.';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($user = User::where('email', $email)->first()) {
            $user->update(['role' => UserRole::Admin, 'is_active' => true]);
            $this->info("'{$email}' is now an admin.");

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: Str::random(16);

        User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => $password, // hashed by the model cast
            'role' => UserRole::Admin,
        ]);

        $this->info("Created admin '{$email}'.");
        if (! $this->option('password')) {
            $this->warn("Temporary password: {$password}");
        }

        return self::SUCCESS;
    }
}
