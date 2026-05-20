<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClaimDenial;
use App\Models\DenialResubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DenialResubmission CRUD — full appeal-attempt history for a denial.
 *
 * Endpoints (all under /api/rcm/denials/{denialId}/resubmissions):
 *   GET    /           list resubmissions for a denial
 *   POST   /           create new resubmission (advances appeal_level)
 *   PUT    /{id}       update outcome (decision_date, recovered_amount, status)
 *   DELETE /{id}       soft-delete (rare — typically reserved for typos)
 *
 * Creating a resubmission also keeps the parent ClaimDenial summary
 * fields in sync: appeal_level = MAX(attempt_number),
 * appeal_submitted_date = latest submitted_date,
 * status = 'appeal_in_progress' if no decision yet.
 *
 * Updating with a non-zero recovered_amount rolls up to the parent's
 * recovered_amount + may transition status to resolved_won /
 * resolved_partial / resolved_lost based on the denied_amount.
 */
class DenialResubmissionController extends Controller
{
    /**
     * GET /rcm/denials/{denialId}/resubmissions
     */
    public function index(Request $request, int $denialId): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        // findOrFail enforces tenant scope through BelongsToAgency.
        $denial = ClaimDenial::where('agency_id', $agencyId)->findOrFail($denialId);

        $rows = DenialResubmission::where('agency_id', $agencyId)
            ->where('claim_denial_id', $denial->id)
            ->orderBy('attempt_number')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows,
        ]);
    }

    /**
     * POST /rcm/denials/{denialId}/resubmissions
     */
    public function store(Request $request, int $denialId): JsonResponse
    {
        $request->validate([
            'submitted_date'           => 'required|date',
            'submission_method'        => 'nullable|string|max:30',
            'submission_notes'         => 'nullable|string|max:4000',
            'resubmitted_claim_number' => 'nullable|string|max:100',
            'payer_appeal_id'          => 'nullable|string|max:100',
            // Optional initial outcome (rare — usually filled in on
            // a later PUT once payer responds).
            'status'                   => 'nullable|string|in:submitted,awaiting_response,won,partial,denied,abandoned',
            'decision_date'            => 'nullable|date',
            'recovered_amount'         => 'nullable|numeric|min:0',
            'outcome_notes'            => 'nullable|string|max:4000',
            'attachments'              => 'nullable|array',
        ]);

        $agencyId = $request->user()->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        $denial = ClaimDenial::where('agency_id', $agencyId)->findOrFail($denialId);

        $resub = DB::transaction(function () use ($request, $denial, $agencyId) {
            // Next attempt_number — next position in the sequence.
            // We don't gap-fill; if a previous attempt is soft-deleted
            // its number stays "burned" so the audit trail stays coherent.
            $next = (int) (DenialResubmission::where('agency_id', $agencyId)
                ->where('claim_denial_id', $denial->id)
                ->max('attempt_number') ?? 0) + 1;

            $resub = DenialResubmission::create([
                'agency_id'                => $agencyId,
                'claim_denial_id'          => $denial->id,
                'attempt_number'           => $next,
                'status'                   => $request->input('status', 'submitted'),
                'submitted_date'           => $request->submitted_date,
                'submission_method'        => $request->submission_method,
                'submission_notes'         => $request->submission_notes,
                'resubmitted_claim_number' => $request->resubmitted_claim_number,
                'payer_appeal_id'          => $request->payer_appeal_id,
                'decision_date'            => $request->decision_date,
                'recovered_amount'         => $request->input('recovered_amount', 0),
                'outcome_notes'            => $request->outcome_notes,
                'attachments'              => $request->attachments,
                'created_by'               => $request->user()->id,
            ]);

            $this->syncDenialSummary($denial);
            return $resub;
        });

        return response()->json([
            'success' => true,
            'data'    => $resub->fresh(),
        ], 201);
    }

    /**
     * PUT /rcm/denials/{denialId}/resubmissions/{id}
     */
    public function update(Request $request, int $denialId, int $id): JsonResponse
    {
        $request->validate([
            'status'                   => 'sometimes|string|in:submitted,awaiting_response,won,partial,denied,abandoned',
            'submitted_date'           => 'sometimes|date',
            'submission_method'        => 'sometimes|nullable|string|max:30',
            'submission_notes'         => 'sometimes|nullable|string|max:4000',
            'resubmitted_claim_number' => 'sometimes|nullable|string|max:100',
            'payer_appeal_id'          => 'sometimes|nullable|string|max:100',
            'decision_date'            => 'sometimes|nullable|date',
            'recovered_amount'         => 'sometimes|numeric|min:0',
            'outcome_notes'            => 'sometimes|nullable|string|max:4000',
            'attachments'              => 'sometimes|nullable|array',
        ]);

        $agencyId = $request->user()->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        $denial = ClaimDenial::where('agency_id', $agencyId)->findOrFail($denialId);
        $resub  = DenialResubmission::where('agency_id', $agencyId)
            ->where('claim_denial_id', $denial->id)
            ->findOrFail($id);

        DB::transaction(function () use ($request, $resub, $denial) {
            $resub->update($request->only([
                'status', 'submitted_date', 'submission_method', 'submission_notes',
                'resubmitted_claim_number', 'payer_appeal_id',
                'decision_date', 'recovered_amount', 'outcome_notes', 'attachments',
            ]));
            $this->syncDenialSummary($denial);
        });

        return response()->json([
            'success' => true,
            'data'    => $resub->fresh(),
        ]);
    }

    /**
     * DELETE /rcm/denials/{denialId}/resubmissions/{id}
     */
    public function destroy(Request $request, int $denialId, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        $denial = ClaimDenial::where('agency_id', $agencyId)->findOrFail($denialId);
        $resub  = DenialResubmission::where('agency_id', $agencyId)
            ->where('claim_denial_id', $denial->id)
            ->findOrFail($id);

        DB::transaction(function () use ($resub, $denial) {
            $resub->delete();
            $this->syncDenialSummary($denial);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Recompute the parent ClaimDenial summary fields from the child
     * resubmission rows. Called inside the same transaction as any
     * create / update / delete so the summary is always coherent.
     *
     * - appeal_level         = MAX(attempt_number) over surviving rows
     * - appeal_submitted_date = latest submitted_date
     * - recovered_amount     = SUM(recovered_amount)
     * - status               = derived from rollup vs denied_amount
     */
    private function syncDenialSummary(ClaimDenial $denial): void
    {
        // Re-fetch surviving rows for this denial (soft-deleted excluded
        // by default scope on the model).
        $rows = DenialResubmission::where('agency_id', $denial->agency_id)
            ->where('claim_denial_id', $denial->id)
            ->get();

        $appealLevel = (int) ($rows->max('attempt_number') ?? 0);
        $lastSubmitted = $rows->max('submitted_date');
        $totalRecovered = (float) $rows->sum('recovered_amount');

        $updates = [
            'appeal_level'           => $appealLevel,
            'appeal_submitted_date'  => $lastSubmitted,
            'recovered_amount'       => $totalRecovered,
        ];

        // Status derivation. We only TIGHTEN status (move toward
        // resolved) — operators can still manually override via the
        // existing denial update endpoint.
        $hasOpen = $rows->whereIn('status', ['submitted', 'awaiting_response'])->isNotEmpty();
        $denied  = (float) $denial->denied_amount;

        if ($hasOpen) {
            // Still in flight — make sure status reflects that.
            if (in_array($denial->status, ['new', 'in_review'])) {
                $updates['status'] = 'appeal_in_progress';
            }
        } elseif ($appealLevel > 0) {
            // All attempts resolved.
            if ($totalRecovered >= $denied && $denied > 0) {
                $updates['status'] = 'resolved_won';
                $updates['resolved_at'] = now();
            } elseif ($totalRecovered > 0) {
                $updates['status'] = 'resolved_partial';
                $updates['resolved_at'] = now();
            } elseif ($rows->where('status', 'abandoned')->isNotEmpty()) {
                // At least one attempt abandoned, no recovery —
                // leave it for the operator to write off or close out.
                // Don't auto-flip to resolved_lost; recovery may still
                // be pursued.
            } else {
                $updates['status'] = 'resolved_lost';
                $updates['resolved_at'] = now();
            }
        }

        $denial->update($updates);
    }
}
