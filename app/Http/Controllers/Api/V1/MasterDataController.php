<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payer;
use App\Models\StrategyProfile;
use App\Models\TelehealthPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    // ── Payers ─────────────────────────────────────────────────

    public function payers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Payer::orderBy('name')->get(),
        ]);
    }

    public function storePayer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:50|unique:payers,slug',
            'name' => 'required|string|max:255',
            'category' => 'nullable|in:national,bcbs,regional,medicaid,medicare,specialty',
            'region' => 'nullable|string|max:100',
            'parent_org' => 'nullable|string|max:255',
            'stedi_id' => 'nullable|string|max:50',
            'states' => 'nullable|array',
            'avg_cred_days' => 'nullable|integer',
            'credentialing_url' => 'nullable|string|max:500',
        ]);

        return response()->json(['success' => true, 'data' => Payer::create($data)], 201);
    }

    public function updatePayer(Request $request, int $id): JsonResponse
    {
        $payer = Payer::findOrFail($id);
        $request->validate([
            'slug' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|nullable|in:national,bcbs,regional,medicaid,medicare,specialty',
            'region' => 'sometimes|nullable|string|max:100',
            'parent_org' => 'sometimes|nullable|string|max:255',
            'stedi_id' => 'sometimes|nullable|string|max:50',
            'states' => 'sometimes|nullable|array',
            'avg_cred_days' => 'sometimes|nullable|integer|min:0',
            'credentialing_url' => 'sometimes|nullable|url|max:500',
        ]);
        $payer->update($request->only([
            'slug', 'name', 'category', 'region', 'parent_org',
            'stedi_id', 'states', 'avg_cred_days', 'credentialing_url',
        ]));
        return response()->json(['success' => true, 'data' => $payer]);
    }

    public function destroyPayer(int $id): JsonResponse
    {
        Payer::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Telehealth Policies ───────────────────────────────────

    public function telehealthPolicies(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => TelehealthPolicy::orderBy('state')->get(),
        ]);
    }

    public function storeTelehealthPolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => 'required|string|size:2|unique:telehealth_policies,state',
            'practice_authority' => 'nullable|string|max:30',
            'prescriptive_authority' => 'nullable|string|max:30',
            'telehealth_parity' => 'boolean',
            'controlled_substances' => 'nullable|string|max:30',
            'informed_consent' => 'nullable|string|max:30',
            'compact_state' => 'boolean',
            'readiness_score' => 'nullable|integer|min:0|max:100',
        ]);

        return response()->json(['success' => true, 'data' => TelehealthPolicy::create($data)], 201);
    }

    public function updateTelehealthPolicy(Request $request, int $id): JsonResponse
    {
        $policy = TelehealthPolicy::findOrFail($id);
        $request->validate([
            'practice_authority' => 'sometimes|nullable|string|max:30',
            'prescriptive_authority' => 'sometimes|nullable|string|max:30',
            'telehealth_parity' => 'sometimes|boolean',
            'controlled_substances' => 'sometimes|nullable|string|max:30',
            'informed_consent' => 'sometimes|nullable|string|max:30',
            'compact_state' => 'sometimes|boolean',
            'readiness_score' => 'sometimes|nullable|integer|min:0|max:100',
        ]);
        $policy->update($request->only([
            'practice_authority', 'prescriptive_authority', 'telehealth_parity',
            'controlled_substances', 'informed_consent', 'compact_state', 'readiness_score',
        ]));
        return response()->json(['success' => true, 'data' => $policy]);
    }

    public function destroyTelehealthPolicy(int $id): JsonResponse
    {
        TelehealthPolicy::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Strategy Profile Templates ────────────────────────────

    public function strategyTemplates(): JsonResponse
    {
        // Global templates (no agency_id)
        return response()->json([
            'success' => true,
            'data' => StrategyProfile::whereNull('agency_id')->orderBy('name')->get(),
        ]);
    }

    public function storeStrategyTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'target_states' => 'nullable|array',
            'wave_rules' => 'nullable|array',
            'revenue_threshold' => 'nullable|numeric',
        ]);
        $data['agency_id'] = null; // Global template

        return response()->json(['success' => true, 'data' => StrategyProfile::create($data)], 201);
    }

    public function updateStrategyTemplate(Request $request, int $id): JsonResponse
    {
        $template = StrategyProfile::whereNull('agency_id')->findOrFail($id);
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'target_states' => 'sometimes|nullable|array',
            'wave_rules' => 'sometimes|nullable|array',
            'revenue_threshold' => 'sometimes|nullable|numeric|min:0',
        ]);
        $template->update($request->only([
            'name', 'target_states', 'wave_rules', 'revenue_threshold',
        ]));
        return response()->json(['success' => true, 'data' => $template]);
    }

    public function destroyStrategyTemplate(int $id): JsonResponse
    {
        StrategyProfile::whereNull('agency_id')->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Taxonomy Codes ────────────────────────────────────────

    public function taxonomyCodes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DB::table('taxonomy_codes')->orderBy('code')->get(),
        ]);
    }

    public function storeTaxonomyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:taxonomy_codes,code',
            'classification' => 'required|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $id = DB::table('taxonomy_codes')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return response()->json(['success' => true, 'data' => DB::table('taxonomy_codes')->find($id)], 201);
    }

    public function updateTaxonomyCode(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|string|max:20',
            'classification' => 'sometimes|string|max:255',
            'specialization' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
        ]);
        DB::table('taxonomy_codes')->where('id', $id)->update(array_merge(
            $request->only(['code', 'classification', 'specialization', 'description']),
            ['updated_at' => now()]
        ));

        return response()->json(['success' => true, 'data' => DB::table('taxonomy_codes')->find($id)]);
    }

    public function destroyTaxonomyCode(int $id): JsonResponse
    {
        DB::table('taxonomy_codes')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Seed / Reprovision ────────────────────────────────────

    public function seedStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'payers' => Payer::count(),
                'telehealth_policies' => TelehealthPolicy::count(),
                'taxonomy_codes' => DB::table('taxonomy_codes')->count(),
                'strategy_templates' => StrategyProfile::whereNull('agency_id')->count(),
            ],
        ]);
    }
}
