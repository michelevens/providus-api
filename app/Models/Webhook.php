<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Webhook extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'url', 'secret', 'events',
        'is_active', 'last_triggered_at', 'failure_count', 'created_by',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'failure_count' => 'integer',
    ];

    protected $hidden = ['secret'];

    /**
     * Encrypt the secret at rest. Falls back to the raw value on read for legacy rows
     * written before encryption was introduced.
     */
    protected function secret(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') return $value;
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    return $value; // legacy plaintext
                }
            },
            set: fn($value) => $value === null ? null : Crypt::encryptString($value),
        );
    }
}
