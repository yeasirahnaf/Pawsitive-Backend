<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    /**
     * Return all settings with values cast to their declared types.
     */
    public function all(): array
    {
        return SystemSetting::all()
            ->mapWithKeys(fn ($s) => [$s->key => $s->typedValue()])
            ->all();
    }

    /**
     * Get a single setting value (typed).
     */
    public function get(string $key): mixed
    {
        $setting = SystemSetting::where('key', $key)->firstOrFail();
        return $setting->typedValue();
    }

    /**
     * Update a setting value (admin only). Validates type compatibility.
     */
    public function set(string $key, string $rawValue, string $updatedBy): SystemSetting
    {
        $setting = SystemSetting::where('key', $key)->firstOrFail();

        match ($setting->type) {
            'integer' => is_numeric($rawValue) ? null : throw ValidationException::withMessages(['value' => ['Value must be a valid integer.']]),
            'boolean' => in_array(strtolower($rawValue), ['true', 'false', '1', '0']) ? null : throw ValidationException::withMessages(['value' => ['Value must be true or false.']]),
            'json'    => (json_decode($rawValue) !== null) ? null : throw ValidationException::withMessages(['value' => ['Value must be valid JSON.']]),
            default   => null,
        };

        $setting->update([
            'value'      => $rawValue,
            'updated_by' => $updatedBy,
            'updated_at' => now(),
        ]);

        return $setting->fresh();
    }
}
