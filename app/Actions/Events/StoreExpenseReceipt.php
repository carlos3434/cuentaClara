<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\EventExpense;
use App\Services\Storage\ReceiptStorage;
use Illuminate\Http\UploadedFile;

/**
 * Stores the organizer's own expense receipt for an event. Shared by the
 * create-event flow and the review hub so the persistence logic lives once.
 */
class StoreExpenseReceipt
{
    public function __construct(private readonly ReceiptStorage $storage) {}

    public function handle(Event $event, UploadedFile $file, ?string $note = null): EventExpense
    {
        return $event->expenses()->create([
            's3_key' => $this->storage->store($file, $event, 'expenses'),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'note' => $note,
        ]);
    }
}
