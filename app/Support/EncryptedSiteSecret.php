<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypt sensitive site_settings values at rest.
 * Legacy plaintext values are still readable and re-encrypted on next write.
 */
class EncryptedSiteSecret
{
    private const PREFIX = 'enc:v1:';

    public static function get(string $key): string
    {
        $raw = (string) SiteSetting::get($key, '');
        if ($raw === '') {
            return '';
        }

        if (!str_starts_with($raw, self::PREFIX)) {
            return $raw;
        }

        try {
            return Crypt::decryptString(substr($raw, strlen(self::PREFIX)));
        } catch (DecryptException) {
            return '';
        }
    }

    public static function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        SiteSetting::set($key, self::PREFIX . Crypt::encryptString($value));
    }

    public static function isEncrypted(string $raw): bool
    {
        return str_starts_with($raw, self::PREFIX);
    }
}
