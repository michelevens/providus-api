<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tokenized share link for a service-line business plan PDF.
 *
 * BelongsToAgency adds the TenantScope so an authenticated session
 * can't read another agency's links. The public token endpoint is
 * unauthenticated — it disables the scope explicitly with
 * ::withoutGlobalScope().
 */
class ServiceLineShareLink extends Model
{
    use BelongsToAgency, HasFactory;

    protected $fillable = [
        'agency_id', 'sender_user_id', 'service_line_id', 'service_line_name',
        'organization_id', 'recipient_email', 'recipient_name', 'message',
        'public_token', 'r2_key', 'original_filename', 'file_size', 'file_disk',
        'expires_at', 'view_count', 'last_viewed_at', 'last_viewed_ip', 'email_sent_at',
    ];

    protected $casts = [
        'expires_at'     => 'datetime',
        'last_viewed_at' => 'datetime',
        'email_sent_at'  => 'datetime',
        'view_count'     => 'integer',
        'file_size'      => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ServiceLineShareLink $link) {
            if (empty($link->public_token)) {
                $link->public_token = Str::random(40);
            }
        });
    }

    public function isExpired(): bool
    {
        // Hard cap: links older than 1 year are dead regardless of
        // expires_at. Defense-in-depth against forgotten links.
        if ($this->created_at && $this->created_at->lt(now()->subYear())) {
            return true;
        }
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
