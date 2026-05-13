<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'agency_id', 'email', 'event_type', 'metadata',
        'impersonator_user_id', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function impersonator(): BelongsTo { return $this->belongsTo(User::class, 'impersonator_user_id'); }
    public function agency(): BelongsTo { return $this->belongsTo(Agency::class); }
}
