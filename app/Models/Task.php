<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'title', 'category', 'priority', 'due_date',
        'linked_application_id', 'recurrence', 'notes',
        'is_completed', 'completed_at', 'assigned_to',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function application(): BelongsTo { return $this->belongsTo(Application::class, 'linked_application_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }

    public function scopeIncomplete($query) { return $query->where('is_completed', false); }
    public function scopeOverdue($query) { return $query->where('is_completed', false)->where('due_date', '<', now()); }
}
