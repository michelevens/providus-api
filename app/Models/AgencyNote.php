<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Operator-only CRM note on a tenant. NOT TenantScope-aware — these
// are written by superadmins and only readable via /admin/* endpoints.
// The agency_id field is the SUBJECT of the note (the tenant being
// noted on), not the tenant who owns the note.
class AgencyNote extends Model
{
    protected $fillable = [
        'agency_id', 'author_user_id', 'author_email',
        'body', 'tag', 'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
