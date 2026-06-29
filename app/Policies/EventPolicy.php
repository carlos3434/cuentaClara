<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * An organizer may manage (review, edit, close, upload to) only their own
     * events. Auto-discovered by Laravel for the Event model.
     */
    public function manage(User $user, Event $event): bool
    {
        return $event->user_id === $user->id;
    }
}
