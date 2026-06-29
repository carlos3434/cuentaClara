<?php

namespace App\Http\Controllers\Public;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventExpense;
use App\Services\Storage\ReceiptStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the organizer's expense receipt to participants (no login) so they can
 * verify the real cost before paying. Scoped to the event's unguessable slug.
 */
class ExpenseController extends Controller
{
    public function image(Event $event, EventExpense $expense, ReceiptStorage $storage): StreamedResponse
    {
        abort_if($event->status === EventStatus::Draft, 404);
        abort_unless($expense->event_id === $event->id, 404);
        abort_unless($storage->exists($expense->s3_key), 404);

        return $storage->streamResponse($expense->s3_key);
    }
}
