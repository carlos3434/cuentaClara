<?php

namespace App\Services\Storage;

use App\Models\Event;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Single gateway to the private receipts/expenses disk. Centralizes the
 * config lookup and the store/read/stream/delete operations so callers don't
 * reach for the Storage facade + config key directly.
 */
class ReceiptStorage
{
    /**
     * Store an uploaded image and return its key. $folder separates participant
     * receipts from organizer expense receipts.
     */
    public function store(UploadedFile $file, Event $event, string $folder = 'receipts'): string
    {
        return $file->store("events/{$event->id}/{$folder}", $this->diskName());
    }

    public function get(string $key): ?string
    {
        return $this->disk()->get($key);
    }

    public function exists(string $key): bool
    {
        return $this->disk()->exists($key);
    }

    public function delete(string $key): void
    {
        $this->disk()->delete($key);
    }

    public function streamResponse(string $key): StreamedResponse
    {
        return $this->disk()->response($key);
    }

    public function diskName(): string
    {
        return config('cuentaclara.receipts_disk');
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }
}
