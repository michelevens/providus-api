<?php

namespace App\Http\Controllers\Api\V1;

// PatientActivityController — powers the 5 new tabs on V2's
// PatientDetailPage: Payments, Activity (audit log), Communications,
// Notes, Timeline (a merged chronological feed).
//
// Why a dedicated controller (instead of extending RcmPhase2Controller):
// these queries are patient-scoped (string match on patient_name)
// and need to fan out across 6+ tables. Pulling that fan-out into
// its own file keeps RcmPhase2 focused on per-record CRUD and makes
// it easier to optimize this page's perf later (one shared eager-
// load, one shared agency_id filter).
//
// Identity: V2 uses patient_name (lowercased + trimmed) as the
// patient key — there is no `patients` table; demographics live
// on the claim rows. Same convention applied here.

use App\Http\Controllers\Controller;
use App\Models\PatientNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientActivityController extends Controller
{
    /**
     * GET /rcm/patients/{key}/activity
     *
     * Returns a single payload with every cross-table feed the V2
     * PatientDetailPage needs: payments, audit events,
     * communications, notes, and a merged timeline.
     *
     * `key` is the URL-encoded patient_key (lowercase, trimmed
     * patient_name). The controller re-normalizes defensively in
     * case callers haven't pre-lowercased.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $agencyId  = $request->user()->effectiveAgencyId($request);
        $patientKey = mb_strtolower(trim(urldecode($key)));

        if ($patientKey === '') {
            return response()->json(['success' => false, 'message' => 'Empty patient key'], 422);
        }

        // ── Claim ids belonging to this patient ──────────────
        // Every downstream query filters through these. One ID list,
        // reused.
        $claimIds = DB::table('claims')
            ->where('agency_id', $agencyId)
            ->whereRaw('LOWER(TRIM(patient_name)) = ?', [$patientKey])
            ->pluck('id')
            ->all();

        if (empty($claimIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'payments'       => [],
                    'audit'          => [],
                    'communications' => [],
                    'notes'          => $this->notesFor($agencyId, $patientKey),
                    'timeline'       => $this->timelineFor($agencyId, $patientKey, []),
                ],
            ]);
        }

        // ── Payments — claim_payments via allocations, deduped ──
        // A single check can pay many claims; we want each check ONCE,
        // with the per-claim breakdown nested. claim_payments rows are
        // the parent; payment_allocations link them to claims.
        $allocsByPayment = DB::table('payment_allocations')
            ->whereIn('claim_id', $claimIds)
            ->select('claim_payment_id', 'claim_id', 'paid_amount', 'service_line_number',
                'allowed_amount', 'adjustment_amount', 'patient_responsibility')
            ->orderBy('claim_payment_id')
            ->get()
            ->groupBy('claim_payment_id');

        $paymentIds = $allocsByPayment->keys()->all();

        $payments = DB::table('claim_payments')
            ->whereIn('id', $paymentIds)
            ->where('agency_id', $agencyId)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get([
                'id', 'payer_name', 'payment_type', 'check_number', 'trace_number',
                'payment_date', 'deposit_date', 'total_amount', 'posted_amount',
                'remaining_amount', 'status', 'notes', 'created_at',
            ])
            ->map(function ($p) use ($allocsByPayment, $claimIds) {
                // Only the allocations that hit THIS patient's claims —
                // not the full check (which may include other patients).
                $allocs = ($allocsByPayment[$p->id] ?? collect())
                    ->filter(fn ($a) => in_array($a->claim_id, $claimIds))
                    ->values();
                $p->patient_paid_amount = (float) $allocs->sum('paid_amount');
                $p->allocations = $allocs->toArray();
                return $p;
            });

        // ── Audit log — entries touching this patient's records ──
        // We pull rows where auditable_type is Claim/ClaimDenial/
        // PatientStatement AND the FK points at one of this patient's
        // records. To keep this fast we also gather denial + statement
        // ids first.
        $denialIds = DB::table('claim_denials')->whereIn('claim_id', $claimIds)->pluck('id')->all();
        $statementIds = DB::table('patient_statements')->whereIn('claim_id', $claimIds)->pluck('id')->all();

        $audit = DB::table('audit_logs')
            ->where('agency_id', $agencyId)
            ->where(function ($q) use ($claimIds, $denialIds, $statementIds) {
                $q->where(function ($q1) use ($claimIds) {
                    $q1->where('auditable_type', 'App\\Models\\Claim')->whereIn('auditable_id', $claimIds);
                });
                if (!empty($denialIds)) {
                    $q->orWhere(function ($q2) use ($denialIds) {
                        $q2->where('auditable_type', 'App\\Models\\ClaimDenial')->whereIn('auditable_id', $denialIds);
                    });
                }
                if (!empty($statementIds)) {
                    $q->orWhere(function ($q3) use ($statementIds) {
                        $q3->where('auditable_type', 'App\\Models\\PatientStatement')->whereIn('auditable_id', $statementIds);
                    });
                }
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'user_id', 'user_email', 'action', 'auditable_type',
                'auditable_id', 'old_values', 'new_values', 'created_at', 'impersonator_user_id']);

        // ── Communications — outbound emails + logged calls ─────
        // communication_logs has rich Resend integration (delivery_status,
        // delivered_at, bounced_at, etc.). Joined to claims by claim_id.
        $communications = DB::table('communication_logs')
            ->where('agency_id', $agencyId)
            ->whereIn('claim_id', $claimIds)
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id', 'direction', 'channel', 'subject', 'body', 'contact_name',
                'contact_info', 'outcome', 'duration_seconds', 'created_at',
                'recipient_email', 'delivery_status', 'resend_id', 'delivered_at',
                'bounced_at', 'read_at', 'claim_id',
            ]);

        // ── Notes ───────────────────────────────────────────────
        $notes = $this->notesFor($agencyId, $patientKey);

        // ── Timeline ────────────────────────────────────────────
        // Merged chronological feed of every event type. The actual
        // events come from the data we already loaded.
        $timeline = $this->timelineFor($agencyId, $patientKey, $claimIds);

        return response()->json([
            'success' => true,
            'data' => [
                'payments'       => $payments,
                'audit'          => $audit,
                'communications' => $communications,
                'notes'          => $notes,
                'timeline'       => $timeline,
            ],
        ]);
    }

    /**
     * Build the chronological timeline for the patient. Each event has
     * { type, at, title, sub?, link?, severity?, meta? } so the V2
     * Timeline component can render every type uniformly.
     *
     * Sources merged:
     *   - claim filed (claims.submitted_date or created_at)
     *   - claim status changes (audit_logs on Claim)
     *   - denial created
     *   - denial status changes (resolved/escalated)
     *   - statement created
     *   - statement sent (statement.last_sent_date)
     *   - statement handoff
     *   - payment posted (claim_payments)
     *   - communication sent
     *   - note added
     */
    private function timelineFor(int $agencyId, string $patientKey, array $claimIds): array
    {
        $events = [];

        // Claim events: filed + significant status changes.
        $claims = empty($claimIds) ? collect() : DB::table('claims')
            ->whereIn('id', $claimIds)
            ->get(['id', 'claim_number', 'submitted_date', 'created_at', 'status', 'date_of_service', 'total_charges']);
        foreach ($claims as $c) {
            $when = $c->submitted_date ?: $c->created_at;
            $events[] = [
                'type' => 'claim_filed',
                'at'   => (string) $when,
                'title' => "Claim {$c->claim_number} filed",
                'sub'   => 'DOS ' . ($c->date_of_service ?? 'unknown') . ' · $' . number_format((float) $c->total_charges, 2),
                'link'  => '/rcm/claims/' . $c->id,
                'severity' => 'info',
            ];
        }

        // Denials.
        $denials = empty($claimIds) ? collect() : DB::table('claim_denials')
            ->whereIn('claim_id', $claimIds)
            ->get(['id', 'claim_id', 'denial_date', 'denial_code', 'denial_reason', 'denied_amount', 'created_at', 'status']);
        foreach ($denials as $d) {
            $events[] = [
                'type' => 'denial_received',
                'at'   => (string) ($d->denial_date ?: $d->created_at),
                'title' => "Denial received: {$d->denial_code}",
                'sub'   => '$' . number_format((float) $d->denied_amount, 2) . ' · ' . substr($d->denial_reason ?: '', 0, 80),
                'link'  => '/rcm/denials/' . $d->id,
                'severity' => 'warning',
            ];
        }

        // Statements created.
        $statements = empty($claimIds) ? collect() : DB::table('patient_statements')
            ->whereIn('claim_id', $claimIds)
            ->get(['id', 'created_at', 'last_sent_date', 'times_sent', 'patient_balance', 'status',
                'handed_off_to_collections_at', 'promised_pay_date', 'promised_pay_amount']);
        foreach ($statements as $s) {
            $events[] = [
                'type' => 'statement_created',
                'at'   => (string) $s->created_at,
                'title' => 'Statement generated',
                'sub'   => '$' . number_format((float) $s->patient_balance, 2) . ' patient balance',
                'severity' => 'info',
            ];
            if ($s->last_sent_date) {
                $events[] = [
                    'type' => 'statement_sent',
                    'at'   => (string) $s->last_sent_date,
                    'title' => 'Statement sent to patient',
                    'sub'   => "Reminder #{$s->times_sent}",
                    'severity' => 'info',
                ];
            }
            if ($s->handed_off_to_collections_at) {
                $events[] = [
                    'type' => 'collections_handoff',
                    'at'   => (string) $s->handed_off_to_collections_at,
                    'title' => 'Handed off to collections',
                    'severity' => 'danger',
                ];
            }
            if ($s->promised_pay_date) {
                $events[] = [
                    'type' => 'promise_to_pay',
                    'at'   => (string) $s->created_at,
                    'title' => 'Promise to pay',
                    'sub'   => '$' . number_format((float) $s->promised_pay_amount, 2) . ' by ' . $s->promised_pay_date,
                    'severity' => 'success',
                ];
            }
        }

        // Payments (one event per check that touched this patient's
        // claims). Re-use the same dedup logic as the payments tab.
        $allocs = empty($claimIds) ? collect() : DB::table('payment_allocations')
            ->whereIn('claim_id', $claimIds)
            ->get(['claim_payment_id', 'paid_amount']);
        $patientPaymentIds = $allocs->pluck('claim_payment_id')->unique()->all();
        $payments = empty($patientPaymentIds) ? collect() : DB::table('claim_payments')
            ->whereIn('id', $patientPaymentIds)
            ->where('agency_id', $agencyId)
            ->get(['id', 'payment_date', 'payer_name', 'payment_type', 'check_number', 'total_amount']);
        foreach ($payments as $p) {
            $patientShare = (float) $allocs->where('claim_payment_id', $p->id)->sum('paid_amount');
            $events[] = [
                'type' => 'payment_posted',
                'at'   => (string) $p->payment_date,
                'title' => ($p->payment_type ? ucfirst($p->payment_type) : 'Payment') . ' from ' . $p->payer_name,
                'sub'   => '$' . number_format($patientShare, 2)
                    . ($p->check_number ? " · check #{$p->check_number}" : ''),
                'severity' => 'success',
            ];
        }

        // Communications sent.
        $comms = empty($claimIds) ? collect() : DB::table('communication_logs')
            ->where('agency_id', $agencyId)
            ->whereIn('claim_id', $claimIds)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'created_at', 'channel', 'direction', 'subject', 'outcome', 'recipient_email']);
        foreach ($comms as $c) {
            $events[] = [
                'type' => 'communication',
                'at'   => (string) $c->created_at,
                'title' => ucfirst($c->channel) . ' ' . $c->direction . ($c->subject ? ': ' . $c->subject : ''),
                'sub'   => $c->recipient_email ?: ($c->outcome ?: null),
                'severity' => 'info',
            ];
        }

        // Notes.
        $notes = DB::table('patient_notes')
            ->where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'body', 'created_at', 'pinned']);
        foreach ($notes as $n) {
            $events[] = [
                'type' => 'note_added',
                'at'   => (string) $n->created_at,
                'title' => 'Note added',
                'sub'   => substr($n->body, 0, 140),
                'severity' => $n->pinned ? 'pinned' : 'info',
            ];
        }

        // Sort desc by `at`. Coerce to comparable strings (ISO-ish);
        // null/empty sort last.
        usort($events, function ($a, $b) {
            $aw = $a['at'] ?? '';
            $bw = $b['at'] ?? '';
            return strcmp($bw, $aw);
        });

        // Cap so V2 doesn't render a 500-event feed.
        return array_slice($events, 0, 200);
    }

    private function notesFor(int $agencyId, string $patientKey): \Illuminate\Support\Collection
    {
        return DB::table('patient_notes')
            ->where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'body', 'pinned', 'created_by', 'updated_by', 'created_at', 'updated_at']);
    }

    // ─── Notes CRUD ───

    /**
     * POST /rcm/patients/{key}/notes
     */
    public function storeNote(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:5000',
            'pinned' => 'sometimes|boolean',
        ]);
        $agencyId = $request->user()->effectiveAgencyId($request);
        $patientKey = mb_strtolower(trim(urldecode($key)));

        $note = PatientNote::create([
            'agency_id'   => $agencyId,
            'patient_key' => $patientKey,
            'body'        => $request->input('body'),
            'pinned'      => (bool) $request->input('pinned', false),
            'created_by'  => $request->user()->id,
            'updated_by'  => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $note], 201);
    }

    /**
     * PUT /rcm/patients/{key}/notes/{id}
     */
    public function updateNote(Request $request, string $key, int $id): JsonResponse
    {
        $request->validate([
            'body' => 'sometimes|string|max:5000',
            'pinned' => 'sometimes|boolean',
        ]);
        $agencyId = $request->user()->effectiveAgencyId($request);
        $patientKey = mb_strtolower(trim(urldecode($key)));

        $note = PatientNote::where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
            ->findOrFail($id);

        $note->update([
            ...$request->only(['body', 'pinned']),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $note->fresh()]);
    }

    /**
     * DELETE /rcm/patients/{key}/notes/{id}
     */
    public function deleteNote(Request $request, string $key, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $patientKey = mb_strtolower(trim(urldecode($key)));

        $note = PatientNote::where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
            ->findOrFail($id);

        $note->delete();
        return response()->json(['success' => true]);
    }
}
