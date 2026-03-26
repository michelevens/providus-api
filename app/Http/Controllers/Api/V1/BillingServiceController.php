<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingActivity;
use App\Models\BillingClient;
use App\Models\BillingFinancial;
use App\Models\BillingTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingServiceController extends Controller
{
    // ── Billing Clients ──

    public function clients(Request $request): JsonResponse
    {
        $clients = BillingClient::where('agency_id', $request->user()->agency_id)
            ->with(['organization:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $clients]);
    }

    public function showClient(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->agency_id)
            ->with(['organization:id,name', 'tasks', 'activities', 'financials'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $client]);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $request->validate([
            'organization_name' => 'required|string|max:200',
            'organization_id' => 'nullable|exists:organizations,id',
            'contact_name' => 'nullable|string|max:200',
            'contact_email' => 'nullable|email|max:200',
            'contact_phone' => 'nullable|string|max:30',
            'billing_platform' => 'nullable|string|max:50',
            'monthly_fee' => 'nullable|numeric|min:0',
            'fee_structure' => 'nullable|in:flat,per_provider,percentage,per_claim',
            'status' => 'nullable|in:onboarding,active,paused,cancelled',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $client = BillingClient::create([
            'agency_id' => $request->user()->agency_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'organization_id', 'organization_name',
                'contact_name', 'contact_email', 'contact_phone',
                'billing_platform', 'monthly_fee', 'fee_structure',
                'status', 'start_date', 'notes',
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $client], 201);
    }

    public function updateClient(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'organization_name' => 'sometimes|string|max:200',
            'organization_id' => 'nullable|exists:organizations,id',
            'contact_name' => 'nullable|string|max:200',
            'contact_email' => 'nullable|email|max:200',
            'contact_phone' => 'nullable|string|max:30',
            'billing_platform' => 'nullable|string|max:50',
            'monthly_fee' => 'nullable|numeric|min:0',
            'fee_structure' => 'nullable|in:flat,per_provider,percentage,per_claim',
            'status' => 'nullable|in:onboarding,active,paused,cancelled',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $client->update($request->only([
            'organization_id', 'organization_name',
            'contact_name', 'contact_email', 'contact_phone',
            'billing_platform', 'monthly_fee', 'fee_structure',
            'status', 'start_date', 'notes',
        ]));

        return response()->json(['success' => true, 'data' => $client]);
    }

    public function destroyClient(Request $request, int $id): JsonResponse
    {
        $client = BillingClient::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $client->delete();
        return response()->json(['success' => true]);
    }

    public function clientStats(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;

        $totalClients = BillingClient::where('agency_id', $agencyId)->count();
        $activeClients = BillingClient::where('agency_id', $agencyId)->where('status', 'active')->count();
        $totalTasks = BillingTask::where('agency_id', $agencyId)->count();
        $pendingTasks = BillingTask::where('agency_id', $agencyId)->whereIn('status', ['pending', 'in_progress'])->count();
        $completedTasks = BillingTask::where('agency_id', $agencyId)->where('status', 'completed')->count();
        $totalClaims = BillingFinancial::where('agency_id', $agencyId)->sum('claims_submitted');
        $totalCollected = BillingFinancial::where('agency_id', $agencyId)->sum('amount_collected');
        $totalDenied = BillingFinancial::where('agency_id', $agencyId)->sum('denied_amount');

        return response()->json(['success' => true, 'data' => [
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
            'total_tasks' => $totalTasks,
            'pending_tasks' => $pendingTasks,
            'completed_tasks' => $completedTasks,
            'total_claims' => $totalClaims,
            'total_collected' => $totalCollected,
            'total_denied' => $totalDenied,
        ]]);
    }

    // ── Billing Tasks ──

    public function tasks(Request $request): JsonResponse
    {
        $query = BillingTask::where('agency_id', $request->user()->agency_id)
            ->with(['billingClient:id,organization_name']);

        if ($clientId = $request->input('billing_client_id')) {
            $query->where('billing_client_id', $clientId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $tasks = $query->orderByRaw("CASE WHEN status IN ('pending','in_progress') THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('due_date')
            ->get();

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function storeTask(Request $request): JsonResponse
    {
        $request->validate([
            'billing_client_id' => 'required|exists:billing_clients,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider_name' => 'nullable|string|max:200',
            'category' => 'nullable|in:charge_entry,claim_submission,claim_followup,denial_management,payment_posting,eligibility_verification,patient_billing,reporting,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,on_hold,cancelled',
            'due_date' => 'nullable|date',
        ]);

        $task = BillingTask::create([
            'agency_id' => $request->user()->agency_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'title', 'description',
                'provider_name', 'category', 'priority', 'status', 'due_date',
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $task->load('billingClient:id,organization_name')], 201);
    }

    public function updateTask(Request $request, int $id): JsonResponse
    {
        $task = BillingTask::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'provider_name' => 'nullable|string|max:200',
            'category' => 'nullable|in:charge_entry,claim_submission,claim_followup,denial_management,payment_posting,eligibility_verification,patient_billing,reporting,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,on_hold,cancelled',
            'due_date' => 'nullable|date',
        ]);

        $data = $request->only([
            'title', 'description', 'provider_name',
            'category', 'priority', 'status', 'due_date',
        ]);

        // Auto-set completed_at when marking completed
        if (($data['status'] ?? null) === 'completed' && !$task->completed_at) {
            $data['completed_at'] = now();
        }

        $task->update($data);
        return response()->json(['success' => true, 'data' => $task]);
    }

    public function destroyTask(Request $request, int $id): JsonResponse
    {
        $task = BillingTask::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $task->delete();
        return response()->json(['success' => true]);
    }

    // ── Billing Activities ──

    public function activities(Request $request): JsonResponse
    {
        $query = BillingActivity::where('agency_id', $request->user()->agency_id)
            ->with(['billingClient:id,organization_name', 'creator:id,first_name,last_name']);

        if ($clientId = $request->input('billing_client_id')) {
            $query->where('billing_client_id', $clientId);
        }
        if ($type = $request->input('activity_type')) {
            $query->where('activity_type', $type);
        }

        $limit = min((int) ($request->input('limit') ?: 50), 200);
        $activities = $query->orderByDesc('activity_date')->orderByDesc('created_at')->limit($limit)->get();

        // Add user_name for convenience
        $activities->each(function ($a) {
            $a->user_name = $a->creator
                ? trim(($a->creator->first_name ?? '') . ' ' . ($a->creator->last_name ?? ''))
                : null;
        });

        return response()->json(['success' => true, 'data' => $activities]);
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $request->validate([
            'billing_client_id' => 'required|exists:billing_clients,id',
            'activity_type' => 'required|in:claim_submitted,claim_followup,denial_worked,payment_posted,eligibility_check,report_generated,note',
            'provider_name' => 'nullable|string|max:200',
            'payer_name' => 'nullable|string|max:200',
            'activity_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'reference' => 'nullable|string|max:200',
            'notes' => 'required|string',
        ]);

        $activity = BillingActivity::create([
            'agency_id' => $request->user()->agency_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'activity_type',
                'provider_name', 'payer_name', 'activity_date',
                'amount', 'quantity', 'reference', 'notes',
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $activity], 201);
    }

    public function updateActivity(Request $request, int $id): JsonResponse
    {
        $activity = BillingActivity::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $activity->update($request->only([
            'activity_type', 'provider_name', 'payer_name',
            'activity_date', 'amount', 'quantity', 'reference', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $activity]);
    }

    public function destroyActivity(Request $request, int $id): JsonResponse
    {
        $activity = BillingActivity::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $activity->delete();
        return response()->json(['success' => true]);
    }

    // ── Billing Financials ──

    public function financials(Request $request): JsonResponse
    {
        $query = BillingFinancial::where('agency_id', $request->user()->agency_id)
            ->with(['billingClient:id,organization_name']);

        if ($clientId = $request->input('billing_client_id')) {
            $query->where('billing_client_id', $clientId);
        }

        $financials = $query->orderByDesc('period')->get();
        return response()->json(['success' => true, 'data' => $financials]);
    }

    public function storeFinancial(Request $request): JsonResponse
    {
        $request->validate([
            'billing_client_id' => 'required|exists:billing_clients,id',
            'period' => 'required|string|size:7', // YYYY-MM
            'claims_submitted' => 'nullable|integer|min:0',
            'amount_billed' => 'nullable|numeric|min:0',
            'amount_collected' => 'nullable|numeric|min:0',
            'denial_count' => 'nullable|integer|min:0',
            'denied_amount' => 'nullable|numeric|min:0',
            'adjustments' => 'nullable|numeric',
            'patient_responsibility' => 'nullable|numeric|min:0',
        ]);

        $financial = BillingFinancial::updateOrCreate(
            [
                'agency_id' => $request->user()->agency_id,
                'billing_client_id' => $request->input('billing_client_id'),
                'period' => $request->input('period'),
            ],
            [
                'created_by' => $request->user()->id,
                ...$request->only([
                    'claims_submitted', 'amount_billed', 'amount_collected',
                    'denial_count', 'denied_amount', 'adjustments', 'patient_responsibility',
                ]),
            ]
        );

        return response()->json(['success' => true, 'data' => $financial], 201);
    }

    public function updateFinancial(Request $request, int $id): JsonResponse
    {
        $financial = BillingFinancial::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $financial->update($request->only([
            'claims_submitted', 'amount_billed', 'amount_collected',
            'denial_count', 'denied_amount', 'adjustments', 'patient_responsibility',
        ]));
        return response()->json(['success' => true, 'data' => $financial]);
    }
}
