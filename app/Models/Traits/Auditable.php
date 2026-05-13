<?php

namespace App\Models\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $new = $model->filterAuditFields($model->getAttributes());
            $model->logAudit('created', [], $new);
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            // Drop sensitive / high-noise fields (passwords, secrets,
            // last_login_at heartbeats, etc.) before computing the diff.
            $dirty = $model->filterAuditFields($dirty);
            if (empty($dirty)) return;
            $old = array_intersect_key($model->getOriginal(), $dirty);
            $model->logAudit('updated', $old, $dirty);
        });

        static::deleted(function ($model) {
            $old = $model->filterAuditFields($model->getOriginal());
            $model->logAudit('deleted', $old, []);
        });
    }

    /**
     * Strip fields that shouldn't land in audit_logs.
     *
     * Per-model exclusion: a model can define
     *
     *     protected array $auditExclude = ['password', 'remember_token'];
     *
     * to drop sensitive fields. Use for password hashes, encrypted
     * secrets, 2FA backup codes, and heartbeat columns like
     * last_login_at that would otherwise spam the log.
     */
    protected function filterAuditFields(array $values): array
    {
        $exclude = property_exists($this, 'auditExclude') && is_array($this->auditExclude)
            ? $this->auditExclude
            : [];
        if (empty($exclude)) return $values;
        return array_diff_key($values, array_flip($exclude));
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
