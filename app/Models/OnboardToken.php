<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardToken extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected $fillable = [
        'agency_id', 'token', 'provider_email', 'provider_name',
        'role', 'expires_at', 'used_at', 'used_by_user_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function usedBy(): BelongsTo { return $this->belongsTo(User::class, 'used_by_user_id'); }

    public function isExpired(): bool { return $this->expires_at->isPast(); }
    public function isUsed(): bool { return $this->used_at !== null; }
    public function isValid(): bool { return !$this->isExpired() && !$this->isUsed(); }
}
