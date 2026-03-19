<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingOpportunity extends Model
{
    protected $fillable = [
        'external_id', 'source', 'title', 'description', 'agency_source',
        'cfda_number', 'funding_type', 'amount_min', 'amount_max', 'amount_display',
        'open_date', 'close_date', 'status', 'eligibility', 'url',
        'category', 'keywords', 'raw_data', 'scraped_at',
    ];

    protected $casts = [
        'amount_min' => 'decimal:2',
        'amount_max' => 'decimal:2',
        'open_date' => 'date',
        'close_date' => 'date',
        'keywords' => 'array',
        'raw_data' => 'array',
        'scraped_at' => 'datetime',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(FundingApplication::class);
    }

    public function isExpiringSoon(int $days = 14): bool
    {
        return $this->close_date && $this->close_date->isBetween(now(), now()->addDays($days));
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && (!$this->close_date || $this->close_date->isFuture());
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('close_date')->orWhere('close_date', '>=', now()));
    }

    public function scopeMentalHealth($query)
    {
        return $query->where(function ($q) {
            $q->where('category', 'like', '%mental%')
              ->orWhere('category', 'like', '%behavioral%')
              ->orWhere('category', 'like', '%substance%')
              ->orWhere('title', 'like', '%mental health%')
              ->orWhere('title', 'like', '%behavioral health%')
              ->orWhere('title', 'like', '%substance%')
              ->orWhere('title', 'like', '%psychiatr%')
              ->orWhere('title', 'like', '%SAMHSA%')
              ->orWhere('agency_source', 'like', '%SAMHSA%');
        });
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }
}
