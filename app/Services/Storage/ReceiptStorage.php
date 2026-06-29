<?php

namespace App\Services\Storage;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReceiptStorage
{
    /**
     * Store an uploaded image on the private disk and return its key.
     * $folder separates participant receipts from organizer expense receipts.
     */
    public function store(UploadedFile $file, Event $event, string $folder = 'receipts'): string
    {
        return $file->store("events/{$event->id}/{$folder}", $this->disk());
    }

    public function disk(): string
    {
        return config('cuentaclara.receipts_disk');
    }
}
