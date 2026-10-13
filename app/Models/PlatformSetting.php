<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Retrieve a setting value by key with optional default fallback.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $setting = static::where('key', $key)->first();

            return $setting && $setting->value !== null ? $setting->value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Store or update a setting value by key.
     */
    public static function set(string $key, ?string $value): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Retrieve all settings as a key => value dictionary.
     */
    public static function getAll(): array
    {
        try {
            return static::pluck('value', 'key')->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
