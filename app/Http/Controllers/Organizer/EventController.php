<?php

namespace App\Http\Controllers\Organizer;

use App\Actions\Events\StoreExpenseReceipt;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Organizer dashboard: the user's events, newest first, paginated so the
     * page stays light (the rest load on demand via `more`).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'events' => $this->page($request->user()->events()->latest('id')->paginate(self::PER_PAGE)),
        ]);
    }

    /**
     * Next page of events for the dashboard's "Ver más" (JSON, appended client-side).
     */
    public function more(Request $request): JsonResponse
    {
        return response()->json(
            $this->page($request->user()->events()->latest('id')->paginate(self::PER_PAGE)),
        );
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, next_page: ?int, total: int}
     */
    private function page(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(fn (Event $event) => $this->present($event))->all(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Event $event): array
    {
        return [
            'slug' => $event->slug,
            'name' => $event->name,
            'event_date' => $event->event_date->toDateString(),
            'pay_deadline' => $event->pay_deadline->toDateString(),
            'total_cents' => $event->total_cents,
            'share_cents' => $event->share_cents,
            'headcount' => $event->headcount,
            'status' => $event->status,
            'public_url' => route('public.events.show', $event),
            'share_url' => route('organizer.events.created', $event),
            'review_url' => route('organizer.events.review', $event),
        ];
    }

    /**
     * Mobile-first create-event form.
     */
    public function create(): Response
    {
        return Inertia::render('Events/Create');
    }

    /**
     * Persist the event and hand back its shareable public link.
     */
    public function store(StoreEventRequest $request, StoreExpenseReceipt $expenses): RedirectResponse
    {
        $data = $request->validated();

        $totalCents = (int) round($data['total_amount'] * 100);
        $headcount = (int) $data['headcount'];

        $event = Event::create([
            'user_id' => $request->user()->id,
            'slug' => Event::generateSlug(),
            'name' => $data['name'],
            'event_date' => $data['event_date'],
            'total_cents' => $totalCents,
            'headcount' => $headcount,
            'share_cents' => Event::shareFor($totalCents, $headcount),
            'recipient_name' => $data['recipient_name'],
            'recipient_handle' => $data['recipient_handle'] ?? null,
            'accepted_methods' => array_values($data['accepted_methods']),
            'pay_deadline' => $data['pay_deadline'],
            'status' => EventStatus::Active,
        ]);

        // Optional expense receipt uploaded alongside the event.
        if ($request->hasFile('expense_image')) {
            $expenses->handle($event, $request->file('expense_image'), $data['expense_note'] ?? null);
        }

        return redirect()->route('organizer.events.created', $event);
    }

    /**
     * Close an event: no further participant uploads are accepted.
     */
    public function close(Event $event): RedirectResponse
    {
        $this->authorize('manage', $event);

        $event->update(['status' => EventStatus::Closed]);

        return back();
    }

    /**
     * Reopen a closed event.
     */
    public function reopen(Event $event): RedirectResponse
    {
        $this->authorize('manage', $event);

        $event->update(['status' => EventStatus::Active]);

        return back();
    }

    /**
     * Success screen: shows the generated shareable link.
     */
    public function created(Event $event): Response
    {
        // An organizer may only see the share page for their own events.
        $this->authorize('manage', $event);

        return Inertia::render('Events/Created', [
            'event' => [
                'name' => $event->name,
                'share_cents' => $event->share_cents,
                'total_cents' => $event->total_cents,
                'headcount' => $event->headcount,
            ],
            'public_url' => route('public.events.show', $event),
        ]);
    }
}
