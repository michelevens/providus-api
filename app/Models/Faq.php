<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'category', 'question', 'answer',
        'sort_order', 'is_published', 'helpful_count',
    ];

    protected $casts = ['is_published' => 'boolean'];
}
