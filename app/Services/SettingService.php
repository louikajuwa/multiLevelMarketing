<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingService
{
    private const CACHE_KEY = 'app_settings';

    /** @return array<string,mixed> cached plain values (encrypted rows are never cached) */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('is_encrypted', false)->pluck('value', 'key')->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return $all[$key] ?? $default;
        }

        // Encrypted rows bypass the cache: read fresh and decrypt.
        $row = Setting::where('key', $key)->where('is_encrypted', true)->first();
        if ($row && $row->value) {
            try {
                return Crypt::decryptString($row->value);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $default;
    }

    public function set(string $key, mixed $value, bool $encrypt = false): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => ($encrypt && $value) ? Crypt::encryptString((string) $value) : $value, 'is_encrypted' => $encrypt],
        );
        $this->flush();
    }

    /** @param array<string,array{value:mixed,encrypt?:bool}> $pairs */
    public function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $opt) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => (! empty($opt['encrypt']) && $opt['value']) ? Crypt::encryptString((string) $opt['value']) : $opt['value'],
                    'is_encrypted' => ! empty($opt['encrypt']),
                ],
            );
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
