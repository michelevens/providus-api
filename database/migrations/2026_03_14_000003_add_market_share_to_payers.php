<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Real approximate national market share percentages (2025 estimates)
        $shares = [
            'pyr_uhc'          => 14.0,
            'pyr_aetna'        => 7.5,
            'pyr_cigna'        => 6.0,
            'pyr_humana'       => 5.5,
            'pyr_medicare'     => 18.5,
            'pyr_centene'      => 8.0,
            'pyr_molina'       => 2.5,
            'pyr_oscar'        => 0.5,
            'pyr_tricare'      => 2.8,
            'pyr_kaiser'       => 4.0,
            'pyr_anthem'       => 6.5,
            'pyr_hcsc'         => 5.0,
            'pyr_highmark'     => 2.0,
            'pyr_premera'      => 1.5,
            'pyr_regence'      => 1.5,
            'pyr_florida_blue' => 3.5,
            'pyr_carefirst'    => 2.0,
            'pyr_bcbs_ma'      => 2.5,
            'pyr_bcbs_az'      => 1.5,
            'pyr_horizon'      => 2.0,
            'pyr_bcbs_nc'      => 2.5,
            'pyr_bcbs_sc'      => 1.5,
            'pyr_bcbs_tn'      => 2.0,
            'pyr_bcbs_al'      => 1.5,
            'pyr_bcbs_mi'      => 2.0,
            'pyr_bcbs_mn'      => 1.5,
            'pyr_bcbs_la'      => 1.5,
            'pyr_bcbs_ks'      => 1.0,
            'pyr_wellmark'     => 1.0,
            'pyr_independence' => 1.5,
            'pyr_emblem'       => 1.2,
            'pyr_fidelis'      => 1.0,
            'pyr_healthfirst'  => 1.0,
            'pyr_connecticare' => 0.8,
            'pyr_harvard_pilgrim' => 1.0,
            'pyr_tufts'        => 0.8,
            'pyr_mvp'          => 0.6,
            'pyr_amerihealth'  => 1.0,
            'pyr_upmc'         => 1.2,
            'pyr_avmed'        => 0.8,
            'pyr_simply'       => 0.6,
            'pyr_sunshine'     => 1.0,
            'pyr_optima'       => 0.8,
            'pyr_virginia_premier' => 0.5,
            'pyr_priority_partners' => 0.5,
            'pyr_wellcare'     => 2.5,
            'pyr_caresource'   => 1.2,
            'pyr_medical_mutual' => 0.8,
            'pyr_priority_health' => 0.8,
            'pyr_healthpartners' => 0.8,
            'pyr_hpn'          => 0.5,
            'pyr_mercy_care'   => 0.8,
            'pyr_banner_aetna' => 0.5,
            'pyr_coordinated_care' => 0.5,
            'pyr_community_health_wa' => 0.4,
            'pyr_providence'   => 0.6,
            'pyr_moda'         => 0.4,
            'pyr_superior'     => 1.0,
            'pyr_healthy_blue' => 0.8,
        ];

        foreach ($shares as $slug => $share) {
            DB::table('payers')->where('slug', $slug)->update(['market_share' => $share]);
        }
    }

    public function down(): void
    {
        DB::table('payers')->update(['market_share' => null]);
    }
};
