<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * X12 Remittance Advice Remark Code lookup.
 * Used by Era835Importer to detect appeal windows + documentation requests.
 */
class RarcCode extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'description', 'triggers_appeal_window', 'indicates_documentation_request'];

    protected $casts = [
        'triggers_appeal_window' => 'boolean',
        'indicates_documentation_request' => 'boolean',
    ];
}
