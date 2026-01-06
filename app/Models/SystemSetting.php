<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $setting = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            return self::where('key', $key)->first();
        });

        if (!$setting) {
            return $default;
        }

        return self::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value, ?string $type = null, ?string $group = null): bool
    {
        $setting = self::where('key', $key)->first();

        if ($setting) {
            $setting->value = is_array($value) ? json_encode($value) : $value;
            if ($type) {
                $setting->type = $type;
            }
            if ($group) {
                $setting->group = $group;
            }
            $setting->save();
        } else {
            self::create([
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => $type ?? 'string',
                'group' => $group ?? 'general',
            ]);
        }

        Cache::forget("setting_{$key}");
        Cache::forget('settings_analytics');

        return true;
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        $settings = self::where('group', $group)->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = [
                'value' => self::castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'description' => $setting->description,
            ];
        }

        return $result;
    }

    /**
     * Get analytics settings (cached)
     */
    public static function getAnalyticsSettings(): array
    {
        return Cache::remember('settings_analytics', 3600, function () {
            return [
                'google_analytics_id' => self::get('google_analytics_id'),
                'google_analytics_enabled' => self::get('google_analytics_enabled', false),
            ];
        });
    }

    /**
     * Cast value to appropriate type
     */
    protected static function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value === 'true' || $value === '1' || $value === 1 || $value === true,
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
