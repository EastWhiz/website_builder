<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Return the CRM base URL for the current environment (production vs dev).
     * Uses admin-configured crm_mode and crm_url_production / crm_url_dev.
     */
    public static function getCrmBaseUrl(): string
    {
        $mode = static::get('crm_mode', 'production');
        $url = $mode === 'dev'
            ? (static::get('crm_url_dev') ?: static::get('crm_url_production', 'https://crm.diy'))
            : (static::get('crm_url_production') ?: 'https://crm.diy');
        $url = trim((string) $url);

        return $url !== '' ? rtrim($url, '/') : 'https://crm.diy';
    }

    /**
     * Whether to verify SSL when calling the CRM API.
     * Set to false for dev servers with self-signed or expired certificates.
     */
    public static function getCrmVerifySsl(): bool
    {
        $v = static::get('crm_verify_ssl', '1');

        return $v === '1' || $v === 'true' || $v === true;
    }
}
