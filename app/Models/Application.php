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
    use Auditable, BelongsToAgency, SoftDeletes;

    const STATUSES = [
        'not_started', 'submitted', 'in_review', 'pending_info',
        'approved', 'denied', 'withdrawn',
    ];

    // Application status state machine. Must stay in sync with V2's
    // APPLICATION_STATUSES list in v2/src/lib/statuses.ts; otherwise the
    // dropdown shows statuses the backend won't accept (422 "Cannot
    // transition from X to Y"). Empty array = terminal status.
    //
    // Flow narrative:
    //   planned → gathering_docs → submitted → in_review → approved → credentialed
    //   At any stage operator can go on_hold, withdraw, or be denied.
    //   pending_info loops back to in_review when the payer responds.
    //   on_hold and withdrawn can be revived back into the pipeline.
    //   'new' is a legacy alias for 'gathering_docs' (kept for old data).
    //   'not_started' is a legacy alias for 'planned' (kept for old data).
    const VALID_TRANSITIONS = [
        'planned'        => ['new', 'gathering_docs', 'submitted', 'on_hold', 'withdrawn'],
        'new'            => ['gathering_docs', 'submitted', 'pending_info', 'in_review', 'on_hold', 'withdrawn', 'denied'],
        'gathering_docs' => ['submitted', 'pending_info', 'on_hold', 'withdrawn'],
        'submitted'      => ['in_review', 'pending_info', 'approved', 'denied', 'on_hold', 'withdrawn'],
        'in_review'      => ['pending_info', 'approved', 'denied', 'on_hold', 'withdrawn'],
        'pending_info'   => ['in_review', 'submitted', 'gathering_docs', 'approved', 'denied', 'on_hold', 'withdrawn'],
        'approved'       => ['credentialed', 'withdrawn'],
        'credentialed'   => ['withdrawn'],
        'denied'         => ['submitted', 'gathering_docs', 'new', 'planned', 'withdrawn'],
        'on_hold'        => ['planned', 'new', 'gathering_docs', 'submitted', 'in_review', 'withdrawn'],
        'withdrawn'      => ['planned', 'new', 'gathering_docs', 'submitted'],
        // Legacy alias preserved so historical rows can move forward.
        'not_started'    => ['planned', 'new', 'gathering_docs', 'submitted', 'withdrawn'],
    ];

    protected $fillable = [
        'agency_id', 'legacy_id', 'provider_id', 'organization_id',
        'payer_id', 'payer_plan_id', 'payer_name', 'state', 'type', 'wave',
        'status', 'portal_url', 'application_ref', 'enrollment_id',
        'submitted_date', 'received_date', 'effective_date', 'denial_reason',
        'est_monthly_revenue', 'payer_contact_name', 'payer_contact_phone',
        'payer_contact_email', 'notes', 'tags', 'document_checklist',
        'assigned_to', 'facility_id',
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
