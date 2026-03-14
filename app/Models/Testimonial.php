<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected $fillable = [
        'agency_id', 'token', 'patient_email', 'patient_name',
        'display_name', 'rating', 'text', 'status',
        'requested_at', 'submitted_at', 'reviewed_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'requested_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function scopeApproved($query) { return $query->where('status', 'approved'); }
}
