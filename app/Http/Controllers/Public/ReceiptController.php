<?php

namespace App\Http\Controllers\Public;

use App\Enums\EventStatus;
use App\Enums\ParticipantStatus;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\ResolvesParticipant;
use App\Jobs\ValidateReceiptJob;
use App\Models\Event;
use App\Services\Storage\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReceiptController extends Controller
{
    use ResolvesParticipant;

    /**
     * A participant uploads their payment receipt (one screen: name + photo).
     * No login: the participant is created on first upload and bound to a
     * long-lived cookie so they can return and see their status.
     *
     * No AI yet — the receipt is stored as `submitted`.
     */
    public function store(Request $request, Event $event, ReceiptStorage $storage): RedirectResponse
    {
        abort_if($event->status !== EventStatus::Active, 404);

        $participant = $this->currentParticipant($request, $event);

        $maxKb = config('cuentaclara.receipts_max_kb');
        $rules = [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', "max:{$maxKb}"],
        ];
        // First-time participants must tell us who they are.
        if (! $participant) {
            $rules['name'] = ['required', 'string', 'max:80'];
        }

        $validated = $request->validate($rules, [
            'image.required' => 'Adjunta una foto de tu voucher.',
            'image.mimes' => 'Sube una imagen (JPG, PNG, WEBP o HEIC).',
            'image.max' => 'La imagen es demasiado grande.',
            'name.required' => 'Ingresa tu nombre.',
        ]);

        $newCookie = null;
        if (! $participant) {
            $participant = $event->participants()->create([
                'name' => $validated['name'],
                'session_token' => Str::random(48),
                'status' => ParticipantStatus::Pending,
            ]);

            // Remember this participant for ~6 months on this device.
            $newCookie = cookie(
                $this->participantCookie($event),
                $participant->session_token,
                60 * 24 * 180,
            );
        }

        $file = $validated['image'];
        $receipt = $participant->receipts()->create([
            'event_id' => $event->id,
            's3_key' => $storage->store($file, $event),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status' => ReceiptStatus::Submitted,
        ]);

        // Read the receipt asynchronously (OCR/AI) to assist the organizer.
        // The participant isn't blocked on it. Whether the reading can
        // auto-approve the payment is decided inside the job by review_mode;
        // in 'manual' mode it only fills the extracted fields.
        ValidateReceiptJob::dispatch($receipt);

        $redirect = redirect()
            ->route('public.events.show', $event)
            ->with('uploaded', true);

        return $newCookie ? $redirect->withCookie($newCookie) : $redirect;
    }
}
