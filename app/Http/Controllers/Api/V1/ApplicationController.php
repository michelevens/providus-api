<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationStatusChange;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Followup;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
        if ($request->has('organization_id')) $query->where('organization_id', $request->organization_id);
        return response()->json(['success' => true, 'data' => $query->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->only([
            'provider_id', 'organization_id', 'payer_id', 'payer_plan_id',
            'payer_name', 'state', 'type', 'wave', 'status',
            'portal_url', 'application_ref', 'enrollment_id',
            'submitted_date', 'received_date', 'effective_date', 'denial_reason',
            'est_monthly_revenue', 'payer_contact_name', 'payer_contact_phone',
            'payer_contact_email', 'notes', 'tags', 'document_checklist',
        ]);
        // Sanitize empty strings to null
        foreach ($data as $k => $v) { if ($v === '') $data[$k] = null; }
        if (empty($data['provider_id']) || empty($data['state'])) {
            return response()->json(['success' => false, 'message' => 'provider_id and state are required'], 422);
        }

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
        $request->validate([
            'provider_id' => 'sometimes|integer',
            'organization_id' => 'sometimes|nullable|integer',
            'payer_id' => 'sometimes|nullable|integer',
            'payer_plan_id' => 'sometimes|nullable|integer',
            'payer_name' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:2',
            'type' => 'sometimes|nullable|string|max:50',
            'wave' => 'sometimes|nullable|string|max:50',
            'status' => 'sometimes|nullable|string|max:50',
            'portal_url' => 'sometimes|nullable|url|max:500',
            'application_ref' => 'sometimes|nullable|string|max:100',
            'enrollment_id' => 'sometimes|nullable|string|max:100',
            'submitted_date' => 'sometimes|nullable|date',
            'received_date' => 'sometimes|nullable|date',
            'effective_date' => 'sometimes|nullable|date',
            'denial_reason' => 'sometimes|nullable|string',
            'est_monthly_revenue' => 'sometimes|nullable|numeric|min:0',
            'payer_contact_name' => 'sometimes|nullable|string|max:200',
            'payer_contact_phone' => 'sometimes|nullable|string|max:20',
            'payer_contact_email' => 'sometimes|nullable|email|max:200',
            'notes' => 'sometimes|nullable|string',
            'tags' => 'sometimes|nullable|array',
        ]);
        $data = $request->only([
            'provider_id', 'organization_id', 'payer_id', 'payer_plan_id', 'payer_name',
            'state', 'type', 'wave', 'status', 'portal_url', 'application_ref', 'enrollment_id',
            'submitted_date', 'received_date', 'effective_date', 'denial_reason',
            'est_monthly_revenue', 'payer_contact_name', 'payer_contact_phone',
            'payer_contact_email', 'notes', 'tags',
        ]);
        foreach (['submitted_date', 'received_date', 'effective_date'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $app->update($data);
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
                'success' => false, 'message' => "Cannot transition from '{$oldStatus}' to '{$request->new_status}'",
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

        // Notify of status change
        NotificationService::send($app->agency_id, 'app_status', "Application status: {$request->new_status}", [
            'body' => "Changed from {$oldStatus} to {$request->new_status}" . ($request->notes ? " — {$request->notes}" : ''),
            'link' => "applications/{$app->id}",
            'linkable_type' => 'application',
            'linkable_id' => $app->id,
        ]);

        // Email notification for status change
        $admins = User::where('agency_id', $app->agency_id)
            ->whereIn('role', ['owner', 'agency'])
            ->where('is_active', true)
            ->get();
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new ApplicationStatusChange(
                    $app,
                    $app->provider?->full_name ?? 'Unknown',
                    $app->payer?->name ?? ($app->payer_name ?? 'Unknown'),
                    $oldStatus,
                    $request->new_status,
                ));
            } catch (\Throwable $e) {
                \Log::warning("Status change email failed: {$e->getMessage()}");
            }
        }

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
