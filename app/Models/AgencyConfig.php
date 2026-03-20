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
        'elig_monthly_limit', 'waves',
    ];

    protected $hidden = ['stedi_api_key', 'caqh_password'];

    protected function casts(): array
    {
        return [
            'stedi_api_key' => 'encrypted',
            'caqh_password' => 'encrypted',
            'waves' => 'array',
        ];
    }

    /**
     * Get wave definitions with defaults.
     * Returns array like: [{ id: 1, label: "Wave 1", color: "#0891b2" }, ...]
     */
    public function getWaveDefinitions(): array
    {
        if (!empty($this->waves)) {
            return $this->waves;
        }

        // Default groups
        return [
            ['id' => 1, 'label' => 'Group 1', 'short' => 'G1', 'color' => '#0891b2'],
            ['id' => 2, 'label' => 'Group 2', 'short' => 'G2', 'color' => '#3b82f6'],
            ['id' => 3, 'label' => 'Group 3', 'short' => 'G3', 'color' => '#6b7280'],
        ];
    }
}
