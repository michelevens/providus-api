<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug', 'name', 'short_name', 'category', 'region', 'parent_org',
        'stedi_id', 'states', 'market_share', 'avg_cred_days',
        'credentialing_url', 'cred_phone', 'cred_email', 'logo_slug', 'notes',
    ];

    protected $casts = ['states' => 'array'];

    public function plans(): HasMany { return $this->hasMany(PayerPlan::class); }
    public function applications(): HasMany { return $this->hasMany(Application::class); }
}
