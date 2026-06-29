<?php

namespace App\Http\Controllers\Public;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\ResolvesParticipant;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use ResolvesParticipant;

    /**
     * Public, read-only event landing page reached via the shared link.
     * Exposes only non-sensitive event data (see docs/10 §8).
     */
    public function show(Request $request, Event $event): Response
    {
        abort_if($event->status === EventStatus::Draft, 404);

        $participant = $this->currentParticipant($request, $event);

        return Inertia::render('Public/Event', [
            'event' => [
                'slug' => $event->slug,
                'name' => $event->name,
                'event_date' => $event->event_date->toDateString(),
                'total_cents' => $event->total_cents,
                'share_cents' => $event->share_cents,
                'recipient_name' => $event->recipient_name,
                'recipient_handle' => $event->recipient_handle,
                'accepted_methods' => $event->accepted_methods,
                'pay_deadline' => $event->pay_deadline->toDateString(),
                'status' => $event->status,
            ],
            'participant' => $participant ? [
                'name' => $participant->name,
                'badge' => $participant->badge(),
            ] : null,
            // The organizer's own expense receipts, so the participant can verify
            // the real cost before paying.
            'expenses' => $event->expenses()->latest('id')->get()->map(fn ($e) => [
                'id' => $e->id,
                'note' => $e->note,
                'image_url' => route('public.expenses.image', [$event, $e]),
            ])->values(),
        ]);
    }
}
