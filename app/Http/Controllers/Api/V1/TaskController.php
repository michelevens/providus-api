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
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'category' => 'nullable|in:credentialing,licensing,followup,document,audit,other',
            'priority' => 'in:urgent,high,normal,low',
            'due_date' => 'nullable|date',
            'linked_application_id' => 'nullable|exists:applications,id',
            'recurrence' => 'nullable|in:daily,weekly,biweekly,monthly,quarterly',
            'notes' => 'nullable|string', 'assigned_to' => 'nullable|exists:users,id',
        ]);

        return response()->json(['success' => true, 'data' => Task::create($data)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
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
