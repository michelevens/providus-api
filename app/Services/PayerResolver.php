<?php

// Resolves a raw payer_name string to a canonical Payer row.
//
// The problem: claim payer_name comes from clearinghouse imports,
// 837 batches, and manual operator entry. Same insurer arrives as
// "Florida Blue", "BLUE CROSS BLUE SHIELD OF FLORIDA", "BCBS of
// Florida", and several truncated variants. Aggregations that group
// by payer fragment across these spellings.
//
// The fix: every claim/charge_entries row gets a stable payer_id
// FK pointing at the canonical Payer record. This service is the
// resolver: pass it a free-text name, get back a Payer id.
//
// Match algorithm (first match wins):
//   1. Exact name match (case-insensitive, trimmed).
//   2. Longest-first scan of payers.aliases — for each payer, check
//      whether the lowercased input contains any of its aliases.
//      "Longest first" prevents "optum" (UHC alias) eating
//      "optum vaccn" (VA CCN alias).
//   3. Lowercased substring match against payers.name — catches
//      payers whose canonical name is itself a substring of the
//      input (e.g. "Aetna" inside "Aetna Texas").
//   4. Auto-create: insert a new payer row with the verbatim name,
//      mark needs_review=true. Returns the new id.
//
// Caching: the canonical list is small (~309 rows on prod). We load
// it once per request and cache in a static property. New rows
// created by this resolver invalidate the cache by clearing the
// static field — cheap enough.

namespace App\Services;

use App\Models\Payer;
use Illuminate\Support\Str;

class PayerResolver
{
    /**
     * Cached list of all payers, pre-sorted with longest aliases first.
     * Structure: array of ['id' => int, 'name_lc' => string, 'aliases' => array<string>].
     */
    private static ?array $cache = null;

    /**
     * Resolve a raw payer_name to a canonical payer id. Returns null
     * only when the input is empty/null — every other case either
     * matches or auto-creates.
     */
    public static function resolve(?string $rawName): ?int
    {
        $trimmed = trim((string) $rawName);
        if ($trimmed === '') {
            return null;
        }
        $lc = Str::lower($trimmed);

        $list = self::loadList();

        // 1. Exact (case-insensitive) name match.
        foreach ($list as $p) {
            if ($p['name_lc'] === $lc) {
                return $p['id'];
            }
        }

        // 2 & 3. Longest-alias-first substring scan. The list is
        //        pre-sorted so the first containment wins. We also
        //        include the canonical name itself as the last
        //        fallback for each payer (handled in loadList()).
        foreach ($list as $p) {
            foreach ($p['aliases'] as $alias) {
                if (str_contains($lc, $alias)) {
                    return $p['id'];
                }
            }
        }

        // 4. Auto-create + flag for review. Slug is derived from
        //    the trimmed name; collisions are extremely unlikely
        //    at this point because all known aliases have failed,
        //    but Str::slug() output appended with a short random
        //    suffix removes that risk entirely.
        return self::createNeedsReview($trimmed);
    }

    /**
     * Force a cache reload — used by the boot listener after a
     * needs_review row is auto-created, and by tests.
     */
    public static function flushCache(): void
    {
        self::$cache = null;
    }

    /**
     * Build (or return cached) sorted alias list. Each entry is
     * a tuple of payer_id + lowercased aliases (longest first)
     * + lowercased canonical name (as last-resort alias).
     */
    private static function loadList(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = Payer::query()
            ->select(['id', 'name', 'aliases'])
            ->get();

        $tuples = [];
        foreach ($rows as $row) {
            // Aliases column may be null when migration ran but seed
            // hasn't (e.g. newly created payer). Coerce to [].
            $aliases = is_array($row->aliases) ? $row->aliases : [];
            // Lowercase + dedupe. Add the canonical name itself as
            // the lowest-priority alias so payers without an alias
            // list still match by name substring.
            $allAliases = array_map(fn ($a) => Str::lower((string) $a), $aliases);
            $allAliases[] = Str::lower($row->name);
            $allAliases = array_values(array_unique(array_filter($allAliases)));
            // Longest first — beats the "optum eats optum vaccn" trap.
            usort($allAliases, fn ($a, $b) => strlen($b) <=> strlen($a));

            $tuples[] = [
                'id'      => (int) $row->id,
                'name_lc' => Str::lower($row->name),
                'aliases' => $allAliases,
            ];
        }

        // Sort tuples by their longest alias so payers with the
        // most specific aliases get first crack overall. Two payers
        // with the same top-alias length keep their DB order.
        usort($tuples, function ($a, $b) {
            $la = isset($a['aliases'][0]) ? strlen($a['aliases'][0]) : 0;
            $lb = isset($b['aliases'][0]) ? strlen($b['aliases'][0]) : 0;
            return $lb <=> $la;
        });

        self::$cache = $tuples;
        return $tuples;
    }

    /**
     * Insert a new Payer row with the verbatim name. needs_review=true
     * so the operator sees it in the merge queue. Returns the new id.
     */
    private static function createNeedsReview(string $name): int
    {
        // Slug: stable form + 6-char nonce to dodge any collision
        // with the existing pyr_* slugs in the seed catalog.
        $base   = Str::slug($name);
        $nonce  = Str::lower(Str::random(6));
        $slug   = 'pyr_auto_' . ($base !== '' ? $base . '_' : '') . $nonce;

        $payer = Payer::create([
            'slug'         => $slug,
            'name'         => $name,
            'category'     => 'unknown',
            'region'       => 'unknown',
            'needs_review' => true,
        ]);

        // Bust cache so subsequent resolves see the new row and
        // operators merging duplicates don't have to wait for a
        // process restart.
        self::flushCache();

        return $payer->id;
    }
}
