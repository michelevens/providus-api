<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLink extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'provider_id', 'token', 'created_by',
        'expires_at', 'is_active', 'view_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'view_count' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function isValid(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
