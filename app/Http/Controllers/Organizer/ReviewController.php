<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends Controller
{
    private const PAID = ['validated', 'cash'];

    /**
     * Per-event review hub: the needs-review queue + participant roster +
     * collected/pending totals (derived from receipts, not faked).
     */
    public function show(Event $event): Response
    {
        $this->authorizeEvent($event);

        $event->load([
            'participants.receipts' => fn ($q) => $q->latest('id'),
            'receipts' => fn ($q) => $q->where('status', 'needs_review')->latest('id'),
            'receipts.participant',
            'expenses' => fn ($q) => $q->latest('id'),
        ]);

        $participants = $event->participants->map(function (Participant $p) use ($event) {
            $paid = $p->receipts->whereIn('status', self::PAID)->isNotEmpty();
            $latest = $p->receipts->first();

            $status = $paid
                ? 'paid'
                : ($latest ? match ($latest->status) {
                    'needs_review' => 'review',
                    'rejected' => 'rejected',
                    default => 'pending',
                } : 'pending');

            return [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $status,
                // Latest uploaded voucher, so the organizer can inspect any
                // participant's payment — not only those in the review queue.
                'receipt' => $latest ? $this->receiptPayload($event, $latest) : null,
            ];
        })->values();

        $paidCount = $participants->where('status', 'paid')->count();
        $collected = $paidCount * $event->share_cents;

        $review = $event->receipts
            ->map(fn (Receipt $r) => $this->receiptPayload($event, $r))
            ->values();

        return Inertia::render('Organizer/Review', [
            'event' => [
                'slug' => $event->slug,
                'name' => $event->name,
                'total_cents' => $event->total_cents,
                'share_cents' => $event->share_cents,
                'headcount' => $event->headcount,
                'status' => $event->status,
                'recipient_name' => $event->recipient_name,
                'recipient_handle' => $event->recipient_handle,
                'pay_deadline' => $event->pay_deadline->toDateString(),
                'public_url' => route('public.events.show', $event),
            ],
            'summary' => [
                'collected_cents' => $collected,
                'pending_cents' => max(0, $event->total_cents - $collected),
                'paid_count' => $paidCount,
                'headcount' => $event->headcount,
                'review_count' => $review->count(),
            ],
            'review' => $review,
            'participants' => $participants,
            'expenses' => $event->expenses->map(fn ($e) => [
                'id' => $e->id,
                'note' => $e->note,
                'image_url' => route('organizer.expenses.image', [$event, $e]),
                'created_at' => $e->created_at->toIso8601String(),
            ])->values(),
            'share_url' => route('organizer.events.created', $event),
        ]);
    }

    /**
     * Stream a receipt image to its owning organizer (private disk).
     */
    public function image(Event $event, Receipt $receipt): StreamedResponse
    {
        $this->authorizeReceipt($event, $receipt);
        abort_if($receipt->s3_key === null, 404);

        $disk = Storage::disk(config('cuentaclara.receipts_disk'));
        abort_unless($disk->exists($receipt->s3_key), 404);

        return $disk->response($receipt->s3_key);
    }

    public function approve(Event $event, Receipt $receipt): RedirectResponse
    {
        $this->authorizeReceipt($event, $receipt);
        $this->decide($receipt, 'validated', null);

        return back();
    }

    public function reject(Event $event, Receipt $receipt): RedirectResponse
    {
        $this->authorizeReceipt($event, $receipt);
        $this->decide($receipt, 'rejected', 'organizer_rejected');

        return back();
    }

    /**
     * Record a manual cash payment for a participant (no receipt image).
     */
    public function cash(Event $event, Participant $participant): RedirectResponse
    {
        $this->authorizeEvent($event);
        abort_unless($participant->event_id === $event->id, 404);

        $participant->receipts()->create([
            'event_id' => $event->id,
            'status' => 'cash',
            'decided_by' => 'organizer',
            'decided_at' => now(),
        ]);

        return back();
    }

    private function decide(Receipt $receipt, string $status, ?string $reason): void
    {
        $receipt->update([
            'status' => $status,
            'reason_code' => $reason,
            'decided_by' => 'organizer',
            'decided_at' => now(),
        ]);
    }

    private function authorizeEvent(Event $event): void
    {
        abort_unless($event->user_id === auth()->id(), 403);
    }

    private function authorizeReceipt(Event $event, Receipt $receipt): void
    {
        $this->authorizeEvent($event);
        abort_unless($receipt->event_id === $event->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(Event $event, Receipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'participant' => $receipt->participant?->name,
            'status' => $receipt->status,
            'reason_code' => $receipt->reason_code,
            'amount_cents' => $receipt->extracted_amount_cents,
            'date' => $receipt->extracted_date?->toDateString(),
            'method' => $receipt->extracted_method,
            'recipient' => $receipt->extracted_recipient,
            'confidence' => $receipt->confidence,
            'explanation' => $receipt->ai_explanation,
            'image_url' => $receipt->s3_key ? route('organizer.receipts.image', [$event, $receipt]) : null,
            'created_at' => $receipt->created_at->toIso8601String(),
        ];
    }
}
