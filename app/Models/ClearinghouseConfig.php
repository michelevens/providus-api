<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ClearinghouseConfig extends Model
{
    // BelongsToAgency adds a global TenantScope so any future
    // `ClearinghouseConfig::find($id)` returns null when the row belongs
    // to another agency. Defense-in-depth — the controller already does
    // manual `where('agency_id', ...)` filtering, but every model that
    // holds tenant-scoped secrets should also have the trait.
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'clearinghouse_name', 'client_id', 'client_secret_encrypted',
        'submitter_id', 'organization_name', 'last_pulled_at', 'metadata', 'connected',
    ];

    // SECURITY: hide both the ciphertext column AND the historical
    // `client_secret` virtual attribute name. If anything ever calls
    // `$cfg->toArray()` / `json_encode($cfg)` / `response()->json($cfg)`,
    // these fields will NOT be emitted. The plaintext is only available
    // via the explicit `clientSecret()` method below.
    protected $hidden = ['client_secret_encrypted', 'client_secret'];

    protected $casts = [
        'metadata' => 'array',
        'connected' => 'boolean',
        'last_pulled_at' => 'datetime',
    ];

    /**
     * Decrypt the stored secret. Use this explicit method instead of an
     * accessor — accessors fire on every magic property read (including
     * serialization paths, `dd()`, logging, telescope), which created an
     * easy footgun where one careless `response()->json($cfg)` would have
     * leaked live Availity OAuth secrets. Callers must opt in.
     */
    public function clientSecret(): ?string
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
