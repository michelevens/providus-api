<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\OfficeHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeHourController extends Controller
{
    // Admin: get office hours for agency
    public function index(Request $request): JsonResponse
    {
        $hours = OfficeHour::where('agency_id', $request->user()->agency_id)
            ->orderBy('day_of_week')
            ->get();
        return response()->json(['success' => true, 'data' => $hours]);
    }

    // Admin: set/update office hours (bulk upsert)
    public function upsert(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|integer|min:0|max:6',
            'hours.*.start_hour' => 'required|string|max:5',
            'hours.*.end_hour' => 'required|string|max:5',
            'hours.*.is_closed' => 'nullable|boolean',
        ]);

        $agencyId = $request->user()->agency_id;

        foreach ($request->hours as $hour) {
            OfficeHour::updateOrCreate(
                ['agency_id' => $agencyId, 'day_of_week' => $hour['day_of_week']],
                [
                    'start_hour' => $hour['start_hour'],
                    'end_hour' => $hour['end_hour'],
                    'is_closed' => $hour['is_closed'] ?? false,
                ]
            );
        }

        $hours = OfficeHour::where('agency_id', $agencyId)->orderBy('day_of_week')->get();
        return response()->json(['success' => true, 'data' => $hours]);
    }

    // PUBLIC: Get office hours for an agency by slug
    public function publicIndex(string $slug): JsonResponse
    {
        $agency = Agency::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $hours = OfficeHour::where('agency_id', $agency->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json(['success' => true, 'data' => $hours]);
    }
}
