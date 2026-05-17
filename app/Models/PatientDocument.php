<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientDocument extends Model
{
    use Auditable, BelongsToAgency;

    protected $fillable = [
        'agency_id', 'patient_key', 'document_type', 'document_name',
        'file_path', 'file_disk', 'mime_type', 'file_size', 'original_filename',
        'uploaded_by', 'status', 'received_date', 'expiration_date', 'notes',
    ];

    protected $casts = [
        'received_date'   => 'date',
        'expiration_date' => 'date',
        'file_size'       => 'integer',
    ];

    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    /**
     * Normalize a patient name to the storage key. Lowercased + trimmed
     * to match how V2 + the patient_notes / patient_statements tables
     * do identity matching against claims.patient_name.
     */
    public static function keyForPatient(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
