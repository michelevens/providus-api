<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelehealthPolicy extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'state', 'practice_authority', 'cpa_notes', 'telehealth_parity',
        'controlled_substances', 'cs_notes', 'consent_required', 'consent_notes',
        'in_person_required', 'in_person_notes', 'originating_site',
        'aprn_compact', 'nlc_member', 'medicaid_telehealth', 'medicaid_notes',
        'audio_only', 'cross_state_license', 'ryan_haight_exemption',
        'readiness_score', 'notes', 'last_updated',
    ];

    protected $casts = [
        'telehealth_parity' => 'boolean',
        'in_person_required' => 'boolean',
        'aprn_compact' => 'boolean',
        'nlc_member' => 'boolean',
        'audio_only' => 'boolean',
        'ryan_haight_exemption' => 'boolean',
        'last_updated' => 'date',
    ];
}
