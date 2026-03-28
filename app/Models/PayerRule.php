<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayerRule extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'payer_name', 'timely_filing_days', 'appeal_filing_days', 'corrected_claim_days',
        'portal_url', 'provider_phone', 'claims_address', 'appeals_address', 'appeals_fax',
        'electronic_payer_id', 'auth_required_cpts', 'bundling_rules', 'medical_necessity_notes',
        'common_denial_reasons', 'credentialing_requirements', 'reimbursement_notes', 'billing_tips',
        'policy_documents', 'created_by',
    ];

    protected $casts = [
        'auth_required_cpts' => 'array',
        'bundling_rules' => 'array',
        'medical_necessity_notes' => 'array',
        'common_denial_reasons' => 'array',
        'credentialing_requirements' => 'array',
        'policy_documents' => 'array',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
