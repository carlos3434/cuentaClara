<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        's3_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'note',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
