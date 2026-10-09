<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Fetch several settings at once. $defaults is [key => default]; the result uses the same keys.
     *
     * @param  array<string, string>  $defaults
     * @return array<string, string>
     */
    public static function many(array $defaults): array
    {
        $stored = static::whereIn('key', array_keys($defaults))->pluck('value', 'key');

        return collect($defaults)->map(fn ($default, $key) => $stored[$key] ?? $default)->all();
    }
}
