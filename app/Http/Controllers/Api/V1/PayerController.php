<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payer;
use App\Models\PayerPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayerController extends Controller
{
    // Global payer catalog (read-only)
    public function index(Request $request): JsonResponse
    {
        $query = Payer::query();
        if ($request->has('category')) $query->where('category', $request->category);
        if ($request->has('region')) $query->where('region', $request->region);
        if ($request->has('state')) $query->whereJsonContains('states', $request->state);
        return response()->json(['success' => true, 'data' => $query->orderBy('name')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Payer::findOrFail($id)]);
    }

    // Agency-specific payer plans
    public function plans(Request $request): JsonResponse
    {
        $query = PayerPlan::with('payer');
        if ($request->has('payer_id')) $query->where('payer_id', $request->payer_id);
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payer_id' => 'required|exists:payers,id',
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:commercial,medicare_advantage,medicaid,marketplace,tricare',
            'state' => 'nullable|string|size:2',
            'reimbursement_rate' => 'nullable|numeric', 'notes' => 'nullable|string',
        ]);

        return response()->json(['success' => true, 'data' => PayerPlan::create($data)], 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = PayerPlan::findOrFail($id);
        $plan->update($request->only(['name', 'type', 'state', 'reimbursement_rate', 'notes']));
        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function destroyPlan(int $id): JsonResponse
    {
        PayerPlan::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
