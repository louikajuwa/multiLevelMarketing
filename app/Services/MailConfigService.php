<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Applies admin-managed mail settings over the .env defaults at runtime.
 *
 * IMPORTANT: this is invoked from AppServiceProvider::boot(), which runs for BOTH
 * web requests AND queue workers — so queued mail uses the admin's DB credentials,
 * not a stale .env value. (The earlier plan's "web middleware" approach would have
 * left the queue worker on .env — this fixes that.)
 */
class MailConfigService
{
    public function apply(): void
    {
        if (! $this->settingsTableReady()) {
            return; // migrations not run yet / during install
        }

        $map = [
            'mail_host' => 'mail.mailers.smtp.host',
            'mail_port' => 'mail.mailers.smtp.port',
            'mail_username' => 'mail.mailers.smtp.username',
            'mail_encryption' => 'mail.mailers.smtp.scheme',
            'mail_from_address' => 'mail.from.address',
            'mail_from_name' => 'mail.from.name',
        ];

        $settings = Setting::whereIn('key', array_merge(array_keys($map), ['mail_password']))->pluck('value', 'key');

        foreach ($map as $key => $configPath) {
            $val = $settings[$key] ?? null;
            if ($val !== null && $val !== '') {
                config([$configPath => $val]);
            }
        }

        $password = $settings['mail_password'] ?? null;
        if ($password) {
            config(['mail.mailers.smtp.password' => $this->maybeDecrypt($password)]);
        }
    }

    private function maybeDecrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value; // stored in plain (e.g., seeded) — use as-is
        }
    }

    private function settingsTableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
