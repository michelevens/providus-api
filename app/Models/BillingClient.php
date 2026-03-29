<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingClient extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'organization_id', 'organization_name',
        'contact_name', 'contact_email', 'contact_phone',
        'billing_platform', 'monthly_fee', 'fee_structure',
        'payment_mode', 'agency_fee_percent',
        'status', 'start_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'start_date' => 'date',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function tasks(): HasMany { return $this->hasMany(BillingTask::class); }
    public function activities(): HasMany { return $this->hasMany(BillingActivity::class); }
    public function financials(): HasMany { return $this->hasMany(BillingFinancial::class); }
}
