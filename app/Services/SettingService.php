<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingService
{
    private const CACHE_KEY = 'app_settings';

    /** @return array<string,mixed> raw stored values (encrypted ones stay encrypted) */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
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
