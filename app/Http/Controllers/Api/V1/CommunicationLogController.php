<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CommunicationLog::where('agency_id', $request->user()->agency_id)
            ->with('creator:id,first_name,last_name')
            ->orderByDesc('created_at');

        if ($request->application_id) {
            $query->where('application_id', $request->application_id);
        }
        if ($request->provider_id) {
            $query->where('provider_id', $request->provider_id);
        }
        if ($request->channel) {
            $query->where('channel', $request->channel);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'application_id' => 'nullable|exists:applications,id',
            'provider_id' => 'nullable|exists:providers,id',
            'direction' => 'required|in:inbound,outbound',
            'channel' => 'required|in:email,phone,fax,portal,mail',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:5000',
            'contact_name' => 'nullable|string|max:100',
            'contact_info' => 'nullable|string|max:200',
            'outcome' => 'nullable|in:connected,voicemail,no_answer,sent,received,bounced',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $log = CommunicationLog::create([
            ...$request->only([
                'application_id', 'provider_id', 'direction', 'channel',
                'subject', 'body', 'contact_name', 'contact_info',
                'outcome', 'duration_seconds',
            ]),
            'agency_id' => $request->user()->agency_id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $log], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $log = CommunicationLog::where('agency_id', $request->user()->agency_id)
            ->with('creator:id,first_name,last_name')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $log]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $log = CommunicationLog::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $log->delete();

        return response()->json(['success' => true]);
    }
}
