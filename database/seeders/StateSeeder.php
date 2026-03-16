<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            // code, name, region, population (thousands 2025 est), NLC, PSYPACT, ASLP, PT, OT, Counseling
            ['AL', 'Alabama',                'southeast',   5108,  true,  true,  true,  true,  true,  false],
            ['AK', 'Alaska',                 'pacific_nw',   734,  false, false, false, false, false, false],
            ['AZ', 'Arizona',                'west',        7431,  true,  true,  true,  true,  true,  true],
            ['AR', 'Arkansas',               'south',       3068,  true,  true,  false, true,  true,  false],
            ['CA', 'California',             'west',       39040,  false, false, false, false, false, false],
            ['CO', 'Colorado',               'west',        5922,  true,  true,  true,  true,  true,  true],
            ['CT', 'Connecticut',            'northeast',   3626,  false, true,  false, false, false, false],
            ['DE', 'Delaware',               'northeast',   1018,  true,  true,  true,  true,  true,  false],
            ['DC', 'District of Columbia',   'northeast',    670,  true,  true,  false, true,  false, false],
            ['FL', 'Florida',                'southeast',   22610, true,  true,  true,  true,  true,  true],
            ['GA', 'Georgia',                'southeast',   11029, true,  true,  true,  true,  true,  true],
            ['HI', 'Hawaii',                 'west',        1441,  false, false, false, false, false, false],
            ['ID', 'Idaho',                  'west',        1964,  true,  true,  true,  true,  true,  false],
            ['IL', 'Illinois',               'midwest',    12550, false, true,  false, true,  false, false],
            ['IN', 'Indiana',                'midwest',     6862,  true,  true,  true,  true,  true,  false],
            ['IA', 'Iowa',                   'midwest',     3219,  true,  true,  true,  true,  true,  true],
            ['KS', 'Kansas',                 'midwest',     2940,  true,  true,  true,  true,  true,  true],
            ['KY', 'Kentucky',               'southeast',   4526,  true,  true,  true,  true,  true,  false],
            ['LA', 'Louisiana',              'south',       4590,  true,  true,  false, true,  true,  false],
            ['ME', 'Maine',                  'northeast',   1395,  true,  true,  true,  true,  true,  false],
            ['MD', 'Maryland',               'northeast',   6180,  true,  true,  true,  true,  true,  true],
            ['MA', 'Massachusetts',          'northeast',   7033,  false, false, false, false, false, false],
            ['MI', 'Michigan',               'midwest',    10037, false, false, false, false, false, false],
            ['MN', 'Minnesota',              'midwest',     5764,  false, true,  true,  true,  true,  false],
            ['MS', 'Mississippi',            'south',       2940,  true,  true,  true,  true,  true,  false],
            ['MO', 'Missouri',               'midwest',     6196,  true,  true,  true,  true,  true,  false],
            ['MT', 'Montana',                'west',        1122,  true,  true,  true,  true,  true,  false],
            ['NE', 'Nebraska',               'midwest',     1978,  true,  true,  true,  true,  true,  true],
            ['NV', 'Nevada',                 'west',        3194,  false, true,  false, true,  false, false],
            ['NH', 'New Hampshire',          'northeast',   1402,  true,  true,  true,  true,  true,  true],
            ['NJ', 'New Jersey',             'northeast',   9290,  true,  true,  false, true,  false, false],
            ['NM', 'New Mexico',             'west',        2115,  true,  true,  true,  true,  true,  false],
            ['NY', 'New York',               'northeast',   19571, false, false, false, false, false, false],
            ['NC', 'North Carolina',         'southeast',   10835, true,  true,  true,  true,  true,  true],
            ['ND', 'North Dakota',           'midwest',      783,  true,  true,  true,  true,  true,  false],
            ['OH', 'Ohio',                   'midwest',    11780, true,  true,  true,  true,  true,  true],
            ['OK', 'Oklahoma',               'south',       4019,  true,  true,  true,  true,  true,  false],
            ['OR', 'Oregon',                 'pacific_nw',  4241,  false, true,  false, true,  false, false],
            ['PA', 'Pennsylvania',           'northeast',   12960, false, true,  false, true,  false, false],
            ['RI', 'Rhode Island',           'northeast',   1100,  false, false, false, false, false, false],
            ['SC', 'South Carolina',         'southeast',   5373,  true,  true,  true,  true,  true,  false],
            ['SD', 'South Dakota',           'midwest',      909,  true,  true,  true,  true,  true,  false],
            ['TN', 'Tennessee',              'southeast',   7126,  true,  true,  true,  true,  true,  true],
            ['TX', 'Texas',                  'south',       30503, true,  true,  true,  true,  true,  false],
            ['UT', 'Utah',                   'west',        3417,  true,  true,  true,  true,  true,  false],
            ['VT', 'Vermont',                'northeast',    648,  true,  true,  false, true,  true,  false],
            ['VA', 'Virginia',               'southeast',   8683,  true,  true,  true,  true,  true,  false],
            ['WA', 'Washington',             'pacific_nw',  7958,  false, true,  true,  true,  true,  false],
            ['WV', 'West Virginia',          'southeast',   1770,  true,  true,  true,  true,  true,  false],
            ['WI', 'Wisconsin',              'midwest',     5910,  true,  true,  true,  true,  true,  false],
            ['WY', 'Wyoming',                'west',         577,  true,  true,  true,  true,  true,  false],
        ];

        foreach ($states as [$code, $name, $region, $pop, $nlc, $psypact, $aslp, $pt, $ot, $counseling]) {
            DB::table('states')->updateOrInsert(
                ['code' => $code],
                [
                    'name'                   => $name,
                    'region'                 => $region,
                    'population'             => $pop,
                    'is_compact_nlc'         => $nlc,
                    'is_compact_psypact'     => $psypact,
                    'is_compact_aslp'        => $aslp,
                    'is_compact_pt'          => $pt,
                    'is_compact_ot'          => $ot,
                    'is_compact_counseling'  => $counseling,
                ]
            );
        }
    }
}
