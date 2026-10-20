<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

/**
 * Bridges Laravel -> Node Socket.IO via Redis pub/sub, and issues the short-lived
 * HMAC handshake token the socket server verifies (see socket-server/index.js).
 */
class RealtimeService
{
    /** Publish an event to one or more rooms. Best-effort (never breaks the request). */
    public function publish(array $rooms, string $event, array $payload = []): void
    {
        if (empty($rooms)) {
            return;
        }
        try {
            Redis::connection('realtime')->publish($this->channel(), json_encode([
                'event' => $event, 'rooms' => array_values($rooms), 'payload' => $payload,
            ]));
        } catch (\Throwable) {
            // realtime is an enhancement — swallow if redis is unreachable
        }
    }

    /**
     * Build a short-lived token (rooms authorized server-side from live data).
     *
     * @return array{token:string,rooms:string[]}
     */
    public function tokenFor(User $user): array
    {
        $rooms = ['user.'.$user->id];
        if ($user->partner) {
            $rooms[] = 'partner.'.$user->partner->id;
        }
        if ($user->isStaff()) {
            $rooms[] = 'admin';
        }

        $body = $this->b64url(json_encode(['uid' => $user->id, 'rooms' => $rooms, 'exp' => time() + 300]));
        $sig = hash_hmac('sha256', $body, $this->secret());

        return ['token' => $body.'.'.$sig, 'rooms' => $rooms];
    }

    private function b64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public function isConfigured(): bool
    {
        return $this->secret() !== '';
    }

    private function secret(): string
    {
        return (string) config('realtime.secret', '');
    }

    private function channel(): string
    {
        return (string) config('realtime.channel', 'mlm.events');
    }
}
