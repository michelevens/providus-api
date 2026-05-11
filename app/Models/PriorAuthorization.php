<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriorAuthorization extends Model
{
    // BelongsToAgency adds the global TenantScope. Same defense-in-depth
    // rationale as PaymentLink / ClearinghouseConfig — every other tenant-
    // scoped model in the codebase has it, and a future
    // `PriorAuthorization::find($id)` from a junior dev / AI session would
    // cross tenants without manual filtering. Trait is a no-op on unauth
    // requests, so no public-route impact.
    use BelongsToAgency, HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'claim_id', 'patient_name',
        'patient_member_id', 'payer_name', 'authorization_number',
        'cpt_code', 'cpt_codes', 'units_authorized', 'units_used',
        'effective_date', 'expiration_date', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'units_authorized' => 'decimal:2',
        'units_used' => 'decimal:2',
        'effective_date' => 'date',
        'expiration_date' => 'date',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }
}
