<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Followup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Application::with(['provider', 'payer', 'organization']);
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('state')) $query->where('state', $request->state);
        if ($request->has('wave')) $query->where('wave', $request->wave);
        if ($request->has('provider_id')) $query->where('provider_id', $request->provider_id);
        if ($request->has('payer_id')) $query->where('payer_id', $request->payer_id);
        return response()->json(['success' => true, 'data' => $query->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => 'required|exists:providers,id',
            'organization_id' => 'nullable|exists:organizations,id',
            'payer_id' => 'required|exists:payers,id',
            'payer_plan_id' => 'nullable|exists:payer_plans,id',
            'payer_name' => 'nullable|string', 'state' => 'required|string|max:5',
            'type' => 'in:individual,group,both', 'wave' => 'integer|min:1|max:5',
            'status' => 'in:not_started,submitted,in_review,pending_info,approved,denied,withdrawn',
            'portal_url' => 'nullable|string', 'application_ref' => 'nullable|string',
            'enrollment_id' => 'nullable|string',
            'submitted_date' => 'nullable|date', 'received_date' => 'nullable|date',
            'effective_date' => 'nullable|date', 'denial_reason' => 'nullable|string',
            'est_monthly_revenue' => 'nullable|numeric',
            'payer_contact_name' => 'nullable|string', 'payer_contact_phone' => 'nullable|string',
            'payer_contact_email' => 'nullable|email', 'notes' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        return response()->json(['success' => true, 'data' => Application::create($data)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Application::with(['provider', 'payer', 'organization', 'followups', 'activityLogs'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $app = Application::findOrFail($id);
        $app->update($request->only([
            'provider_id', 'organization_id', 'payer_id', 'payer_plan_id', 'payer_name',
            'state', 'type', 'wave', 'status', 'portal_url', 'application_ref', 'enrollment_id',
            'submitted_date', 'received_date', 'effective_date', 'denial_reason',
            'est_monthly_revenue', 'payer_contact_name', 'payer_contact_phone',
            'payer_contact_email', 'notes', 'tags',
        ]));
        return response()->json(['success' => true, 'data' => $app]);
    }

    public function destroy(int $id): JsonResponse
    {
        Application::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'new_status' => 'required|in:' . implode(',', Application::STATUSES),
            'notes' => 'nullable|string',
        ]);

        $app = Application::findOrFail($id);
        $oldStatus = $app->status;

        if (!$app->canTransitionTo($request->new_status)) {
            return response()->json([
                'error' => "Cannot transition from '{$oldStatus}' to '{$request->new_status}'",
            ], 422);
        }

        $app->update(['status' => $request->new_status]);

        // Auto-fill dates
        if ($request->new_status === 'submitted' && !$app->submitted_date) {
            $app->update(['submitted_date' => now()]);
        }
        if ($request->new_status === 'approved' && !$app->effective_date) {
            $app->update(['effective_date' => now()]);
        }

        // Log the transition
        ActivityLog::create([
            'agency_id' => $app->agency_id,
            'application_id' => $app->id,
            'type' => 'status_change',
            'logged_date' => now(),
            'status_from' => $oldStatus,
            'status_to' => $request->new_status,
            'outcome' => $request->notes,
            'created_by' => auth()->id(),
        ]);

        // Auto-schedule followup for submitted apps
        if ($request->new_status === 'submitted') {
            Followup::create([
                'agency_id' => $app->agency_id,
                'application_id' => $app->id,
                'type' => 'status_check',
                'due_date' => now()->addDays(14),
                'method' => 'phone',
            ]);
        }

        return response()->json(['success' => true, 'data' => $app->fresh()]);
    }

    public function stats(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;

        $byStatus = Application::where('agency_id', $agencyId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')->pluck('count', 'status');

        $byWave = Application::where('agency_id', $agencyId)
            ->selectRaw('wave, count(*) as count')
            ->groupBy('wave')->pluck('count', 'wave');

        $byState = Application::where('agency_id', $agencyId)
            ->selectRaw('state, count(*) as count')
            ->groupBy('state')->pluck('count', 'state');

        $totalRevenue = Application::where('agency_id', $agencyId)
            ->where('status', 'approved')
            ->sum('est_monthly_revenue');

        $overdueFollowups = Followup::where('agency_id', $agencyId)
            ->overdue()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'by_status' => $byStatus,
                'by_wave' => $byWave,
                'by_state' => $byState,
                'total_approved_revenue' => $totalRevenue,
                'overdue_followups' => $overdueFollowups,
                'total' => Application::where('agency_id', $agencyId)->count(),
            ],
        ]);
    }
}
