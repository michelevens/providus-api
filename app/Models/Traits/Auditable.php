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

            AuditLog::create([
                'agency_id' => $this->agency_id ?? $user?->agency_id,
                'user_id' => $user?->id,
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
