<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class AgencyConfig extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'stedi_api_key', 'stedi_npi', 'stedi_org_name',
        'caqh_org_id', 'caqh_username', 'caqh_password', 'caqh_environment',
        'google_calendar_id', 'notification_email', 'provider_name',
        'elig_monthly_limit',
    ];

    protected $hidden = ['stedi_api_key', 'caqh_password'];

    protected function casts(): array
    {
        return [
            'stedi_api_key' => 'encrypted',
            'caqh_password' => 'encrypted',
        ];
    }
}
