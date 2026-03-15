<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicensingBoard extends Model
{
    protected $fillable = [
        'state', 'board_name', 'board_type', 'website_url',
        'verification_url', 'renewal_url', 'phone', 'email',
        'address', 'notes',
    ];
}
