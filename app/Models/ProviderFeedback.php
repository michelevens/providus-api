<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderFeedback extends Model
{
    use BelongsToAgency;

    protected $table = 'provider_feedback';

    protected $fillable = [
        'agency_id', 'provider_id', 'provider_name', 'claim_id', 'denial_id',
        'feedback_type', 'cpt_code', 'payer_name', 'issue', 'recommendation',
        'status', 'sent_date', 'provider_response', 'created_by',
    ];

    protected $casts = ['sent_date' => 'date'];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function denial(): BelongsTo { return $this->belongsTo(ClaimDenial::class, 'denial_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
