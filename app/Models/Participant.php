<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use App\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'session_token',
        'status',
    ];

    protected $hidden = [
        'session_token',
    ];

    protected $casts = [
        'status' => ParticipantStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Participant-facing badge derived from their latest receipt.
     * none | pending | confirmed | review
     *
     * Until AI/organizer review exists, an uploaded receipt is `submitted`,
     * which surfaces as `pending` ("en revisión").
     */
    public function badge(): string
    {
        $latest = $this->receipts()->latest('id')->first();

        if (! $latest) {
            return 'none';
        }

        return match ($latest->status) {
            ReceiptStatus::Validated, ReceiptStatus::Cash => 'confirmed',
            ReceiptStatus::Rejected => 'review',
            default => 'pending',
        };
    }
}
