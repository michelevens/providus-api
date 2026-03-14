<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payer;
use App\Models\Provider;
use App\Models\StrategyProfile;
use Illuminate\Support\Facades\DB;

class BatchGeneratorService
{
    /**
     * Default revenue estimation rates by payer category (per visit).
     */
    const REVENUE_RATES = [
        'national'         => ['eval' => 250, 'followup' => 130],
        'bcbs_anthem'      => ['eval' => 240, 'followup' => 125],
        'bcbs_hcsc'        => ['eval' => 235, 'followup' => 120],
        'bcbs_highmark'    => ['eval' => 230, 'followup' => 118],
        'bcbs_independent' => ['eval' => 225, 'followup' => 115],
        'regional'         => ['eval' => 200, 'followup' => 105],
        'medicaid'         => ['eval' => 140, 'followup' =>  70],
        'medicare'         => ['eval' => 180, 'followup' =>  90],
    ];

    /**
     * Estimated monthly patient volumes by wave priority.
     */
    const VOLUME_BY_WAVE = [
        1 => ['new' => 15, 'followup' => 40],
        2 => ['new' => 10, 'followup' => 25],
        3 => ['new' =>  5, 'followup' => 12],
    ];

    /**
     * Generate a batch of Application records for each provider-payer combination.
     *
     * @param  Provider[]|array  $providers   Provider models or IDs
     * @param  Payer[]|array     $payers      Payer models or IDs
     * @param  array             $options     {
     *     @type int|null        $agency_id           Required agency scope
     *     @type int|null        $organization_id     Organization to assign
     *     @type int|null        $strategy_profile_id Strategy profile with wave_rules
     *     @type string[]        $target_states       Only generate for these states (empty = all payer states)
     *     @type bool            $skip_existing       Skip provider+payer+state combos that already exist (default true)
     *     @type int|null        $default_wave        Default wave when no strategy rule matches (default 1)
     *     @type float|null      $revenue_threshold   Minimum estimated monthly revenue to include
     * }
     * @return array{success: bool, created: int, skipped: int, total_revenue: float, applications: Application[], by_wave: array, by_state: array}
     */
    public function generate(array $providers, array $payers, array $options = []): array
    {
        $agencyId        = $options['agency_id'] ?? null;
        $organizationId  = $options['organization_id'] ?? null;
        $strategyId      = $options['strategy_profile_id'] ?? null;
        $targetStates    = $options['target_states'] ?? [];
        $skipExisting    = $options['skip_existing'] ?? true;
        $defaultWave     = $options['default_wave'] ?? 1;
        $revenueThreshold = $options['revenue_threshold'] ?? null;

        // Resolve strategy profile for wave rules
        $waveRules = [];
        if ($strategyId) {
            $strategy = StrategyProfile::find($strategyId);
            if ($strategy) {
                $waveRules = $strategy->wave_rules ?? [];
                if (empty($targetStates) && !empty($strategy->target_states)) {
                    $targetStates = $strategy->target_states;
                }
                $revenueThreshold = $revenueThreshold ?? $strategy->revenue_threshold;
            }
        }

        // Resolve models from IDs if needed
        $providerModels = $this->resolveModels($providers, Provider::class);
        $payerModels    = $this->resolveModels($payers, Payer::class);

        // Build set of existing keys to skip duplicates
        $existingKeys = collect();
        if ($skipExisting && $agencyId) {
            $existingKeys = Application::where('agency_id', $agencyId)
                ->get(['provider_id', 'payer_id', 'state'])
                ->map(fn ($a) => "{$a->provider_id}|{$a->payer_id}|{$a->state}");
        }

        $created = [];
        $skipped = 0;
        $totalRevenue = 0;
        $byWave  = [];
        $byState = [];

        DB::transaction(function () use (
            $providerModels, $payerModels, $targetStates, $waveRules,
            $defaultWave, $revenueThreshold, $skipExisting, $existingKeys,
            $agencyId, $organizationId,
            &$created, &$skipped, &$totalRevenue, &$byWave, &$byState
        ) {
            foreach ($providerModels as $provider) {
                foreach ($payerModels as $payer) {
                    $states = $this->resolveStates($payer, $targetStates);

                    foreach ($states as $state) {
                        $key = "{$provider->id}|{$payer->id}|{$state}";

                        if ($skipExisting && $existingKeys->contains($key)) {
                            $skipped++;
                            continue;
                        }

                        $wave = $this->resolveWave($payer, $waveRules, $defaultWave);
                        $estRevenue = $this->estimateMonthlyRevenue($payer->category, $wave);

                        if ($revenueThreshold && $estRevenue < $revenueThreshold) {
                            $skipped++;
                            continue;
                        }

                        $app = Application::create([
                            'agency_id'          => $agencyId ?? $provider->agency_id,
                            'provider_id'        => $provider->id,
                            'organization_id'    => $organizationId ?? $provider->organization_id,
                            'payer_id'           => $payer->id,
                            'payer_name'         => $payer->name,
                            'state'              => $state,
                            'type'               => 'individual',
                            'wave'               => $wave,
                            'status'             => 'not_started',
                            'est_monthly_revenue' => $estRevenue,
                            'tags'               => [$payer->category, "wave_{$wave}"],
                        ]);

                        $created[] = $app;
                        $totalRevenue += $estRevenue;
                        $byWave[$wave] = ($byWave[$wave] ?? 0) + 1;
                        $byState[$state] = ($byState[$state] ?? 0) + 1;

                        // Track to prevent duplicates within this batch
                        $existingKeys->push($key);
                    }
                }
            }
        });

        // Sort summary keys
        ksort($byWave);
        ksort($byState);

        return [
            'success'       => true,
            'created'       => count($created),
            'skipped'       => $skipped,
            'total_revenue' => round($totalRevenue, 2),
            'annual_revenue' => round($totalRevenue * 12, 2),
            'applications'  => $created,
            'by_wave'       => $byWave,
            'by_state'      => $byState,
            'unique_payers' => count($payerModels),
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Determine which states to generate applications for given a payer
     * and the optional target-state filter.
     *
     * @return string[]
     */
    private function resolveStates(Payer $payer, array $targetStates): array
    {
        $payerStates = $payer->states ?? [];

        // National payers cover all targets (or a single "ALL" record)
        if (in_array('ALL', $payerStates, true)) {
            return !empty($targetStates) ? $targetStates : ['ALL'];
        }

        if (empty($targetStates)) {
            return $payerStates;
        }

        return array_values(array_intersect($payerStates, $targetStates));
    }

    /**
     * Determine the wave number for a payer using strategy wave_rules.
     */
    private function resolveWave(Payer $payer, array $waveRules, int $default): int
    {
        foreach ($waveRules as $rule) {
            $category = $rule['payerCategory'] ?? $rule['payer_category'] ?? null;

            if ($category && $category === $payer->category) {
                return (int) ($rule['wave'] ?? $default);
            }

            $payerIds = $rule['payerIds'] ?? $rule['payer_ids'] ?? [];
            if (!empty($payerIds) && in_array($payer->id, $payerIds, true)) {
                return (int) ($rule['wave'] ?? $default);
            }
        }

        return $default;
    }

    /**
     * Estimate monthly revenue based on payer category and wave.
     */
    public function estimateMonthlyRevenue(string $category, int $wave): float
    {
        $rates  = self::REVENUE_RATES[$category] ?? self::REVENUE_RATES['regional'];
        $volume = self::VOLUME_BY_WAVE[$wave] ?? self::VOLUME_BY_WAVE[3];

        return round(
            ($volume['new'] * $rates['eval']) + ($volume['followup'] * $rates['followup']),
            2
        );
    }

    /**
     * Resolve an array of models or IDs into model instances.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  array       $items
     * @param  class-string<T>  $modelClass
     * @return T[]
     */
    private function resolveModels(array $items, string $modelClass): array
    {
        if (empty($items)) {
            return [];
        }

        // Already model instances
        if ($items[0] instanceof $modelClass) {
            return $items;
        }

        // Array of IDs
        return $modelClass::whereIn('id', $items)->get()->all();
    }
}
