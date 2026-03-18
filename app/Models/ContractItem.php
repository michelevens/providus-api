<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractItem extends Model
{
    protected $fillable = [
        'contract_id', 'service_catalog_id', 'description',
        'quantity', 'unit_price', 'total', 'frequency', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($item) {
            $item->total = round($item->quantity * $item->unit_price, 2);
        });
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function serviceCatalog(): BelongsTo { return $this->belongsTo(ServiceCatalog::class); }
}
