<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * An admin may manage any event (platform oversight); an organizer may
     * manage only their own. Auto-discovered by Laravel for the Event model.
     */
    public function manage(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->user_id === $user->id;
    }
}
