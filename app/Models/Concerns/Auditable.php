<?php

namespace App\Models\Concerns;

use App\Services\AuditService;

/**
 * Writes an audit_logs row on create / update / delete of the model,
 * capturing changed values + acting user + IP + browser.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => app(AuditService::class)->log('created', $m, null, $m->getAttributes()));
        static::updated(fn ($m) => app(AuditService::class)->log('updated', $m, $m->getOriginal(), $m->getChanges()));
        static::deleted(fn ($m) => app(AuditService::class)->log('deleted', $m, $m->getOriginal(), null));
    }
}
