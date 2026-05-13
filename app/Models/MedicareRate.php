<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MedicareRate extends Model
{
    protected $fillable = [
        'cpt_code', 'modifier', 'cpt_description',
        'state', 'locality_code', 'year',
        'non_facility_rate', 'facility_rate',
        'effective_date', 'source',
    ];

    protected $casts = [
        'year' => 'integer',
        'non_facility_rate' => 'decimal:2',
        'facility_rate' => 'decimal:2',
        'effective_date' => 'date',
    ];

    /**
     * Find the best matching rate for a CPT (+ optional modifier +
     * optional state). Falls back: exact state match → national → null.
     * If multiple years exist, picks the most recent.
     */
    public static function lookup(string $cpt, ?string $state = null, ?string $modifier = null): ?self
    {
        $q = static::query()->where('cpt_code', $cpt);
        if ($modifier) $q->where('modifier', $modifier);

        // Most-recent first.
        $q->orderByDesc('year')->orderByDesc('effective_date');

        if ($state) {
            $stateMatch = (clone $q)->where('state', $state)->first();
            if ($stateMatch) return $stateMatch;
        }
        return (clone $q)->whereNull('state')->first()
            ?? (clone $q)->first(); // last resort: any state, most recent
    }

    /**
     * Batch lookup — returns a map keyed by cpt_code so the CPT
     * analysis tab can fetch every rate in one query.
     */
    public static function lookupBatch(array $cpts, ?string $state = null, ?int $year = null): array
    {
        if (empty($cpts)) return [];
        $q = static::query()->whereIn('cpt_code', $cpts);
        if ($year) $q->where('year', $year);
        $rows = $q->orderByDesc('year')->orderByDesc('effective_date')->get();

        // For each cpt, prefer the state match, then null-state, then anything.
        $byCpt = [];
        foreach ($rows as $r) {
            $cpt = $r->cpt_code;
            if (!isset($byCpt[$cpt])) {
                $byCpt[$cpt] = $r;
                continue;
            }
            $current = $byCpt[$cpt];
            // Replace current if this row is a better state match.
            if ($state) {
                if ($r->state === $state && $current->state !== $state) {
                    $byCpt[$cpt] = $r;
                } elseif ($r->state === null && $current->state !== $state && $current->state !== null) {
                    $byCpt[$cpt] = $r;
                }
            }
        }
        return $byCpt;
    }

    public function scopeForYear(Builder $q, int $year): Builder
    {
        return $q->where('year', $year);
    }
}
