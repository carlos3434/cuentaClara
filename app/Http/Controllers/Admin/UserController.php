<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List organizers with their event counts and active state.
     */
    public function index(): Response
    {
        $organizers = User::query()
            ->where('role', UserRole::Organizer->value)
            ->withCount('events')
            ->latest('id')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
                'events_count' => $u->events_count,
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $organizers,
        ]);
    }

    /**
     * Create a new organizer account.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed by the model cast
            'role' => UserRole::Organizer,
        ]);

        return back();
    }

    /**
     * Activate or deactivate an organizer (deactivated users cannot log in).
     */
    public function toggle(User $user): RedirectResponse
    {
        // Admins are managed via the CLI, not this screen.
        abort_if($user->isAdmin(), 403);

        $user->update(['is_active' => ! $user->is_active]);

        return back();
    }
}
