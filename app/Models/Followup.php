<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Followup extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'application_id', 'type', 'due_date', 'completed_date',
        'method', 'contact_name', 'contact_phone', 'contact_email',
        'outcome', 'next_action',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_date' => 'date',
    ];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }

    public function isOverdue(): bool
    {
        return !$this->completed_date && $this->due_date->isPast();
    }

    public function scopeOverdue($query) { return $query->whereNull('completed_date')->where('due_date', '<', now()); }
    public function scopePending($query) { return $query->whereNull('completed_date'); }
    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->whereNull('completed_date')
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }
}
