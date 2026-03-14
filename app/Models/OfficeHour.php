<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class OfficeHour extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected $fillable = [
        'agency_id', 'day_of_week', 'start_hour', 'end_hour', 'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_hour' => 'decimal:2',
        'end_hour' => 'decimal:2',
        'is_closed' => 'boolean',
    ];
}
