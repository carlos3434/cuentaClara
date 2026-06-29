<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const MAX_EVENTS = 50;

    /**
     * Platform overview: the global review-mode setting plus a payments-per-event
     * table across all organizers.
     */
    public function index(): Response
    {
        $paid = [ReceiptStatus::Validated->value, ReceiptStatus::Cash->value];

        $events = Event::query()
            ->with('user:id,name')
            ->withCount(['participants as paid_count' => fn ($q) => $q->whereHas(
                'receipts',
                fn ($r) => $r->whereIn('status', $paid),
            )])
            ->latest('id')
            ->limit(self::MAX_EVENTS)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'review_mode' => Setting::get('review_mode', config('cuentaclara.review_mode')),
            'totals' => [
                'events' => Event::count(),
                'organizers' => User::query()->where('role', \App\Enums\UserRole::Organizer->value)->count(),
                'shown' => $events->count(),
            ],
            'events' => $events->map(fn (Event $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'organizer' => $e->user?->name,
                'status' => $e->status,
                'headcount' => $e->headcount,
                'paid_count' => $e->paid_count,
                'collected_cents' => $e->paid_count * $e->share_cents,
                'total_cents' => $e->total_cents,
            ])->all(),
        ]);
    }
}
