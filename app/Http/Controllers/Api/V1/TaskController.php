<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::with(['application', 'assignee']);
        if ($request->has('category')) $query->where('category', $request->category);
        if ($request->has('priority')) $query->where('priority', $request->priority);
        if ($request->has('completed')) $query->where('is_completed', $request->boolean('completed'));
        if ($request->has('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        return response()->json(['success' => true, 'data' => $query->orderBy('due_date')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        // The V2 frontend sends applicationId / providerId (camelCase), which
        // the API layer's camelToSnake converter rewrites to application_id /
        // provider_id. We accept both names below — the older
        // linked_application_id is kept for any direct API callers.
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'category' => 'nullable|string|max:50',
            'priority' => 'in:urgent,high,normal,low',
            'due_date' => 'nullable|date',
            'linked_application_id' => 'nullable|exists:applications,id',
            'application_id'        => 'nullable|exists:applications,id',
            'provider_id'           => 'nullable|exists:providers,id',
            'recurrence' => 'nullable|in:daily,weekly,biweekly,monthly,quarterly',
            'notes' => 'nullable|string', 'assigned_to' => 'nullable|exists:users,id',
            // Allow callers that already know about the morph pair.
            'linkable_type' => 'nullable|string|max:32',
            'linkable_id'   => 'nullable|integer|min:1',
        ]);

        // Reconcile the three input shapes the FE / direct callers might send.
        // application_id (new FE) and linked_application_id (legacy) both flow
        // into the same column. provider_id maps onto the polymorphic morph
        // pair so the task surfaces under that provider in any future
        // provider-detail Tasks panel.
        if (!empty($data['application_id']) && empty($data['linked_application_id'])) {
            $data['linked_application_id'] = $data['application_id'];
        }
        if (!empty($data['provider_id']) && empty($data['linkable_type'])) {
            $data['linkable_type'] = 'provider';
            $data['linkable_id'] = $data['provider_id'];
        }
        unset($data['application_id'], $data['provider_id']);

        return response()->json(['success' => true, 'data' => Task::create($data)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $request->validate([
            'title' => 'sometimes|string|max:500',
            'category' => 'sometimes|nullable|string|max:50',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'due_date' => 'sometimes|nullable|date',
            'linked_application_id' => 'sometimes|nullable|integer',
            'recurrence' => 'sometimes|nullable|in:daily,weekly,biweekly,monthly,quarterly',
            'notes' => 'sometimes|nullable|string',
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
            'is_completed' => 'sometimes|boolean',
            'completed_at' => 'sometimes|nullable|date',
        ]);
        $data = $request->only([
            'title', 'category', 'priority', 'due_date',
            'linked_application_id', 'recurrence', 'notes', 'assigned_to',
            'is_completed', 'completed_at',
        ]);
        foreach (['due_date', 'completed_at'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $task->update($data);
        return response()->json(['success' => true, 'data' => $task]);
    }

    public function complete(int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $task->update(['is_completed' => true, 'completed_at' => now()]);
        return response()->json(['success' => true, 'data' => $task]);
    }

    public function destroy(int $id): JsonResponse
    {
        Task::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
