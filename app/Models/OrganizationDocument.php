<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDocument extends Model
{
    use BelongsToAgency, HasFactory;

    protected $fillable = [
        'agency_id', 'organization_id',
        'document_type', 'document_name',
        'file_path', 'file_disk', 'mime_type', 'file_size', 'original_filename',
        'uploaded_by', 'status',
        'received_date', 'expiration_date', 'notes',
        'document_request_id',
    ];

    protected $casts = [
        'received_date'   => 'date',
        'expiration_date' => 'date',
        'file_size'       => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }
}
