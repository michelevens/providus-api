<?php

namespace App\Models\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logAudit('created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) return;
            $old = array_intersect_key($model->getOriginal(), $dirty);
            $model->logAudit('updated', $old, $dirty);
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getOriginal(), []);
        });
    }

    protected function logAudit(string $action, array $old, array $new): void
    {
        try {
            $user = auth()->user();
            $request = request();

            // Impersonation detection: if the operator's Sanctum token
            // carries an `impersonate:<agencyId>` ability for a tenant
            // different from their home agency, mark this entry as
            // impersonator-driven so compliance can trace the
            // who-actually-acted question separately from who-owns-the-data.
            $impersonatorUserId = null;
            if ($user && method_exists($user, 'effectiveAgencyId')) {
                $effective = $user->effectiveAgencyId($request);
                if ($effective !== null && $effective !== $user->agency_id) {
                    $impersonatorUserId = $user->id;
                }
            }

            AuditLog::create([
                'agency_id' => $this->agency_id ?? $user?->agency_id,
                'user_id' => $user?->id,
                'impersonator_user_id' => $impersonatorUserId,
                'user_email' => $user?->email,
                'action' => $action,
                'auditable_type' => static::class,
                'auditable_id' => $this->getKey(),
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
