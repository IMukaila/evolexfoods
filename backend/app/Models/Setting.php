<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    public const SECRETS = ['mail_password', 'sms_api_key', 'resend_api_key'];

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a typed setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return $setting->castValue();
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, ?string $group = 'general', ?string $type = null, ?string $description = null): self
    {
        if ($type === null) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'json',
                default => 'string',
            };
        }

        $serializedValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        if (in_array($key, self::SECRETS, true)) {
            $serializedValue = Crypt::encryptString((string) $value);
            $type = 'encrypted';
        }

        return static::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group ?? 'general',
                'value' => $serializedValue,
                'type' => $type,
                'description' => $description,
            ]
        );
    }

    /**
     * Get all settings grouped by their group.
     */
    public static function getAllGrouped(): array
    {
        $settings = static::all();
        $grouped = [];

        foreach ($settings as $setting) {
            $group = $setting->group ?: 'general';
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            if (in_array($setting->key, self::SECRETS, true)) {
                $grouped[$group][$setting->key] = '';
                $grouped[$group][$setting->key.'_configured'] = !empty($setting->castValue());
            } else {
                $grouped[$group][$setting->key] = $setting->castValue();
            }
        }

        return $grouped;
    }

    /**
     * Cast the stored string value to its typed equivalent.
     */
    public function castValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'encrypted' => Crypt::decryptString($this->value),
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
