<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderReference extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'provider_id', 'reference_name', 'reference_title',
        'reference_organization', 'relationship', 'phone', 'email',
        'status', 'contacted_at', 'received_at', 'response_notes',
    ];

    protected $casts = [
        'contacted_at' => 'date',
        'received_at' => 'date',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
