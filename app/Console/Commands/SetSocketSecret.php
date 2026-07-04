<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates the shared HMAC secret used to sign Socket.IO handshake tokens.
 * Laravel signs realtime tokens with this secret; the Node socket-server verifies
 * them with the same value (SOCKET_SECRET in .env, passed to the socket container).
 */
class SetSocketSecret extends Command
{
    protected $signature = 'mlm:socket-secret {--force : Overwrite an existing secret}';

    protected $description = 'Generate and store the SOCKET_SECRET (shared HMAC key for realtime tokens)';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            $this->error('.env not found.');

            return self::FAILURE;
        }

        $contents = file_get_contents($envPath);
        $current = $this->currentValue($contents);

        if ($current !== '' && ! $this->option('force')) {
            $this->info('SOCKET_SECRET already set (use --force to regenerate).');

            return self::SUCCESS;
        }

        $secret = bin2hex(random_bytes(32));

        if (preg_match('/^SOCKET_SECRET=.*$/m', $contents)) {
            $contents = preg_replace('/^SOCKET_SECRET=.*$/m', 'SOCKET_SECRET='.$secret, $contents);
        } else {
            $contents .= PHP_EOL.'SOCKET_SECRET='.$secret.PHP_EOL;
        }

        file_put_contents($envPath, $contents);
        $this->info('SOCKET_SECRET set successfully.');

        return self::SUCCESS;
    }

    private function currentValue(string $contents): string
    {
        if (preg_match('/^SOCKET_SECRET=(.*)$/m', $contents, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
