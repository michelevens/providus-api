<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDocument extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'provider_id', 'document_type', 'document_name',
        'file_url', 'status', 'received_date', 'expiration_date',
        'request_attempts', 'last_requested_at', 'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
        'expiration_date' => 'date',
        'last_requested_at' => 'date',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
