<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiKey extends Model
{
    use Auditable, BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'name', 'key', 'secret_hash', 'permissions',
        'is_active', 'last_used_at', 'created_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['key', 'secret_hash'];

    /**
     * Drop hashed secret + low-signal heartbeat from audit logs.
     * The secret_hash is bcrypt — useless on its own and noisy in
     * the diff. last_used_at changes on every request, would flood
     * audit_logs.
     */
    protected array $auditExclude = ['secret_hash', 'last_used_at'];
}
