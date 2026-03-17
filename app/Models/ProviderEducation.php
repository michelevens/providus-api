<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderEducation extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $table = 'provider_education';

    protected $fillable = [
        'agency_id', 'provider_id', 'institution_name', 'degree',
        'field_of_study', 'education_type', 'start_date', 'end_date',
        'graduation_date', 'is_completed', 'is_verified', 'verified_at',
        'verification_source', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'graduation_date' => 'date',
        'is_completed' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
