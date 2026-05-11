<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    // BelongsToAgency adds the global TenantScope. The scope is a no-op on
    // unauthenticated requests (the public /payments/status/{token} route),
    // so the patient-facing token lookup still works — but any future
    // authenticated `PaymentLink::find($id)` is automatically tenant-scoped.
    // Defense-in-depth so a junior dev or AI session can't accidentally
    // skip the manual `where('agency_id', ...)` controllers do today.
    use BelongsToAgency, HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'target_type', 'target_id',
        'patient_name', 'patient_email', 'amount', 'currency',
        'public_token', 'stripe_session_id', 'stripe_payment_intent_id',
        'checkout_url', 'status', 'paid_at', 'expires_at', 'metadata', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentLink $link) {
            if (empty($link->public_token)) {
                $link->public_token = Str::random(40);
            }
        });
    }
}
