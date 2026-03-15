<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BoardCertification;
use App\Models\MalpracticePolicy;
use App\Models\ProviderCme;
use App\Models\ProviderDocument;
use App\Models\ProviderEducation;
use App\Models\ProviderReference;
use App\Models\ProviderWorkHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderProfileController extends Controller
{
    // ── Malpractice Policies ──
    public function malpractice(Request $request, int $providerId): JsonResponse
    {
        $data = MalpracticePolicy::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->orderByDesc('effective_date')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeMalpractice(Request $request, int $providerId): JsonResponse
    {
        $request->validate([
            'carrier_name' => 'required|string|max:200',
            'effective_date' => 'nullable|date',
            'expiration_date' => 'nullable|date',
        ]);
        $policy = MalpracticePolicy::create([
            'agency_id' => $request->user()->agency_id,
            'provider_id' => $providerId,
            ...$request->only([
                'carrier_name', 'policy_number', 'coverage_type',
                'per_incident_amount', 'aggregate_amount', 'effective_date',
                'expiration_date', 'status', 'has_tail_coverage', 'has_claims_history',
                'claims_count', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $policy], 201);
    }

    public function updateMalpractice(Request $request, int $providerId, int $id): JsonResponse
    {
        $policy = MalpracticePolicy::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id);
        $policy->update($request->all());
        return response()->json(['success' => true, 'data' => $policy]);
    }

    public function destroyMalpractice(Request $request, int $providerId, int $id): JsonResponse
    {
        MalpracticePolicy::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Education ──
    public function education(Request $request, int $providerId): JsonResponse
    {
        $data = ProviderEducation::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->orderByDesc('graduation_date')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeEducation(Request $request, int $providerId): JsonResponse
    {
        $request->validate(['institution_name' => 'required|string|max:200']);
        $edu = ProviderEducation::create([
            'agency_id' => $request->user()->agency_id,
            'provider_id' => $providerId,
            ...$request->only([
                'institution_name', 'degree', 'field_of_study', 'education_type',
                'start_date', 'end_date', 'graduation_date', 'is_completed', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $edu], 201);
    }

    public function updateEducation(Request $request, int $providerId, int $id): JsonResponse
    {
        $edu = ProviderEducation::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id);
        $edu->update($request->all());
        return response()->json(['success' => true, 'data' => $edu]);
    }

    public function destroyEducation(Request $request, int $providerId, int $id): JsonResponse
    {
        ProviderEducation::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Board Certifications ──
    public function boards(Request $request, int $providerId): JsonResponse
    {
        $data = BoardCertification::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->orderByDesc('initial_certification_date')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeBoard(Request $request, int $providerId): JsonResponse
    {
        $request->validate(['board_name' => 'required|string', 'specialty' => 'required|string']);
        $cert = BoardCertification::create([
            'agency_id' => $request->user()->agency_id,
            'provider_id' => $providerId,
            ...$request->only([
                'board_name', 'specialty', 'certificate_number',
                'initial_certification_date', 'expiration_date', 'recertification_date',
                'status', 'is_lifetime', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $cert], 201);
    }

    public function updateBoard(Request $request, int $providerId, int $id): JsonResponse
    {
        $cert = BoardCertification::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id);
        $cert->update($request->all());
        return response()->json(['success' => true, 'data' => $cert]);
    }

    public function destroyBoard(Request $request, int $providerId, int $id): JsonResponse
    {
        BoardCertification::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Work History ──
    public function workHistory(Request $request, int $providerId): JsonResponse
    {
        $data = ProviderWorkHistory::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->orderByDesc('start_date')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeWorkHistory(Request $request, int $providerId): JsonResponse
    {
        $request->validate(['employer_name' => 'required|string|max:200']);
        $wh = ProviderWorkHistory::create([
            'agency_id' => $request->user()->agency_id,
            'provider_id' => $providerId,
            ...$request->only([
                'employer_name', 'position_title', 'department', 'start_date',
                'end_date', 'is_current', 'city', 'state', 'supervisor_name',
                'supervisor_phone', 'reason_for_leaving', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $wh], 201);
    }

    public function updateWorkHistory(Request $request, int $providerId, int $id): JsonResponse
    {
        $wh = ProviderWorkHistory::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id);
        $wh->update($request->all());
        return response()->json(['success' => true, 'data' => $wh]);
    }

    public function destroyWorkHistory(Request $request, int $providerId, int $id): JsonResponse
    {
        ProviderWorkHistory::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── CME / Continuing Education ──
    public function cme(Request $request, int $providerId): JsonResponse
    {
        $data = ProviderCme::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->orderByDesc('completion_date')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeCme(Request $request, int $providerId): JsonResponse
    {
        $request->validate(['course_name' => 'required|string|max:200']);
        $cme = ProviderCme::create([
            'agency_id' => $request->user()->agency_id,
            'provider_id' => $providerId,
            ...$request->only([
                'course_name', 'provider_org', 'credit_hours', 'credit_type',
                'completion_date', 'expiration_date', 'certificate_number', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $cme], 201);
    }

    public function updateCme(Request $request, int $providerId, int $id): JsonResponse
    {
        $cme = ProviderCme::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id);
        $cme->update($request->all());
        return response()->json(['success' => true, 'data' => $cme]);
    }

    public function destroyCme(Request $request, int $providerId, int $id): JsonResponse
    {
        ProviderCme::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── References ──
    public function references(Request $request, int $providerId): JsonResponse
    {
        $data = ProviderReference::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeReference(Request $request, int $providerId): JsonResponse
    {
        $request->validate(['reference_name' => 'required|string|max:200']);
        $ref = ProviderReference::create([
            'agency_id' => $request->user()->agency_id,
            'provider_id' => $providerId,
            ...$request->only([
                'reference_name', 'reference_title', 'reference_organization',
                'relationship', 'phone', 'email', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $ref], 201);
    }

    public function updateReference(Request $request, int $providerId, int $id): JsonResponse
    {
        $ref = ProviderReference::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id);
        $ref->update($request->all());
        return response()->json(['success' => true, 'data' => $ref]);
    }

    public function destroyReference(Request $request, int $providerId, int $id): JsonResponse
    {
        ProviderReference::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Documents ──
    public function documents(Request $request, int $providerId): JsonResponse
    {
        $data = ProviderDocument::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->orderBy('document_type')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeDocument(Request $request, int $providerId): JsonResponse
    {
        $request->validate([
            'document_type' => 'required|string',
            'document_name' => 'required|string|max:200',
        ]);
        $doc = ProviderDocument::create([
            'agency_id' => $request->user()->agency_id,
            'provider_id' => $providerId,
            ...$request->only([
                'document_type', 'document_name', 'file_url', 'status',
                'received_date', 'expiration_date', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $doc], 201);
    }

    public function updateDocument(Request $request, int $providerId, int $id): JsonResponse
    {
        $doc = ProviderDocument::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id);
        $doc->update($request->all());
        return response()->json(['success' => true, 'data' => $doc]);
    }

    public function destroyDocument(Request $request, int $providerId, int $id): JsonResponse
    {
        ProviderDocument::where('agency_id', $request->user()->agency_id)
            ->where('provider_id', $providerId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── Full Provider Profile (aggregate) ──
    public function fullProfile(Request $request, int $providerId): JsonResponse
    {
        $agencyId = $request->user()->agency_id;
        $where = fn($q) => $q->where('agency_id', $agencyId)->where('provider_id', $providerId);

        return response()->json([
            'success' => true,
            'data' => [
                'education' => ProviderEducation::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
                'board_certifications' => BoardCertification::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
                'malpractice' => MalpracticePolicy::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
                'work_history' => ProviderWorkHistory::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
                'cme' => ProviderCme::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
                'references' => ProviderReference::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
                'documents' => ProviderDocument::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
            ],
        ]);
    }
}
