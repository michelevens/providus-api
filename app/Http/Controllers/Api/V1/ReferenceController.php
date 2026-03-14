<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payer;
use App\Models\TaxonomyCode;
use App\Models\TelehealthPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function states(): JsonResponse
    {
        $states = [
            'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California',
            'CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia',
            'HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa',
            'KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland',
            'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri',
            'MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey',
            'NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio',
            'OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina',
            'SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont',
            'VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming',
            'DC'=>'District of Columbia',
        ];
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

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('state')) {
            $query->whereJsonContains('states', $request->state);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }
}
