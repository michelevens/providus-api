<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * X12 Claim Adjustment Reason Code lookup.
 * Used by Era835Importer to enrich denial rows with category + description.
 */
class CarcCode extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'description', 'category', 'typical_group_codes'];
}
