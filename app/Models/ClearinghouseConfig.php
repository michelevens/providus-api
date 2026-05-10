<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ClearinghouseConfig extends Model
{
    protected $fillable = [
        'agency_id', 'clearinghouse_name', 'client_id', 'client_secret_encrypted',
        'submitter_id', 'organization_name', 'last_pulled_at', 'metadata', 'connected',
    ];

    protected $casts = [
        'metadata' => 'array',
        'connected' => 'boolean',
        'last_pulled_at' => 'datetime',
    ];

    /** Plain-text accessor for the encrypted secret. Setters use ::setSecret(). */
    public function getClientSecretAttribute(): ?string
    {
        if (!$this->client_secret_encrypted) return null;
        try { return Crypt::decryptString($this->client_secret_encrypted); }
        catch (\Throwable $e) { return null; }
    }

    public function setSecret(?string $plain): void
    {
        $this->client_secret_encrypted = $plain ? Crypt::encryptString($plain) : null;
    }
}
