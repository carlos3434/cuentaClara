<?php

namespace App\Http\Controllers\Public\Concerns;

use App\Models\Event;
use App\Models\Participant;
use Illuminate\Http\Request;

trait ResolvesParticipant
{
    /**
     * Cookie name binding a no-login participant to one event.
     */
    protected function participantCookie(Event $event): string
    {
        return "cc_p_{$event->id}";
    }

    /**
     * The participant tied to this request's cookie, if any.
     */
    protected function currentParticipant(Request $request, Event $event): ?Participant
    {
        $token = $request->cookie($this->participantCookie($event));

        if (! $token) {
            return null;
        }

        return $event->participants()
            ->where('session_token', $token)
            ->first();
    }
}
