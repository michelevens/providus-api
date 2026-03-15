<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'invoice_number', 'type', 'status',
        'organization_id', 'client_name', 'client_email', 'client_address',
        'issue_date', 'due_date', 'subtotal', 'tax_rate', 'tax_amount',
        'discount_amount', 'total', 'paid_amount', 'balance_due',
        'notes', 'terms', 'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function recalculate(): void
    {
        $this->subtotal = $this->items()->sum('total');
        $this->tax_amount = round($this->subtotal * ($this->tax_rate / 100), 2);
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->paid_amount = $this->payments()->sum('amount');
        $this->balance_due = $this->total - $this->paid_amount;
        if ($this->balance_due <= 0 && $this->total > 0) $this->status = 'paid';
        elseif ($this->paid_amount > 0) $this->status = 'partial';
        $this->save();
    }
}
