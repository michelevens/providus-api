<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriorAuthorization extends Model
{
    use HasFactory, SoftDeletes;

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
