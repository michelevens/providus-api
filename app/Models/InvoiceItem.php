<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'invoice_id', 'service_catalog_id', 'description',
        'quantity', 'unit_price', 'total', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function serviceCatalog(): BelongsTo { return $this->belongsTo(ServiceCatalog::class); }

    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $item) {
            $item->total = round($item->quantity * $item->unit_price, 2);
        });
    }
}
