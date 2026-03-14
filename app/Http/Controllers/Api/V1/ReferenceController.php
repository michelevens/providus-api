<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payer;
use App\Models\TelehealthPolicy;
use App\Models\TaxonomyCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function states(): JsonResponse
    {
        // Population estimates (2025 US Census Bureau projections, in thousands)
        $states = [
            ['code' => 'AL', 'name' => 'Alabama', 'region' => 'southeast', 'population' => 5108],
            ['code' => 'AK', 'name' => 'Alaska', 'region' => 'pacific_nw', 'population' => 734],
            ['code' => 'AZ', 'name' => 'Arizona', 'region' => 'west', 'population' => 7578],
            ['code' => 'AR', 'name' => 'Arkansas', 'region' => 'south', 'population' => 3068],
            ['code' => 'CA', 'name' => 'California', 'region' => 'west', 'population' => 38965],
            ['code' => 'CO', 'name' => 'Colorado', 'region' => 'west', 'population' => 5915],
            ['code' => 'CT', 'name' => 'Connecticut', 'region' => 'northeast', 'population' => 3617],
            ['code' => 'DE', 'name' => 'Delaware', 'region' => 'northeast', 'population' => 1018],
            ['code' => 'DC', 'name' => 'District of Columbia', 'region' => 'northeast', 'population' => 679],
            ['code' => 'FL', 'name' => 'Florida', 'region' => 'southeast', 'population' => 23372],
            ['code' => 'GA', 'name' => 'Georgia', 'region' => 'southeast', 'population' => 11029],
            ['code' => 'HI', 'name' => 'Hawaii', 'region' => 'west', 'population' => 1436],
            ['code' => 'ID', 'name' => 'Idaho', 'region' => 'pacific_nw', 'population' => 2001],
            ['code' => 'IL', 'name' => 'Illinois', 'region' => 'midwest', 'population' => 12516],
            ['code' => 'IN', 'name' => 'Indiana', 'region' => 'midwest', 'population' => 6876],
            ['code' => 'IA', 'name' => 'Iowa', 'region' => 'midwest', 'population' => 3207],
            ['code' => 'KS', 'name' => 'Kansas', 'region' => 'midwest', 'population' => 2937],
            ['code' => 'KY', 'name' => 'Kentucky', 'region' => 'southeast', 'population' => 4526],
            ['code' => 'LA', 'name' => 'Louisiana', 'region' => 'south', 'population' => 4590],
            ['code' => 'ME', 'name' => 'Maine', 'region' => 'northeast', 'population' => 1396],
            ['code' => 'MD', 'name' => 'Maryland', 'region' => 'northeast', 'population' => 6180],
            ['code' => 'MA', 'name' => 'Massachusetts', 'region' => 'northeast', 'population' => 7001],
            ['code' => 'MI', 'name' => 'Michigan', 'region' => 'midwest', 'population' => 10037],
            ['code' => 'MN', 'name' => 'Minnesota', 'region' => 'midwest', 'population' => 5787],
            ['code' => 'MS', 'name' => 'Mississippi', 'region' => 'south', 'population' => 2940],
            ['code' => 'MO', 'name' => 'Missouri', 'region' => 'midwest', 'population' => 6196],
            ['code' => 'MT', 'name' => 'Montana', 'region' => 'west', 'population' => 1133],
            ['code' => 'NE', 'name' => 'Nebraska', 'region' => 'midwest', 'population' => 1978],
            ['code' => 'NV', 'name' => 'Nevada', 'region' => 'west', 'population' => 3194],
            ['code' => 'NH', 'name' => 'New Hampshire', 'region' => 'northeast', 'population' => 1402],
            ['code' => 'NJ', 'name' => 'New Jersey', 'region' => 'northeast', 'population' => 9290],
            ['code' => 'NM', 'name' => 'New Mexico', 'region' => 'west', 'population' => 2115],
            ['code' => 'NY', 'name' => 'New York', 'region' => 'northeast', 'population' => 19571],
            ['code' => 'NC', 'name' => 'North Carolina', 'region' => 'southeast', 'population' => 10835],
            ['code' => 'ND', 'name' => 'North Dakota', 'region' => 'midwest', 'population' => 783],
            ['code' => 'OH', 'name' => 'Ohio', 'region' => 'midwest', 'population' => 11780],
            ['code' => 'OK', 'name' => 'Oklahoma', 'region' => 'south', 'population' => 4019],
            ['code' => 'OR', 'name' => 'Oregon', 'region' => 'pacific_nw', 'population' => 4233],
            ['code' => 'PA', 'name' => 'Pennsylvania', 'region' => 'northeast', 'population' => 12972],
            ['code' => 'RI', 'name' => 'Rhode Island', 'region' => 'northeast', 'population' => 1095],
            ['code' => 'SC', 'name' => 'South Carolina', 'region' => 'southeast', 'population' => 5373],
            ['code' => 'SD', 'name' => 'South Dakota', 'region' => 'midwest', 'population' => 919],
            ['code' => 'TN', 'name' => 'Tennessee', 'region' => 'southeast', 'population' => 7126],
            ['code' => 'TX', 'name' => 'Texas', 'region' => 'south', 'population' => 30503],
            ['code' => 'UT', 'name' => 'Utah', 'region' => 'west', 'population' => 3417],
            ['code' => 'VT', 'name' => 'Vermont', 'region' => 'northeast', 'population' => 648],
            ['code' => 'VA', 'name' => 'Virginia', 'region' => 'southeast', 'population' => 8683],
            ['code' => 'WA', 'name' => 'Washington', 'region' => 'pacific_nw', 'population' => 7812],
            ['code' => 'WV', 'name' => 'West Virginia', 'region' => 'southeast', 'population' => 1770],
            ['code' => 'WI', 'name' => 'Wisconsin', 'region' => 'midwest', 'population' => 5893],
            ['code' => 'WY', 'name' => 'Wyoming', 'region' => 'west', 'population' => 577],
        ];

        // Add abbreviation field for frontend compatibility
        foreach ($states as &$state) {
            $state['abbreviation'] = $state['code'];
        }

        return response()->json(['success' => true, 'data' => $states]);
    }

    public function telehealthPolicies(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => TelehealthPolicy::all()]);
    }

    public function taxonomyCodes(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => TaxonomyCode::all()]);
    }

    public function payers(Request $request): JsonResponse
    {
        $query = Payer::query();

        if ($request->state) {
            $query->whereJsonContains('states', $request->state)
                ->orWhereJsonContains('states', 'ALL');
        }

        return response()->json(['success' => true, 'data' => $query->orderBy('name')->get()]);
    }
}
