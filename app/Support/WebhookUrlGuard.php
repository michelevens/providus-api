<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * SSRF guard for outbound webhook URLs. Rejects hosts that resolve to
 * loopback / private / link-local / reserved IPs and known internal hostnames.
 * Called at registration AND at delivery time (DNS-rebind defense).
 */
class WebhookUrlGuard
{
    /**
     * DNS cache TTL — short enough that DNS-rebind defense remains effective
     * (an attacker who flips DNS to internal must wait for the cache to expire).
     */
    private const DNS_CACHE_TTL = 60;

    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'metadata.google.internal',
        'metadata.goog',
    ];

    public static function assertSafe(string $url): void
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            throw ValidationException::withMessages(['url' => ['Invalid URL.']]);
        }

        $host = strtolower($parsed['host']);

        if (in_array($host, self::BLOCKED_HOSTNAMES, true)) {
            throw ValidationException::withMessages(['url' => ['URL host is not allowed.']]);
        }

        $ips = self::resolveIps($host);
        if (empty($ips)) {
            throw ValidationException::withMessages(['url' => ['Could not resolve URL host.']]);
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw ValidationException::withMessages(['url' => ['URL must not point to a private or loopback address.']]);
            }
        }
    }

    private static function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return Cache::remember("webhook_dns:{$host}", self::DNS_CACHE_TTL, function () use ($host) {
            $ips = [];
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            foreach ($records ?: [] as $r) {
                if (!empty($r['ip']))   $ips[] = $r['ip'];
                if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
            }
            return $ips;
        });
    }
}
