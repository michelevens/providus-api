<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-clearinghouse EDI payer ID for a given Payer.
 *
 * UnitedHealthcare is "87726" on Availity but may be "87716" on
 * Change Healthcare. This pairs a clearinghouse + EDI payer ID with
 * the canonical Payer record.
 *
 * Exactly one row per payer should carry is_primary=true. The
 * application enforces this at write time (PayersController); no
 * partial unique index in Postgres because is_primary changes can
 * happen mid-transaction without it being a violation.
 */
class PayerEdiCode extends Model
{
    protected $fillable = [
        'payer_id', 'clearinghouse', 'edi_payer_id', 'is_primary', 'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }
}
