<?php

namespace App\Models;

use App\Enums\DecidedBy;
use App\Enums\ReasonCode;
use App\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'participant_id',
        's3_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'status',
        'note',
        'extracted_amount_cents',
        'extracted_currency',
        'extracted_date',
        'extracted_method',
        'extracted_recipient',
        'extracted_operation',
        'confidence',
        'ai_explanation',
        'ai_raw',
        'reason_code',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'extracted_amount_cents' => 'integer',
        'extracted_date' => 'date',
        'confidence' => 'float',
        'ai_raw' => 'array',
        'decided_at' => 'datetime',
        'status' => ReceiptStatus::class,
        'reason_code' => ReasonCode::class,
        'decided_by' => DecidedBy::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
