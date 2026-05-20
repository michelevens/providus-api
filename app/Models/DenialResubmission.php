<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per appeal/resubmission attempt against a ClaimDenial.
 *
 * The ClaimDenial row already carries the SUMMARY state
 * (appeal_level, appeal_submitted_date, recovered_amount). This table
 * preserves the FULL history — each appeal attempt with its own
 * outcome, attachments, and payer reference numbers.
 *
 * Workflow: operator marks a denial as resubmitted via the UI,
 * which creates a row here AND updates the parent denial's summary
 * fields. When the payer responds, the row is updated with
 * decision_date + recovered_amount + status.
 */
class DenialResubmission extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id', 'claim_denial_id', 'attempt_number',
        'status',
        'submitted_date', 'submission_method', 'submission_notes',
        'resubmitted_claim_number', 'payer_appeal_id',
        'decision_date', 'recovered_amount', 'outcome_notes',
        'attachments',
        'created_by',
    ];

    protected $casts = [
        'submitted_date'    => 'date',
        'decision_date'     => 'date',
        'recovered_amount'  => 'decimal:2',
        'attachments'       => 'array',
        'attempt_number'    => 'integer',
    ];

    public function denial(): BelongsTo
    {
        return $this->belongsTo(ClaimDenial::class, 'claim_denial_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
