<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contract extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'contract_number', 'status',
        'organization_id', 'provider_id',
        'client_name', 'client_email', 'client_address',
        'title', 'description', 'token',
        'effective_date', 'expiration_date',
        'auto_renew', 'renewal_terms',
        'subtotal', 'tax_rate', 'tax_amount', 'discount_amount', 'total',
        'billing_frequency', 'payment_terms',
        'terms_and_conditions', 'notes',
        'sent_at', 'viewed_at',
        'accepted_at', 'accepted_by_name', 'accepted_by_email', 'accepted_ip',
        'terminated_at', 'terminated_reason',
        'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiration_date' => 'date',
        'auto_renew' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function recalculate(): void
    {
        $subtotal = $this->items()->sum('total');
        $taxAmount = round($subtotal * ($this->tax_rate / 100), 2);
        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount - ($this->discount_amount ?? 0),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(ContractItem::class); }
}
