<?php

// Activity log — append-only timeline entries for an entity.
//
// Polymorphic: subject_type + subject_id is the canonical link, replacing
// the original application_id-only link. Currently understood subject
// types: 'application', 'claim', 'provider'.
//
// Reads accept either (subject_type + subject_id) OR the legacy
// ?application_id=X shim so V1 callers and existing analytics queries
// keep working through the migration window.

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    private const SUBJECT_TYPES = ['application', 'claim', 'provider'];

    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('creator');

        // Polymorphic filter (preferred).
        if ($request->filled('subject_type') && $request->filled('subject_id')) {
            $query->where('subject_type', $request->subject_type)
                  ->where('subject_id', $request->subject_id);
        }
        // Legacy shim — equivalent to subject_type=application.
        elseif ($request->filled('application_id')) {
            $query->where('subject_type', 'application')
                  ->where('subject_id', $request->application_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Preferred input: subject_type + subject_id together.
            'subject_type' => ['nullable', Rule::in(self::SUBJECT_TYPES)],
            'subject_id'   => 'nullable|integer|min:1',
            // Legacy shim: application_id alone implies subject_type=application.
            'application_id' => 'nullable|integer|exists:applications,id',
            'type'           => 'required|in:call,email,portal_check,status_change,note,document,activity',
            'logged_date'    => 'required|date',
            'contact_name'   => 'nullable|string',
            'contact_phone'  => 'nullable|string',
            'ref_number'     => 'nullable|string',
            'outcome'        => 'nullable|string',
            'next_step'      => 'nullable|string',
            'status_from'    => 'nullable|string',
            'status_to'      => 'nullable|string',
        ]);

        // Reconcile the two input shapes. If subject_type/id were
        // provided, use them. Otherwise fall back to application_id.
        if (!empty($data['subject_type']) && !empty($data['subject_id'])) {
            // Confirm the referenced row actually exists for the type.
            $this->assertSubjectExists($data['subject_type'], (int) $data['subject_id']);
            // Keep application_id populated when the subject IS an
            // application — preserves any V1 reporting that still
            // groups on that column.
            if ($data['subject_type'] === 'application' && empty($data['application_id'])) {
                $data['application_id'] = $data['subject_id'];
            }
        } elseif (!empty($data['application_id'])) {
            $data['subject_type'] = 'application';
            $data['subject_id'] = (int) $data['application_id'];
        } else {
            return response()->json([
                'success' => false,
                'message' => 'subject_type+subject_id or application_id is required',
            ], 422);
        }

        $data['created_by'] = auth()->id();
        $log = ActivityLog::create($data);

        return response()->json(['success' => true, 'data' => $log], 201);
    }

    /** Cheap existence check by table — keeps the controller free of
     *  per-type imports. Throws ModelNotFoundException-equivalent 422. */
    private function assertSubjectExists(string $type, int $id): void
    {
        $tableMap = [
            'application' => 'applications',
            'claim'       => 'claims',
            'provider'    => 'providers',
        ];
        $table = $tableMap[$type] ?? null;
        if (!$table) {
            abort(422, "Unknown subject_type: {$type}");
        }
        $exists = \DB::table($table)->where('id', $id)->exists();
        if (!$exists) {
            abort(422, "{$type} #{$id} not found");
        }
    }
}
