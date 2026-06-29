<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(EventExpense::class);
    }

    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'event_date',
        'total_cents',
        'headcount',
        'share_cents',
        'recipient_name',
        'recipient_handle',
        'accepted_methods',
        'pay_deadline',
        'status',
    ];

    protected $casts = [
        'event_date' => 'date',
        'pay_deadline' => 'date',
        'accepted_methods' => 'array',
        'total_cents' => 'integer',
        'headcount' => 'integer',
        'share_cents' => 'integer',
        'status' => EventStatus::class,
    ];

    /**
     * Route-model binding uses the public, unguessable slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate a short, unguessable, URL-safe slug for the public link.
     */
    public static function generateSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(10));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Equal split: the per-participant share in cents (floor division).
     * The rounding remainder is reconciled later (see docs/08 BR-S1); for the
     * MVP create flow we expose the base share only.
     */
    public static function shareFor(int $totalCents, int $headcount): int
    {
        return $headcount > 0 ? intdiv($totalCents, $headcount) : 0;
    }
}
