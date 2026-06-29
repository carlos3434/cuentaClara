<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global key/value app settings the admin can change at runtime. Backs
 * helpers like the manual/auto review-mode toggle, overriding config defaults.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Read a setting, falling back to $default when it has never been set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->find($key)?->value ?? $default;
    }

    /**
     * Create or update a setting.
     */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
