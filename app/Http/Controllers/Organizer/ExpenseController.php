<?php

namespace App\Http\Controllers\Organizer;

use App\Actions\Events\StoreExpenseReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Event;
use App\Models\EventExpense;
use App\Services\Storage\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Organizer's own cost evidence for an event (the venue, the gift, etc.).
 * Store-only in v1 — no AI extraction or mismatch flagging (docs/06 BR-X).
 */
class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request, Event $event, StoreExpenseReceipt $expenses): RedirectResponse
    {
        $this->authorize('manage', $event);

        $expenses->handle($event, $request->file('image'), $request->validated()['note'] ?? null);

        return back();
    }

    public function image(Event $event, EventExpense $expense, ReceiptStorage $storage): StreamedResponse
    {
        $this->authorize('manage', $event);
        abort_unless($expense->event_id === $event->id, 404);
        abort_unless($storage->exists($expense->s3_key), 404);

        return $storage->streamResponse($expense->s3_key);
    }

    public function destroy(Event $event, EventExpense $expense, ReceiptStorage $storage): RedirectResponse
    {
        $this->authorize('manage', $event);
        abort_unless($expense->event_id === $event->id, 404);

        $storage->delete($expense->s3_key);
        $expense->delete();

        return back();
    }
}
