<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agency_id', 'user_id', 'impersonator_user_id', 'user_email', 'action',
        'auditable_type', 'auditable_id', 'old_values', 'new_values',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // The superadmin operating the session, when this entry was
    // written during impersonation. Null when the change was made
    // directly by user_id (no impersonation).
    public function impersonator()
    {
        return $this->belongsTo(User::class, 'impersonator_user_id');
    }
}
