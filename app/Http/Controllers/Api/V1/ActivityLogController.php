<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('creator');
        if ($request->has('application_id')) $query->where('application_id', $request->application_id);
        return response()->json(['success' => true, 'data' => $query->orderBy('created_at', 'desc')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'type' => 'required|in:call,email,portal_check,status_change,note,document',
            'logged_date' => 'required|date',
            'contact_name' => 'nullable|string', 'contact_phone' => 'nullable|string',
            'ref_number' => 'nullable|string', 'outcome' => 'nullable|string',
            'next_step' => 'nullable|string',
            'status_from' => 'nullable|string', 'status_to' => 'nullable|string',
        ]);

        $data['created_by'] = auth()->id();
        return response()->json(['success' => true, 'data' => ActivityLog::create($data)], 201);
    }
}
