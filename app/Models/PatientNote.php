<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientNote extends Model
{
    use Auditable, BelongsToAgency;

    protected $fillable = [
        'agency_id', 'patient_key', 'body', 'pinned',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'pinned' => 'boolean',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    /**
     * Normalize a patient name to the storage key.
     * Lowercased + trimmed to match how V2's PatientDetailPage
     * does identity matching against claims.patient_name.
     */
    public static function keyForPatient(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
