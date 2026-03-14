<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class EligibilityCheck extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected $fillable = [
        'agency_id', 'insurance', 'member_id', 'patient_dob',
        'patient_first_name', 'patient_last_name', 'stedi_response',
        'status', 'plan_name', 'network', 'copay', 'coinsurance',
        'deductible', 'oop_max', 'error_message',
    ];

    protected $casts = [
        'patient_dob' => 'date',
        'stedi_response' => 'array',
        'copay' => 'decimal:2',
        'deductible' => 'decimal:2',
        'oop_max' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
