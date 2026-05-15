<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailityEraSyncFile extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'sync_id', 'agency_id', 'file_id', 'received_at',
        'payer_name', 'size_bytes', 'claim_count',
        'status', 'import_result', 'error',
    ];

    protected $casts = [
        'received_at'   => 'datetime',
        'import_result' => 'array',
    ];

    public function sync(): BelongsTo
    {
        return $this->belongsTo(AvailityEraSync::class, 'sync_id');
    }
}
