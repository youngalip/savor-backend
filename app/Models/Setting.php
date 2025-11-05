<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'value', 'type', 'description', 'is_editable', 'category'
    ];

    protected $casts = [
        'is_editable' => 'boolean',
    ];

    const CACHE_DURATION = 3600; // 1 hour

    // Get setting value by key
    public static function get(string $key, $default = null)
    {
        $cacheKey = "setting_{$key}";
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    // Set setting value
    public static function set(string $key, $value): bool
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting || !$setting->is_editable) {
            return false;
        }

        $setting->value = self::prepareValue($value, $setting->type);
        $saved = $setting->save();

        if ($saved) {
            Cache::forget("setting_{$key}");
        }

        return $saved;
    }

    // Get pricing rates
    public static function getPricingRates(): array
    {
        return [
            'service_charge_rate' => self::get('service_charge_rate', 0.07),
            'tax_rate' => self::get('tax_rate', 0.10)
        ];
    }

    // Cast value based on type
    protected static function castValue(string $value, string $type)
    {
        return match($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    // Prepare value for storage
    protected static function prepareValue($value, string $type): string
    {
        return match($type) {
            'json' => json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    // Clear all cache
    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget("setting_{$setting->key}");
        }
    }

    // Relationship
    public function history()
    {
        return $this->hasMany(SettingHistory::class);
    }
}