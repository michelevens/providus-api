<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderNote extends Model
{
    use Auditable, BelongsToAgency;

    protected $fillable = [
        'agency_id', 'provider_id', 'body', 'pinned',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'pinned' => 'boolean',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
