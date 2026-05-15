<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailityEraSync extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'source', 'triggered_by',
        'from_date', 'to_date', 'cursor_at',
        'files_listed', 'files_imported', 'files_skipped', 'files_errored',
        'claims_posted', 'total_amount_posted',
        'error', 'status', 'completed_at',
    ];

    protected $casts = [
        'from_date'           => 'date',
        'to_date'             => 'date',
        'cursor_at'           => 'datetime',
        'completed_at'        => 'datetime',
        'total_amount_posted' => 'decimal:2',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(AvailityEraSyncFile::class, 'sync_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
