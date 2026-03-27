<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppealTemplate extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'name', 'denial_category', 'template_type', 'subject', 'body',
        'required_attachments', 'is_default', 'created_by',
    ];

    protected $casts = [
        'required_attachments' => 'array', 'is_default' => 'boolean',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
