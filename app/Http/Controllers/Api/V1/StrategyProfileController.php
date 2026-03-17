<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StrategyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StrategyProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;
        // Return both global defaults and agency-specific strategies
        $strategies = StrategyProfile::where('agency_id', $agencyId)
            ->orWhereNull('agency_id')
            ->get();
        return response()->json(['success' => true, 'data' => $strategies]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255', 'slug' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'target_states' => 'nullable|array', 'wave_rules' => 'nullable|array',
            'revenue_threshold' => 'nullable|numeric', 'auto_wave_assignment' => 'boolean',
        ]);

        return response()->json(['success' => true, 'data' => StrategyProfile::create($data)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $strategy = StrategyProfile::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'target_states' => 'sometimes|nullable|array',
            'wave_rules' => 'sometimes|nullable|array',
            'revenue_threshold' => 'sometimes|nullable|numeric|min:0',
            'auto_wave_assignment' => 'sometimes|boolean',
        ]);
        $strategy->update($request->only([
            'name', 'description', 'target_states', 'wave_rules',
            'revenue_threshold', 'auto_wave_assignment',
        ]));
        return response()->json(['success' => true, 'data' => $strategy]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        StrategyProfile::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
