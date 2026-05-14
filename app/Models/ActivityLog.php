<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected $fillable = [
        'agency_id',
        // Polymorphic subject — preferred way to link this entry to its owner.
        'subject_type', 'subject_id',
        // Legacy: still populated when subject_type='application' so V1
        // reporting that groups on application_id keeps working.
        'application_id',
        'type', 'logged_date',
        'contact_name', 'contact_phone', 'ref_number',
        'outcome', 'next_step', 'status_from', 'status_to', 'created_by',
    ];

    protected $casts = ['logged_date' => 'date', 'created_at' => 'datetime'];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
