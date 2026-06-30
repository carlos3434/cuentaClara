<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\DecidedBy;
use App\Enums\ReasonCode;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use App\Services\Storage\ReceiptStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends Controller
{
    private const PAID = [ReceiptStatus::Validated, ReceiptStatus::Cash];
    private const PER_PAGE = 10;

    /**
     * Per-event review hub: the needs-review queue + participant roster +
     * collected/pending totals (derived from receipts, not faked).
     */
    public function show(Event $event): Response
    {
        $this->authorizeEvent($event);

        // The review queue holds every receipt still awaiting the organizer's
        // decision: freshly uploaded ones (`submitted`) and any the AI flagged
        // (`needs_review`). Confirmed/rejected/cash receipts have left the queue.
        $event->load([
            'receipts' => fn ($q) => $q
                ->whereIn('status', [ReceiptStatus::Submitted->value, ReceiptStatus::NeedsReview->value])
                ->latest('id'),
            'receipts.participant',
            'expenses' => fn ($q) => $q->latest('id'),
        ]);

        $paidCount = $this->paidCount($event);
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
            'participants' => $this->participantsPage($event),
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
     * Next page of participants for the review hub's "Ver más" (JSON, appended).
     */
    public function participantsMore(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        return response()->json($this->participantsPage($event));
    }

    /**
     * Stream a receipt image to its owning organizer (private disk).
     */
    public function image(Event $event, Receipt $receipt, ReceiptStorage $storage): StreamedResponse
    {
        $this->authorizeReceipt($event, $receipt);
        abort_if($receipt->s3_key === null, 404);
        abort_unless($storage->exists($receipt->s3_key), 404);

        return $storage->streamResponse($receipt->s3_key);
    }

    public function approve(Event $event, Receipt $receipt): RedirectResponse
    {
        $this->authorizeReceipt($event, $receipt);
        $this->decide($receipt, ReceiptStatus::Validated, null);

        return back();
    }

    public function reject(Event $event, Receipt $receipt): RedirectResponse
    {
        $this->authorizeReceipt($event, $receipt);
        $this->decide($receipt, ReceiptStatus::Rejected, ReasonCode::OrganizerRejected);

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
            'status' => ReceiptStatus::Cash,
            'decided_by' => DecidedBy::Organizer,
            'decided_at' => now(),
        ]);

        return back();
    }

    private function decide(Receipt $receipt, ReceiptStatus $status, ?ReasonCode $reason): void
    {
        $receipt->update([
            'status' => $status,
            'reason_code' => $reason,
            'decided_by' => DecidedBy::Organizer,
            'decided_at' => now(),
        ]);
    }

    private function authorizeEvent(Event $event): void
    {
        $this->authorize('manage', $event);
    }

    private function authorizeReceipt(Event $event, Receipt $receipt): void
    {
        $this->authorizeEvent($event);
        abort_unless($receipt->event_id === $event->id, 404);
    }

    private function paidCount(Event $event): int
    {
        return $event->participants()
            ->whereHas('receipts', fn ($q) => $q->whereIn('status', [ReceiptStatus::Validated->value, ReceiptStatus::Cash->value]))
            ->count();
    }

    /**
     * One page of participants (newest first), shaped for "Ver más".
     *
     * @return array{data: array<int, array<string, mixed>>, next_page: ?int, total: int}
     */
    private function participantsPage(Event $event): array
    {
        $paginator = $event->participants()
            ->with(['receipts' => fn ($q) => $q->latest('id')])
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return [
            'data' => collect($paginator->items())->map(fn (Participant $p) => $this->presentParticipant($event, $p))->all(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentParticipant(Event $event, Participant $p): array
    {
        $paid = $p->receipts->whereIn('status', self::PAID)->isNotEmpty();
        $latest = $p->receipts->first();

        $status = $paid
            ? 'paid'
            : ($latest ? match ($latest->status) {
                ReceiptStatus::NeedsReview => 'review',
                ReceiptStatus::Rejected => 'rejected',
                ReceiptStatus::Submitted => 'submitted', // subió, esperando validación de IA
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
            'operation' => $receipt->extracted_operation,
            'confidence' => $receipt->confidence,
            'explanation' => $receipt->ai_explanation,
            'image_url' => $receipt->s3_key ? route('organizer.receipts.image', [$event, $receipt]) : null,
            'created_at' => $receipt->created_at->toIso8601String(),
        ];
    }
}
