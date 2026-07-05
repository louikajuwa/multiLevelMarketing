<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public function log(string $action, Model $model, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $this->clean($old),
            'new_values' => $this->clean($new),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    private function clean(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return collect($data)->except(self::HIDDEN)->all();
    }
}
