<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyConfig;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ── Platform Stats ─────────────────────────────────────────

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'agencies' => Agency::count(),
                'agencies_active' => Agency::where('is_active', true)->count(),
                'users' => User::count(),
                'users_active' => User::where('is_active', true)->count(),
                'providers' => Provider::count(),
                'licenses' => License::count(),
                'applications' => Application::count(),
                'applications_by_status' => Application::selectRaw('status, count(*) as count')
                    ->groupBy('status')->pluck('count', 'status'),
                'agencies_by_plan' => Agency::selectRaw('plan_tier, count(*) as count')
                    ->groupBy('plan_tier')->pluck('count', 'plan_tier'),
            ],
        ]);
    }

    // ── Agencies ───────────────────────────────────────────────

    public function agencies(Request $request): JsonResponse
    {
        $query = Agency::withCount(['users', 'organizations', 'providers', 'applications', 'licenses'])
            ->with('config:id,agency_id,notification_email');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($request->has('plan')) {
            $query->where('plan_tier', $request->plan);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function agencyShow(int $id): JsonResponse
    {
        $agency = Agency::withCount([
            'users', 'organizations', 'providers',
            'applications', 'licenses', 'tasks', 'followups',
        ])
        ->with([
            'config',
            'users:id,agency_id,first_name,last_name,email,role,is_active,last_login_at,created_at',
        ])
        ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $agency]);
    }

    public function agencyUpdate(Request $request, int $id): JsonResponse
    {
        $agency = Agency::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'plan_tier' => 'sometimes|string|in:free,basic,professional,enterprise',
            'is_active' => 'sometimes|boolean',
            'primary_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);
        $agency->update($request->only([
            'name', 'plan_tier', 'is_active',
            'primary_color', 'accent_color',
        ]));

        return response()->json(['success' => true, 'data' => $agency]);
    }

    // ── Agency Users (cross-agency) ───────────────────────────

    public function agencyUsers(int $id): JsonResponse
    {
        $users = User::where('agency_id', $id)
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'agency' THEN 2 WHEN 'organization' THEN 3 WHEN 'provider' THEN 4 ELSE 5 END")
            ->get(['id', 'agency_id', 'first_name', 'last_name', 'email', 'role', 'is_active', 'last_login_at', 'created_at']);

        return response()->json(['success' => true, 'data' => $users]);
    }

    // ── Impersonate ───────────────────────────────────────────

    public function impersonate(Request $request, int $agencyId): JsonResponse
    {
        $agency = Agency::findOrFail($agencyId);
        $superadmin = $request->user();

        // Create a scoped token that the superadmin can use
        // with the X-Agency-Id header for tenant scoping
        return response()->json([
            'success' => true,
            'data' => [
                'agency_id' => $agency->id,
                'agency_name' => $agency->name,
                'agency_slug' => $agency->slug,
                'instruction' => 'Add header X-Agency-Id: ' . $agency->id . ' to scope all requests to this agency.',
            ],
        ]);
    }

    // ── All Users (platform-wide) ─────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $query = User::with('agency:id,name,slug');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ilike', "%{$search}%")
                  ->orWhere('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function userUpdate(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'is_active' => 'sometimes|boolean',
            'role' => 'sometimes|string|in:owner,agency,organization,provider,superadmin',
        ]);
        $data = $request->only(['is_active', 'role']);

        // Cannot demote another superadmin
        if ($user->role === 'superadmin' && isset($data['role']) && $data['role'] !== 'superadmin') {
            return response()->json(['error' => 'Cannot demote a superadmin via API'], 403);
        }

        $user->update($data);

        return response()->json(['success' => true, 'data' => $user]);
    }

    // ── Activity Log ─────────────────────────────────────────────

    public function auditLog(Request $request): JsonResponse
    {
        $query = \App\Models\ActivityLog::with([
            'application:id,agency_id,provider_id,payer_name,state',
            'application.provider:id,first_name,last_name',
        ]);

        if ($request->has('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('created_at', 'desc')->limit(100)->get(),
        ]);
    }

    // ── Audit Logs (model-level change tracking) ──────────────

    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate([
            'agency_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'action' => 'nullable|string|in:created,updated,deleted,restored',
            'auditable_type' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $query = AuditLog::query();

        if (!$request->user()->isSuperAdmin()) {
            $query->where('agency_id', $request->user()->agency_id);
        } elseif ($agencyId = $request->input('agency_id')) {
            $query->where('agency_id', $agencyId);
        }

        if ($userId = $request->input('user_id')) $query->where('user_id', $userId);
        if ($action = $request->input('action')) $query->where('action', $action);
        if ($type = $request->input('auditable_type')) $query->where('auditable_type', $type);
        if ($from = $request->input('from')) $query->where('created_at', '>=', $from);
        if ($to = $request->input('to')) $query->where('created_at', '<=', $to);

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate(50),
        ]);
    }
}
