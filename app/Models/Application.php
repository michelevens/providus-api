<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use BelongsToAgency, SoftDeletes; // Auditable temporarily removed to debug 503

    const STATUSES = [
        'not_started', 'submitted', 'in_review', 'pending_info',
        'approved', 'denied', 'withdrawn',
    ];

    const VALID_TRANSITIONS = [
        'not_started' => ['submitted', 'withdrawn'],
        'submitted' => ['in_review', 'pending_info', 'approved', 'denied', 'withdrawn'],
        'in_review' => ['pending_info', 'approved', 'denied', 'withdrawn'],
        'pending_info' => ['in_review', 'submitted', 'approved', 'denied', 'withdrawn'],
        'approved' => ['withdrawn'],
        'denied' => ['submitted', 'not_started'],
        'withdrawn' => ['not_started', 'submitted'],
    ];

    protected $fillable = [
        'agency_id', 'legacy_id', 'provider_id', 'organization_id',
        'payer_id', 'payer_plan_id', 'payer_name', 'state', 'type', 'wave',
        'status', 'portal_url', 'application_ref', 'enrollment_id',
        'submitted_date', 'received_date', 'effective_date', 'denial_reason',
        'est_monthly_revenue', 'payer_contact_name', 'payer_contact_phone',
        'payer_contact_email', 'notes', 'tags', 'document_checklist',
    ];

    protected $casts = [
        'submitted_date' => 'date',
        'received_date' => 'date',
        'effective_date' => 'date',
        'est_monthly_revenue' => 'decimal:2',
        'tags' => 'array',
        'document_checklist' => 'array',
        'wave' => 'integer',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function payer(): BelongsTo { return $this->belongsTo(Payer::class); }
    public function payerPlan(): BelongsTo { return $this->belongsTo(PayerPlan::class); }
    public function followups(): HasMany { return $this->hasMany(Followup::class); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class, 'linked_application_id'); }

    public function canTransitionTo(string $status): bool
    {
        $allowed = self::VALID_TRANSITIONS[$this->status] ?? [];
        return in_array($status, $allowed);
    }
}
