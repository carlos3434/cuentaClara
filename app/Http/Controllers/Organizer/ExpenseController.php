<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Event;
use App\Models\EventExpense;
use App\Services\Storage\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Organizer's own cost evidence for an event (the venue, the gift, etc.).
 * Store-only in v1 — no AI extraction or mismatch flagging (docs/06 BR-X).
 */
class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request, Event $event, ReceiptStorage $storage): RedirectResponse
    {
        $this->authorizeEvent($event);

        $file = $request->file('image');
        $event->expenses()->create([
            's3_key' => $storage->store($file, $event, 'expenses'),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'note' => $request->validated()['note'] ?? null,
        ]);

        return back();
    }

    public function image(Event $event, EventExpense $expense): StreamedResponse
    {
        $this->authorizeEvent($event);
        abort_unless($expense->event_id === $event->id, 404);

        $disk = Storage::disk(config('cuentaclara.receipts_disk'));
        abort_unless($disk->exists($expense->s3_key), 404);

        return $disk->response($expense->s3_key);
    }

    public function destroy(Event $event, EventExpense $expense): RedirectResponse
    {
        $this->authorizeEvent($event);
        abort_unless($expense->event_id === $event->id, 404);

        Storage::disk(config('cuentaclara.receipts_disk'))->delete($expense->s3_key);
        $expense->delete();

        return back();
    }

    private function authorizeEvent(Event $event): void
    {
        abort_unless($event->user_id === auth()->id(), 403);
    }
}
