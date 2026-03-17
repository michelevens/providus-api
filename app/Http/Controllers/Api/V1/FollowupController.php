<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Followup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Followup::with('application.payer');
        if ($request->has('application_id')) $query->where('application_id', $request->application_id);
        if ($request->boolean('pending')) $query->pending();
        return response()->json(['success' => true, 'data' => $query->orderBy('due_date')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_id' => 'required|exists:applications,id',
            'type' => 'required|in:status_check,document_request,info_response,escalation,general',
            'due_date' => 'required|date', 'completed_date' => 'nullable|date',
            'method' => 'nullable|in:phone,email,portal,fax',
            'contact_name' => 'nullable|string', 'contact_phone' => 'nullable|string',
            'contact_email' => 'nullable|email', 'outcome' => 'nullable|string',
            'next_action' => 'nullable|string',
        ]);

        return response()->json(['success' => true, 'data' => Followup::create($data)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $followup = Followup::findOrFail($id);
        $request->validate([
            'type' => 'sometimes|in:status_check,document_request,info_response,escalation,general',
            'due_date' => 'sometimes|date',
            'completed_date' => 'sometimes|nullable|date',
            'method' => 'sometimes|nullable|in:phone,email,portal,fax',
            'contact_name' => 'sometimes|nullable|string|max:200',
            'contact_phone' => 'sometimes|nullable|string|max:20',
            'contact_email' => 'sometimes|nullable|email|max:200',
            'outcome' => 'sometimes|nullable|string',
            'next_action' => 'sometimes|nullable|string',
        ]);
        $data = $request->only([
            'type', 'due_date', 'completed_date', 'method',
            'contact_name', 'contact_phone', 'contact_email', 'outcome', 'next_action',
        ]);
        foreach (['due_date', 'completed_date'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $followup->update($data);
        return response()->json(['success' => true, 'data' => $followup]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $followup = Followup::findOrFail($id);
        $request->validate(['outcome' => 'nullable|string']);
        $followup->update([
            'completed_date' => now(),
            'outcome' => $request->input('outcome', $followup->outcome),
        ]);
        return response()->json(['success' => true, 'data' => $followup]);
    }

    public function destroy(int $id): JsonResponse
    {
        Followup::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function overdue(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Followup::with('application.payer')->overdue()->get()]);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);
        return response()->json(['success' => true, 'data' => Followup::with('application.payer')->upcoming($days)->get()]);
    }
}
