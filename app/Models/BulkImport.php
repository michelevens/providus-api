<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkImport extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'import_type', 'file_name', 'status',
        'total_rows', 'processed_rows', 'success_count', 'error_count',
        'skip_count', 'column_mapping', 'errors', 'preview_data',
        'created_by', 'completed_at',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'errors' => 'array',
        'preview_data' => 'array',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
