<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingTask;
use App\Models\ChargeEntry;
use App\Models\Claim;
use App\Models\ClaimDenial;
use App\Models\ClaimPayment;
use App\Models\ClaimServiceLine;
use App\Models\ClaimStatusCheck;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\AvailityService;
use App\Services\WebhookDispatcher;
use App\Support\WebhookPayloads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RcmController extends Controller
{
    // ── Claims ──

    public function claims(Request $request): JsonResponse
    {
        $query = Claim::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['billingClient:id,organization_name', 'serviceLines']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        if ($from = $request->input('from_date')) $query->where('date_of_service', '>=', $from);
        if ($to = $request->input('to_date')) $query->where('date_of_service', '<=', $to);
        // Free-text search hits claim_number, payer_icn, and patient_name.
        // Critical for the Availity-remit workflow: operators paste the
        // "Claim #" they see on the portal, which is the PAYER'S ICN —
        // not our submitter claim_number. Both must match the same
        // search box or the claim looks missing.
        if ($q = trim((string) $request->input('search', ''))) {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('claim_number', 'ilike', $like)
                  ->orWhere('payer_icn', 'ilike', $like)
                  ->orWhere('payer_claim_control_number', 'ilike', $like)
                  ->orWhere('patient_name', 'ilike', $like);
            });
        }
        $perPage = min((int) ($request->input('per_page', 100)), 1000);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('date_of_service')->paginate($perPage)]);
    }

    public function showClaim(Request $request, int $id): JsonResponse
    {
        $claim = Claim::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with([
                'billingClient:id,organization_name',
                'serviceLines',
                'denials',
                // Pull the parent ClaimPayment with each allocation so V2
                // can render a "Paid via check #X" link straight to the
                // payment detail page. Without this, the claim shows it
                // was paid but the operator can't trace which check it
                // came from without leaving the page.
                'paymentAllocations.payment:id,check_number,trace_number,payment_type,payment_date,total_amount,payer_name,status',
                'followups',
            ])
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $claim]);
    }

    public function storeClaim(Request $request): JsonResponse
    {
        $request->validate([
            'date_of_service' => 'required|date',
            'service_lines' => 'nullable|array',
            'service_lines.*.cpt_code' => 'required_with:service_lines|string|max:10',
        ]);
        $count = Claim::where('agency_id', $request->user()->effectiveAgencyId($request))->count() + 1;
        $claim = Claim::create([
            'agency_id' => $request->user()->effectiveAgencyId($request),
            'claim_number' => 'CLM-' . str_pad($count, 6, '0', STR_PAD_LEFT),
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'claim_type', 'status', 'provider_id', 'provider_name',
                'patient_name', 'patient_dob', 'patient_member_id', 'payer_name', 'payer_id_number',
                'payer_icn', 'payer_claim_control_number',
                'date_of_service', 'date_of_service_end', 'place_of_service', 'facility_name',
                'referring_provider', 'authorization_number', 'total_charges', 'submission_method',
                'clearinghouse', 'submitted_date', 'notes',
            ]),
        ]);
        if ($request->has('service_lines')) {
            foreach ($request->service_lines as $i => $line) {
                ClaimServiceLine::create(['claim_id' => $claim->id, 'line_number' => $i + 1, ...$line]);
            }
            $claim->recalculate();
        }

        if ($claim->status === 'submitted') {
            WebhookDispatcher::dispatch($claim->agency_id, WebhookDispatcher::CLAIM_SUBMITTED, WebhookPayloads::claim($claim));
        }

        $claim->load(['serviceLines', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => $claim], 201);
    }

    public function updateClaim(Request $request, int $id): JsonResponse
    {
        $claim = Claim::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $oldStatus = $claim->status;

        // Mirror the write-off approval gate from writeOffClaim — a
        // generic PUT shouldn't be a back door around the threshold.
        // Triggers only when status is flipping TO written_off (not on
        // edits to already-written-off claims).
        if ($request->input('status') === 'written_off' && $oldStatus !== 'written_off') {
            $amount = (float) ($request->input('balance', $claim->balance));
            if (!\App\Support\WriteOffApproval::canApprove($request->user(), $amount)) {
                return response()->json([
                    'success' => false,
                    'message' => \App\Support\WriteOffApproval::rejectionMessage($amount),
                    'error' => 'writeoff_requires_approval',
                    'threshold_usd' => \App\Support\WriteOffApproval::THRESHOLD_USD,
                ], 403);
            }
        }

        $claim->update($request->only([
            'billing_client_id', 'claim_type', 'status', 'provider_id', 'provider_name',
            'patient_name', 'patient_dob', 'patient_member_id', 'payer_name', 'payer_id_number',
            'payer_icn', 'payer_claim_control_number',
            'date_of_service', 'date_of_service_end', 'place_of_service', 'facility_name',
            'referring_provider', 'authorization_number', 'total_charges', 'total_allowed',
            'total_paid', 'patient_responsibility', 'adjustments', 'balance',
            'submission_method', 'clearinghouse', 'submitted_date', 'acknowledged_date',
            'adjudicated_date', 'paid_date', 'check_number', 'denial_reason', 'denial_codes',
            'appeal_deadline', 'notes',
        ]));
        if ($request->has('service_lines')) {
            $claim->serviceLines()->delete();
            foreach ($request->service_lines as $i => $line) {
                ClaimServiceLine::create(['claim_id' => $claim->id, 'line_number' => $i + 1, ...$line]);
            }
            $claim->recalculate();
        } elseif ($request->hasAny(['total_charges', 'total_paid', 'adjustments'])) {
            // Operator edited a balance-relevant field directly (no
            // service-line update). Recalculate balance server-side so
            // the stored value can't drift from charges - paid - adj.
            // Don't auto-flip status here — that's the operator's call
            // via the status field, distinct from a typo correction.
            $claim->balance = ((float) $claim->total_charges) - ((float) $claim->total_paid) - ((float) ($claim->adjustments ?? 0));
            $claim->save();
        }

        $this->fireClaimStatusEvent($claim, $oldStatus);

        $claim->load(['serviceLines', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => $claim]);
    }

    public function destroyClaim(Request $request, int $id): JsonResponse
    {
        Claim::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Run an Availity 276 claim-status inquiry against the payer and
     * persist the 277 response. The pending-claims UI fires this from
     * the per-row Check button and the Check All bulk action.
     *
     * Side effects when 277 says paid/denied:
     *   - claims.last_status_* mirror the response
     *   - claims.status_inquiry_count++
     *   - claims.status auto-promotes to 'paid' or 'denied' so the
     *     pending list shrinks without a manual update
     * Each call also appends a row to claim_status_checks so we can
     * trend "how often does Cigna's system say paid but ours says
     * pending" per payer.
     *
     * Map of Availity statusCategory → claim status:
     *   F1 (Finalized / Payment) → paid
     *   F2 (Finalized / Denial)  → denied
     *   F3 (Finalized / Revised) → leave alone (warrants manual review)
     *   P* (Pending)             → leave alone
     *   anything else            → leave alone
     */
    public function statusInquiry(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $claim = Claim::where('agency_id', $agencyId)->findOrFail($id);

        $agency = $request->user()->agency;
        $config = array_merge(
            $agency->config ? $agency->config->toArray() : [],
            [
                'agency_id'   => $agency->id,
                'agency_name' => $agency->name,
                'agency_npi'  => $agency->npi ?? '',
            ]
        );

        // Split patient_name "Last, First" or "First Last" so the
        // payer matcher has something to work with. Availity won't
        // return a hit on a mismatch, so this is load-bearing.
        $firstName = '';
        $lastName = '';
        $name = trim((string) $claim->patient_name);
        if (str_contains($name, ',')) {
            [$lastName, $firstName] = array_pad(array_map('trim', explode(',', $name, 2)), 2, '');
        } else {
            $parts = preg_split('/\s+/', $name) ?: [];
            $lastName = (string) array_pop($parts);
            $firstName = implode(' ', $parts);
        }

        $payload = [
            'payer_name'      => $claim->payer_name,
            'payer_id'        => $claim->payer_id_number,
            'claim_number'    => $claim->claim_number,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'date_of_birth'   => $claim->patient_dob,
            'date_of_service' => $claim->date_of_service?->format('Y-m-d'),
            'charge_amount'   => $claim->total_charges,
            'provider_npi'    => $claim->provider?->npi ?? $agency->npi ?? '',
        ];

        $availity = app(AvailityService::class);
        $result = $availity->checkClaimStatus($config, $payload);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Status inquiry failed',
            ], 422);
        }

        $cs = $result['claimStatus'];
        $statusCategory = (string) ($cs['statusCategory'] ?? '');
        $paidAmount = (float) ($cs['paidAmount'] ?? 0);

        ClaimStatusCheck::create([
            'claim_id'        => $claim->id,
            'checked_at'      => now(),
            'source'          => 'availity',
            'status_code'     => $cs['statusCode'] ?? null,
            'status_category' => $statusCategory ?: null,
            'status_text'     => $cs['status'] ?? null,
            'paid_amount'     => $paidAmount ?: null,
            'paid_date'       => $cs['paidDate'] ?: null,
            'check_number'    => $cs['checkNumber'] ?: null,
            'raw_response'    => $cs['raw'] ?? null,
            'user_id'         => $request->user()->id,
        ]);

        $claim->last_status_check_at = now();
        $claim->last_status_code = $cs['statusCode'] ?? null;
        $claim->last_status_category = $statusCategory ?: null;
        $claim->last_status_response = $cs;
        $claim->status_inquiry_count = ($claim->status_inquiry_count ?? 0) + 1;

        // Auto-promote when payer's system says finalized. The biller
        // can still override later if the response is wrong.
        if ($statusCategory === 'F1' && !in_array($claim->status, ['paid', 'denied', 'written_off'], true)) {
            $claim->status = 'paid';
            if ($paidAmount > 0 && !$claim->paid_date) {
                $claim->paid_date = $cs['paidDate'] ?: now()->toDateString();
            }
        } elseif ($statusCategory === 'F2' && !in_array($claim->status, ['paid', 'denied', 'written_off'], true)) {
            $claim->status = 'denied';
        }

        $claim->save();
        $claim->load(['statusChecks' => fn ($q) => $q->limit(10)]);

        return response()->json(['success' => true, 'data' => $claim, 'claim_status' => $cs]);
    }

    /**
     * Update follow-up assignment on a claim. Accepts assigned_to,
     * follow_up_due_date, snoozed_until, escalated. Null is allowed —
     * use it to clear a field (unassign, un-snooze).
     */
    public function updateAssignment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'assigned_to'        => 'nullable|integer|exists:users,id',
            'follow_up_due_date' => 'nullable|date',
            'snoozed_until'      => 'nullable|date',
            'escalated'          => 'nullable|boolean',
        ]);
        $agencyId = $request->user()->effectiveAgencyId($request);
        $claim = Claim::where('agency_id', $agencyId)->findOrFail($id);
        $claim->fill($request->only(['assigned_to', 'follow_up_due_date', 'snoozed_until', 'escalated']));
        $claim->save();
        return response()->json(['success' => true, 'data' => $claim]);
    }

    /**
     * Apply the same assignment patch to many claims at once. Used by
     * the pending-claims bulk toolbar ("Assign 12 selected claims to
     * Sarah, due Friday").
     */
    public function bulkUpdateAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'claim_ids'          => 'required|array|min:1',
            'claim_ids.*'        => 'integer',
            'assigned_to'        => 'nullable|integer|exists:users,id',
            'follow_up_due_date' => 'nullable|date',
            'snoozed_until'      => 'nullable|date',
            'escalated'          => 'nullable|boolean',
        ]);
        $agencyId = $request->user()->effectiveAgencyId($request);
        $patch = array_filter(
            $request->only(['assigned_to', 'follow_up_due_date', 'snoozed_until', 'escalated']),
            fn ($v) => $v !== null,
        );
        if (empty($patch)) {
            return response()->json(['success' => false, 'error' => 'No fields to update'], 422);
        }
        $count = Claim::where('agency_id', $agencyId)
            ->whereIn('id', $request->input('claim_ids'))
            ->update($patch);
        return response()->json(['success' => true, 'updated' => $count]);
    }

    /**
     * Write off a claim balance.
     *
     * Adds {amount} to claim.adjustments, recalculates balance, and
     * (when balance reaches 0) flips status to written_off. Above the
     * WriteOffApproval threshold ($500), only agency-owner roles can
     * complete this — staff billers get a 403 with the structured
     * error 'writeoff_requires_approval' so the UI can prompt the
     * operator to ask an owner.
     *
     * The reason text is appended to claim.notes with a marker line
     * (\"[WRITE-OFF $X by user on date]\") so the history is queryable
     * later without a separate write_offs table. Auditable trait on
     * the Claim model records the full before/after diff in audit_logs.
     */
    public function writeOffClaim(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
            'category' => 'nullable|string|max:50', // small_balance, charity, bad_debt, contractual, etc.
        ]);
        $amount = (float) $request->input('amount');
        $user = $request->user();
        $agencyId = $user->effectiveAgencyId($request);

        if (!\App\Support\WriteOffApproval::canApprove($user, $amount)) {
            return response()->json([
                'success' => false,
                'message' => \App\Support\WriteOffApproval::rejectionMessage($amount),
                'error' => 'writeoff_requires_approval',
                'threshold_usd' => \App\Support\WriteOffApproval::THRESHOLD_USD,
            ], 403);
        }

        $claim = Claim::where('agency_id', $agencyId)->findOrFail($id);

        // Don't allow writing off more than the outstanding balance —
        // would put adjustments above charges and produce a negative
        // balance which the UI can't reason about.
        $outstanding = (float) $claim->balance;
        if ($amount > $outstanding + 0.005) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Write-off amount $%s exceeds the outstanding balance of $%s.',
                    number_format($amount, 2),
                    number_format($outstanding, 2),
                ),
                'error' => 'writeoff_exceeds_balance',
            ], 422);
        }

        $this->applyWriteOff($claim, $amount, (string) $request->input('reason'), $request->input('category'), $user, $user);

        $claim->load(['serviceLines', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => $claim]);
    }

    /**
     * Apply the actual write-off math. Extracted so both the direct
     * writeOffClaim flow and the approveWriteOffRequest queue flow
     * land on identical math, marker formatting, and webhook firing.
     *
     * $appliedBy is the user whose name appears on the marker line —
     * for direct write-offs this equals $approver; for queued
     * approvals it's the approver, with the original requester
     * referenced inside the reason text.
     */
    private function applyWriteOff(Claim $claim, float $amount, string $reason, ?string $category, User $appliedBy, User $approver): void
    {
        \DB::transaction(function () use ($claim, $amount, $reason, $category, $appliedBy, $approver) {
            $oldStatus = $claim->status;
            $stamp = now()->format('Y-m-d');
            $byLabel = $appliedBy->id === $approver->id
                ? ($appliedBy->email ?: ('user#' . $appliedBy->id))
                : sprintf(
                    '%s (approved by %s)',
                    $appliedBy->email ?: ('user#' . $appliedBy->id),
                    $approver->email ?: ('user#' . $approver->id),
                );
            $marker = sprintf(
                '[WRITE-OFF $%s%s by %s on %s] %s',
                number_format($amount, 2),
                $category ? " · {$category}" : '',
                $byLabel,
                $stamp,
                trim($reason),
            );
            $claim->adjustments = ((float) $claim->adjustments) + $amount;
            $claim->balance = ((float) $claim->total_charges) - ((float) $claim->total_paid) - ((float) $claim->adjustments);
            if ($claim->balance <= 0.005) {
                $claim->balance = 0;
                $claim->status = 'written_off';
            }
            $claim->notes = $claim->notes
                ? rtrim($claim->notes) . "\n" . $marker
                : $marker;
            $claim->save();

            $this->fireClaimStatusEvent($claim, $oldStatus);
        });
    }

    // ── Write-off approval queue ──
    // Staff billers below the approval threshold ($500) submit
    // requests via POST /rcm/claims/{id}/write-off/request. The
    // request rides on the existing billing_tasks table with
    // category='writeoff_approval' and a JSON sentinel encoded into
    // description. Owners see the queue at GET /rcm/write-off-requests
    // and approve/reject. On approve, the same applyWriteOff() path
    // runs so the math + audit + webhooks are identical to a direct
    // owner write-off.
    private const WO_SENTINEL = '<<WRITEOFF_REQUEST';
    private const WO_SENTINEL_CLOSE = '>>';

    private function woEncode(array $payload): string
    {
        return self::WO_SENTINEL . ' ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . self::WO_SENTINEL_CLOSE;
    }

    private function woDecode(?string $description): ?array
    {
        if (!$description) return null;
        $start = strpos($description, self::WO_SENTINEL);
        if ($start === false) return null;
        $jsonStart = $start + strlen(self::WO_SENTINEL) + 1;
        $end = strpos($description, self::WO_SENTINEL_CLOSE, $jsonStart);
        if ($end === false) return null;
        $json = substr($description, $jsonStart, $end - $jsonStart);
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function requestWriteOff(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
            'category' => 'nullable|string|max:50',
        ]);
        $amount = (float) $request->input('amount');
        $user = $request->user();
        $agencyId = $user->effectiveAgencyId($request);

        // If the user could just approve it themselves, skip the
        // queue entirely — they shouldn't have to wait on their own
        // approval. Routes the call through the direct endpoint
        // semantically by returning a hint.
        if (\App\Support\WriteOffApproval::canApprove($user, $amount)) {
            return response()->json([
                'success' => false,
                'message' => 'You can complete this write-off directly — no request needed.',
                'error' => 'can_approve_directly',
            ], 422);
        }

        $claim = Claim::where('agency_id', $agencyId)->findOrFail($id);
        $outstanding = (float) $claim->balance;
        if ($amount > $outstanding + 0.005) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Requested write-off $%s exceeds the outstanding balance of $%s.',
                    number_format($amount, 2),
                    number_format($outstanding, 2),
                ),
                'error' => 'writeoff_exceeds_balance',
            ], 422);
        }

        // Dedup: if there's already a pending request for this claim,
        // surface that instead of creating a second one. Owners get
        // one queue item per claim, not three.
        $existing = BillingTask::where('agency_id', $agencyId)
            ->where('category', 'writeoff_approval')
            ->where('claim_id', $claim->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A write-off request for this claim is already pending owner approval.',
                'error' => 'request_already_pending',
                'existing_task_id' => $existing->id,
            ], 409);
        }

        $reason = trim((string) $request->input('reason'));
        $category = $request->input('category');
        $payload = [
            'amount' => $amount,
            'reason' => $reason,
            'category' => $category,
            'claim_id' => $claim->id,
            'claim_number' => $claim->claim_number,
            'patient_name' => $claim->patient_name,
            'payer_name' => $claim->payer_name,
            'outstanding' => $outstanding,
            'requested_by' => $user->id,
            'requested_by_email' => $user->email,
            'requested_at' => now()->toIso8601String(),
        ];
        $description = sprintf(
            "Staff biller %s is requesting approval to write off \$%s on claim %s (%s, %s). Reason: %s\n\n%s",
            $user->email ?: ('user#' . $user->id),
            number_format($amount, 2),
            $claim->claim_number ?: '#' . $claim->id,
            $claim->patient_name ?: 'patient',
            $claim->payer_name ?: 'payer',
            $reason,
            $this->woEncode($payload),
        );

        $task = BillingTask::create([
            'agency_id' => $agencyId,
            'billing_client_id' => $claim->billing_client_id,
            'claim_id' => $claim->id,
            'title' => sprintf('Approve $%s write-off — %s', number_format($amount, 2), $claim->patient_name ?: ('claim ' . ($claim->claim_number ?: $claim->id))),
            'description' => $description,
            'provider_name' => $claim->provider_name,
            'category' => 'writeoff_approval',
            'priority' => $amount >= 2000 ? 'urgent' : 'high',
            'status' => 'pending',
            'due_date' => now()->addDays(3)->toDateString(),
            'created_by' => $user->id,
            'source' => 'writeoff_request',
            'source_key' => 'writeoff:' . $claim->id,
        ]);

        return response()->json(['success' => true, 'data' => $task], 201);
    }

    public function listWriteOffRequests(Request $request): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $status = $request->input('status', 'pending'); // pending | completed | cancelled | all
        $q = BillingTask::where('agency_id', $agencyId)
            ->where('category', 'writeoff_approval')
            ->orderByDesc('created_at');
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        $tasks = $q->limit(200)->get()->map(function ($t) {
            $payload = $this->woDecode($t->description) ?? [];
            return [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'claim_id' => $t->claim_id,
                'billing_client_id' => $t->billing_client_id,
                'due_date' => $t->due_date,
                'created_at' => $t->created_at,
                'completed_at' => $t->completed_at,
                'created_by' => $t->created_by,
                'assigned_to' => $t->assigned_to,
                'amount' => $payload['amount'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'category' => $payload['category'] ?? null,
                'claim_number' => $payload['claim_number'] ?? null,
                'patient_name' => $payload['patient_name'] ?? null,
                'payer_name' => $payload['payer_name'] ?? null,
                'outstanding' => $payload['outstanding'] ?? null,
                'requested_by_email' => $payload['requested_by_email'] ?? null,
                'requested_at' => $payload['requested_at'] ?? null,
            ];
        });

        return response()->json(['success' => true, 'data' => $tasks]);
    }

    public function approveWriteOffRequest(Request $request, int $taskId): JsonResponse
    {
        $approver = $request->user();
        $agencyId = $approver->effectiveAgencyId($request);
        $task = BillingTask::where('agency_id', $agencyId)
            ->where('category', 'writeoff_approval')
            ->findOrFail($taskId);

        if ($task->status === 'completed' || $task->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $task->status . '.',
                'error' => 'request_already_resolved',
            ], 409);
        }

        $payload = $this->woDecode($task->description);
        if (!$payload || !isset($payload['amount'], $payload['claim_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Request payload is corrupt — cannot complete write-off.',
                'error' => 'payload_corrupt',
            ], 422);
        }

        $amount = (float) $payload['amount'];

        // The approver themselves must clear the threshold — a staff
        // user can't "approve" their own request for $1,500 by
        // hitting this endpoint.
        if (!\App\Support\WriteOffApproval::canApprove($approver, $amount)) {
            return response()->json([
                'success' => false,
                'message' => \App\Support\WriteOffApproval::rejectionMessage($amount),
                'error' => 'writeoff_requires_approval',
                'threshold_usd' => \App\Support\WriteOffApproval::THRESHOLD_USD,
            ], 403);
        }

        $claim = Claim::where('agency_id', $agencyId)->find($payload['claim_id']);
        if (!$claim) {
            // Claim deleted between request and approval — cancel
            // the task rather than blow up.
            $task->status = 'cancelled';
            $task->completed_at = now();
            $task->description = rtrim($task->description) . "\n\n[CANCELLED: claim no longer exists]";
            $task->save();
            return response()->json([
                'success' => false,
                'message' => 'The underlying claim no longer exists. Request cancelled.',
                'error' => 'claim_not_found',
            ], 410);
        }

        // Re-validate the outstanding balance — a payment may have
        // landed between request and approval, reducing the balance
        // below the requested amount.
        $outstanding = (float) $claim->balance;
        if ($amount > $outstanding + 0.005) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Outstanding balance is now $%s, less than the requested $%s. Reject this request or have the staff biller submit a new one.',
                    number_format($outstanding, 2),
                    number_format($amount, 2),
                ),
                'error' => 'balance_changed',
                'new_outstanding' => $outstanding,
            ], 422);
        }

        $requester = $payload['requested_by'] ? User::find($payload['requested_by']) : null;
        $appliedBy = $requester ?: $approver;
        $reason = (string) ($payload['reason'] ?? '');

        $this->applyWriteOff($claim, $amount, $reason, $payload['category'] ?? null, $appliedBy, $approver);

        $task->status = 'completed';
        $task->completed_at = now();
        $task->assigned_to = $approver->id;
        $task->description = rtrim($task->description) . sprintf(
            "\n\n[APPROVED by %s on %s]",
            $approver->email ?: ('user#' . $approver->id),
            now()->format('Y-m-d H:i'),
        );
        $task->save();

        $claim->load(['serviceLines', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => ['claim' => $claim, 'task_id' => $task->id]]);
    }

    public function rejectWriteOffRequest(Request $request, int $taskId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);
        $approver = $request->user();
        $agencyId = $approver->effectiveAgencyId($request);
        $task = BillingTask::where('agency_id', $agencyId)
            ->where('category', 'writeoff_approval')
            ->findOrFail($taskId);

        if ($task->status === 'completed' || $task->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $task->status . '.',
                'error' => 'request_already_resolved',
            ], 409);
        }

        // Same role check as approve — only owner-level users can
        // resolve a request. Below-threshold users can't reject
        // their colleagues' requests either.
        $payload = $this->woDecode($task->description) ?: [];
        $amount = (float) ($payload['amount'] ?? 0);
        if (!\App\Support\WriteOffApproval::canApprove($approver, $amount)) {
            return response()->json([
                'success' => false,
                'message' => \App\Support\WriteOffApproval::rejectionMessage($amount),
                'error' => 'writeoff_requires_approval',
            ], 403);
        }

        $rejectReason = trim((string) $request->input('reason', ''));
        $task->status = 'cancelled';
        $task->completed_at = now();
        $task->assigned_to = $approver->id;
        $task->description = rtrim($task->description) . sprintf(
            "\n\n[REJECTED by %s on %s] %s",
            $approver->email ?: ('user#' . $approver->id),
            now()->format('Y-m-d H:i'),
            $rejectReason ?: 'No reason given.',
        );
        $task->save();

        return response()->json(['success' => true, 'data' => $task]);
    }

    public function bulkImportClaims(Request $request): JsonResponse
    {
        $request->validate(['claims' => 'required|array|min:1|max:500']);

        $agencyId = $request->user()->effectiveAgencyId($request);
        $userId = $request->user()->id;
        $baseCount = Claim::where('agency_id', $agencyId)->count();
        $created = 0;
        $errors = [];

        foreach ($request->claims as $i => $row) {
            // Per-row transaction so a constraint failure on the optional
            // denial/service-line inserts after the claim row commits
            // doesn't leave an orphaned claim without its companion records.
            try {
                \DB::beginTransaction();
                if (empty($row['date_of_service'])) {
                    $errors[] = "Row " . ($i + 1) . ": date_of_service is required";
                    \DB::rollBack();
                    continue;
                }
                // Use source claim number if available, otherwise auto-generate
                $claimNumber = $row['payer_id_number'] ?? $row['claim_number'] ?? null;
                if (!$claimNumber) {
                    $claimNumber = 'CLM-' . str_pad($baseCount + $created + 1, 6, '0', STR_PAD_LEFT);
                }

                // Duplicate check 1: same claim_number + date_of_service.
                // Also matches when the CSV's claim_number equals an
                // existing row's payer_icn — covers the case where one
                // operator imports the Tebra claim_number and another
                // imports a CSV that uses the payer ICN as the key.
                $dupeByNumber = Claim::where('agency_id', $agencyId)
                    ->where(function ($q) use ($claimNumber) {
                        $q->where('claim_number', $claimNumber)
                          ->orWhere('payer_icn', $claimNumber);
                    })
                    ->where('date_of_service', $row['date_of_service']);
                if (!empty($row['patient_name'])) {
                    $dupeByNumber->where('patient_name', $row['patient_name']);
                }
                if ($dupeByNumber->exists()) {
                    $errors[] = "Row " . ($i + 1) . ": duplicate claim ({$claimNumber} on {$row['date_of_service']})";
                    \DB::rollBack();
                    continue;
                }
                // Duplicate check 2: same patient + date_of_service + total_charges (cross-source)
                if (!empty($row['patient_name']) && !empty($row['total_charges'])) {
                    $dupeByPatient = Claim::where('agency_id', $agencyId)
                        ->where('patient_name', $row['patient_name'])
                        ->where('date_of_service', $row['date_of_service'])
                        ->where('total_charges', (float) $row['total_charges']);
                    if ($dupeByPatient->exists()) {
                        $errors[] = "Row " . ($i + 1) . ": duplicate (same patient/DOS/charges as existing claim)";
                        \DB::rollBack();
                        continue;
                    }
                }

                $claim = Claim::create([
                    'agency_id' => $agencyId,
                    'claim_number' => $claimNumber,
                    'created_by' => $userId,
                    'claim_type' => $row['claim_type'] ?? '837P',
                    'status' => $row['status'] ?? 'submitted',
                    'billing_client_id' => $row['billing_client_id'] ?? null,
                    'provider_name' => $row['provider_name'] ?? null,
                    'patient_name' => $row['patient_name'] ?? null,
                    'patient_dob' => $row['patient_dob'] ?? null,
                    'patient_member_id' => $row['patient_member_id'] ?? null,
                    'payer_name' => $row['payer_name'] ?? null,
                    'payer_id_number' => $row['payer_id_number'] ?? null,
                    'date_of_service' => $row['date_of_service'],
                    'date_of_service_end' => $row['date_of_service_end'] ?? null,
                    'place_of_service' => $row['place_of_service'] ?? null,
                    'facility_name' => $row['facility_name'] ?? null,
                    'referring_provider' => $row['referring_provider'] ?? null,
                    'authorization_number' => $row['authorization_number'] ?? null,
                    'total_charges' => $row['total_charges'] ?? 0,
                    'total_paid' => $row['total_paid'] ?? 0,
                    'patient_responsibility' => $row['patient_responsibility'] ?? 0,
                    'balance' => ($row['total_charges'] ?? 0) - ($row['total_paid'] ?? 0),
                    'submission_method' => $row['submission_method'] ?? 'electronic',
                    'submitted_date' => $row['submitted_date'] ?? null,
                    'paid_date' => $row['paid_date'] ?? null,
                    'check_number' => $row['check_number'] ?? null,
                    'denial_reason' => $row['denial_reason'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);

                // Auto-create denial record if status is denied
                if ($claim->status === 'denied') {
                    ClaimDenial::create([
                        'agency_id' => $agencyId,
                        'claim_id' => $claim->id,
                        'billing_client_id' => $claim->billing_client_id,
                        'denial_category' => 'other',
                        'denial_reason' => $row['denial_reason'] ?? 'Imported as denied',
                        'denied_amount' => $claim->total_charges,
                        'status' => 'new',
                        'priority' => 'normal',
                        'denial_date' => $claim->date_of_service,
                        'created_by' => $userId,
                    ]);
                }

                // Create service lines if CPT code provided
                if (!empty($row['cpt_code'])) {
                    ClaimServiceLine::create([
                        'claim_id' => $claim->id,
                        'line_number' => 1,
                        'cpt_code' => $row['cpt_code'],
                        'cpt_description' => $row['cpt_description'] ?? '',
                        'modifiers' => $row['modifiers'] ?? '',
                        'icd_codes' => $row['icd_codes'] ?? '',
                        'units' => $row['units'] ?? 1,
                        'charges' => $row['total_charges'] ?? 0,
                        'paid_amount' => $row['total_paid'] ?? 0,
                    ]);
                }

                $created++;
                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'imported' => $created,
            'errors' => $errors,
            'total_submitted' => count($request->claims),
        ], 201);
    }

    /**
     * Purge all claims and related data (service lines, denials, charges, payments) for the agency.
     * Used to wipe imported data before a clean reimport.
     */
    public function purgeAllClaims(Request $request): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $confirm = $request->input('confirm');
        if ($confirm !== 'DELETE_ALL_CLAIMS') {
            return response()->json(['success' => false, 'message' => 'Send {"confirm":"DELETE_ALL_CLAIMS"} to proceed'], 422);
        }

        try {
            $claimIds = Claim::where('agency_id', $agencyId)->pluck('id')->toArray();

            // Delete related records (service lines & allocations don't have agency_id, use claim_id)
            $deletedServiceLines = $claimIds ? ClaimServiceLine::whereIn('claim_id', $claimIds)->delete() : 0;
            $deletedAllocations = 0;
            try { $deletedAllocations = $claimIds ? PaymentAllocation::whereIn('claim_id', $claimIds)->delete() : 0; } catch (\Exception $e) {}
            $deletedDenials = ClaimDenial::where('agency_id', $agencyId)->delete();
            $deletedPayments = ClaimPayment::where('agency_id', $agencyId)->delete();
            $deletedCharges = ChargeEntry::where('agency_id', $agencyId)->delete();
            $deletedClaims = Claim::where('agency_id', $agencyId)->delete();

            return response()->json([
                'success' => true,
                'deleted' => [
                    'claims' => $deletedClaims,
                    'service_lines' => $deletedServiceLines,
                    'denials' => $deletedDenials,
                    'payments' => $deletedPayments,
                    'allocations' => $deletedAllocations,
                    'charges' => $deletedCharges,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function claimStats(Request $request): JsonResponse
    {
        $aid = $request->user()->effectiveAgencyId($request);
        $claims = Claim::where('agency_id', $aid)->get();
        $totalClaims = $claims->count();
        $totalCharged = $claims->sum(fn($c) => (float) $c->total_charges);
        $totalPaid = $claims->sum(fn($c) => (float) $c->total_paid);
        $totalBalance = $claims->sum(fn($c) => (float) $c->balance);
        $pendingCount = $claims->whereIn('status', ['submitted', 'acknowledged', 'pending'])->count();
        $paidCount = $claims->whereIn('status', ['paid', 'partial_paid'])->count();
        $deniedCount = $claims->where('status', 'denied')->count();
        $totalPatientResp = $claims->sum(fn($c) => (float) $c->patient_responsibility);
        $totalDeniedAmount = $claims->where('status', 'denied')->sum(fn($c) => (float) $c->total_charges);

        // Monthly breakdown for charts (configurable range)
        $monthRange = (int) ($request->input('months', 6));
        if ($monthRange < 1) $monthRange = 6;
        if ($monthRange > 24) $monthRange = 24;
        $monthly = [];
        for ($m = $monthRange - 1; $m >= 0; $m--) {
            $date = now()->subMonths($m);
            $key = $date->format('Y-m');
            $monthClaims = $claims->filter(fn($c) => substr($c->date_of_service, 0, 7) === $key);
            $monthly[] = [
                'period' => $key,
                'claims_submitted' => $monthClaims->count(),
                'amount_billed' => round($monthClaims->sum(fn($c) => (float) $c->total_charges), 2),
                'amount_collected' => round($monthClaims->sum(fn($c) => (float) $c->total_paid), 2),
                'denied_amount' => round($monthClaims->where('status', 'denied')->sum(fn($c) => (float) $c->total_charges), 2),
            ];
        }

        return response()->json(['success' => true, 'data' => [
            'total_claims' => $totalClaims,
            'total_charged' => round($totalCharged, 2),
            'total_paid' => round($totalPaid, 2),
            'total_balance' => round($totalBalance, 2),
            'total_patient_responsibility' => round($totalPatientResp, 2),
            'total_denied_amount' => round($totalDeniedAmount, 2),
            'pending_count' => $pendingCount,
            'paid_count' => $paidCount,
            'denied_count' => $deniedCount,
            'clean_claim_rate' => $totalClaims > 0 ? round(($totalClaims - $deniedCount) / $totalClaims * 100, 1) : 0,
            'collection_rate' => $totalCharged > 0 ? round($totalPaid / $totalCharged * 100, 1) : 0,
            'monthly' => $monthly,
        ]]);
    }

    // ── Denials ──

    public function denials(Request $request): JsonResponse
    {
        $query = ClaimDenial::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['claim:id,claim_number,payer_name,patient_name,total_charges', 'billingClient:id,organization_name']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        if ($cat = $request->input('category')) $query->where('denial_category', $cat);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->get()]);
    }

    public function storeDenial(Request $request): JsonResponse
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'denial_category' => 'required|string|max:30',
            'denial_reason' => 'required|string|max:500',
        ]);
        $claim = Claim::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($request->claim_id);
        $denial = ClaimDenial::create([
            'agency_id' => $request->user()->effectiveAgencyId($request),
            'billing_client_id' => $claim->billing_client_id,
            'created_by' => $request->user()->id,
            ...$request->only([
                'claim_id', 'denial_category', 'denial_code', 'denial_reason', 'denied_amount',
                'status', 'priority', 'denial_date', 'appeal_deadline', 'assigned_to',
            ]),
        ]);
        $oldStatus = $claim->status;
        $claim->update(['status' => 'denied', 'denial_reason' => $request->denial_reason]);
        if ($oldStatus !== 'denied') {
            WebhookDispatcher::dispatch($claim->agency_id, WebhookDispatcher::CLAIM_DENIED, WebhookPayloads::claim($claim));
        }
        return response()->json(['success' => true, 'data' => $denial->load('claim:id,claim_number,payer_name')], 201);
    }

    public function updateDenial(Request $request, int $id): JsonResponse
    {
        $denial = ClaimDenial::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $data = $request->only([
            'denial_category', 'denial_code', 'denial_reason', 'denied_amount', 'status', 'priority',
            'appeal_deadline', 'appeal_level', 'appeal_submitted_date', 'recovered_amount',
            'appeal_notes', 'resolution_notes', 'assigned_to',
        ]);

        // Write-off approval gate. Above $500, only agency-owner roles
        // can complete a write-off; staff billers see a 403 with a
        // suggestion to ask an owner. Prevents silent compliance gaps
        // where junior staff close out large denials without sign-off.
        if (($data['status'] ?? null) === 'written_off') {
            $amount = (float) ($data['denied_amount'] ?? $denial->denied_amount);
            if (!\App\Support\WriteOffApproval::canApprove($request->user(), $amount)) {
                return response()->json([
                    'success' => false,
                    'message' => \App\Support\WriteOffApproval::rejectionMessage($amount),
                    'error' => 'writeoff_requires_approval',
                ], 403);
            }
        }

        if (in_array($data['status'] ?? '', ['resolved_won', 'resolved_lost', 'resolved_partial', 'written_off']) && !$denial->resolved_at) {
            $data['resolved_at'] = now();
        }
        $denial->update($data);
        return response()->json(['success' => true, 'data' => $denial]);
    }

    public function destroyDenial(Request $request, int $id): JsonResponse
    {
        ClaimDenial::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Create a corrected claim from a denial. Clones the patient,
     * provider, payer, DOS, and service lines from the original claim,
     * leaves status=draft so the operator can edit (fix the modifier,
     * coding error, etc) before resubmitting, and links the new claim
     * back to the original + the denial that triggered it.
     *
     * The link enables three things:
     *   1. "How many times has this same DOS been re-billed?" view
     *   2. Trending: "we keep coding 99214 wrong for BCBS"
     *   3. Audit: writing off the original denial AFTER the corrected
     *      claim is submitted is the right action — UI can prompt for it
     */
    public function createCorrectedClaim(Request $request, int $denialId): JsonResponse
    {
        $denial = ClaimDenial::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($denialId);
        $original = $denial->claim()->with('serviceLines')->firstOrFail();

        // Build a new claim from the original. We deliberately don't
        // copy: status (defaults to 'draft'), claim_number (will be
        // regenerated on submit), submitted_date / paid_date / etc.,
        // total_paid (zero on a fresh claim), and audit fields.
        $newClaim = $original->replicate([
            'claim_number', 'status', 'submitted_date', 'acknowledged_date',
            'adjudicated_date', 'paid_date', 'check_number', 'total_paid',
            'denial_reason', 'denial_codes', 'appeal_deadline', 'balance',
        ]);
        $newClaim->status = 'draft';
        $newClaim->balance = $newClaim->total_charges;
        $newClaim->original_claim_id = $original->id;
        $newClaim->corrected_from_denial_id = $denial->id;
        $newClaim->created_by = $request->user()->id;
        $newClaim->notes = trim(($newClaim->notes ?? '') . "\n\nCorrected from claim #{$original->id} after denial #{$denial->id}: {$denial->denial_code} {$denial->denial_reason}");
        $newClaim->save();

        // Clone service lines so the biller has the same CPT/modifier
        // skeleton to edit. Without this they'd have to re-enter
        // every line from scratch.
        foreach ($original->serviceLines as $line) {
            $cloned = $line->replicate(['paid_amount']);
            $cloned->claim_id = $newClaim->id;
            $cloned->save();
        }

        return response()->json([
            'success' => true,
            'data' => $newClaim->fresh()->load('serviceLines'),
        ], 201);
    }

    public function denialStats(Request $request): JsonResponse
    {
        $aid = $request->user()->effectiveAgencyId($request);
        $total = ClaimDenial::where('agency_id', $aid)->count();
        $open = ClaimDenial::where('agency_id', $aid)->whereIn('status', ['new', 'in_review', 'appeal_in_progress', 'pending_response'])->count();
        $totalDenied = ClaimDenial::where('agency_id', $aid)->sum('denied_amount');
        $totalRecovered = ClaimDenial::where('agency_id', $aid)->sum('recovered_amount');
        $won = ClaimDenial::where('agency_id', $aid)->where('status', 'resolved_won')->count();
        $lost = ClaimDenial::where('agency_id', $aid)->where('status', 'resolved_lost')->count();
        $appealRate = ($won + $lost) > 0 ? round($won / ($won + $lost) * 100, 1) : 0;
        $overdue = ClaimDenial::where('agency_id', $aid)->whereIn('status', ['new', 'in_review'])
            ->whereNotNull('appeal_deadline')->where('appeal_deadline', '<', now())->count();
        $byCategory = ClaimDenial::where('agency_id', $aid)
            ->selectRaw('denial_category, COUNT(*) as count, SUM(denied_amount) as total')
            ->groupBy('denial_category')->get();
        return response()->json(['success' => true, 'data' => [
            'total' => $total, 'open' => $open, 'total_denied' => $totalDenied,
            'total_recovered' => $totalRecovered, 'appeal_success_rate' => $appealRate,
            'overdue_appeals' => $overdue, 'by_category' => $byCategory,
        ]]);
    }

    // ── Payments ──

    public function payments(Request $request): JsonResponse
    {
        $query = ClaimPayment::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['billingClient:id,organization_name', 'allocations.claim:id,claim_number']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('payment_date')->get()]);
    }

    /**
     * Single payment detail — used by the PaymentDetailPage in V2.
     *
     * Eager-loads everything the page needs in one round trip:
     *   - billing client (for the practice name in the header)
     *   - creator user (who entered it, if manual)
     *   - allocations + each allocation's claim (patient, charges,
     *     dates) so the allocations table renders without per-row
     *     fetches
     *   - audit_logs entries for this ClaimPayment AND for each of
     *     its PaymentAllocations, merged + sorted newest-first.
     *     Surfaces the full lifecycle of the money for an auditor:
     *     who recorded it, when, what allocations changed and by whom.
     */
    public function showPayment(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $payment = ClaimPayment::where('agency_id', $agencyId)
            ->with([
                'billingClient:id,organization_name,contact_name',
                'creator:id,email,first_name,last_name',
                'allocations' => function ($q) {
                    $q->with(['claim:id,claim_number,patient_name,date_of_service,total_charges,total_paid,balance,status']);
                },
            ])
            ->findOrFail($id);

        // Pull the audit trail. Both ClaimPayment + PaymentAllocation
        // carry the Auditable trait so writes are captured automatically.
        // We merge the streams so the operator sees one chronological
        // history rather than two separate logs.
        $paymentLogs = \App\Models\AuditLog::where('auditable_type', \App\Models\ClaimPayment::class)
            ->where('auditable_id', $payment->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        $allocationIds = $payment->allocations->pluck('id')->all();
        $allocationLogs = empty($allocationIds)
            ? collect()
            : \App\Models\AuditLog::where('auditable_type', \App\Models\PaymentAllocation::class)
                ->whereIn('auditable_id', $allocationIds)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        $auditTrail = $paymentLogs->concat($allocationLogs)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => $payment,
                'audit_trail' => $auditTrail,
            ],
        ]);
    }

    /**
     * ERA import history. Synthesized from existing claim_payments by
     * coalescing trace_number with check_number. Reality of the data
     * layer (verified on prod 2026-05-12):
     *   - 0 of 172 payments have trace_number populated
     *   - 170 of 172 have check_number populated
     *   - All are type=eft (← Era835Importer wrote them that way)
     *
     * The 835 importer writes the 835 TRN02 (or CLP07/BPR16) into
     * check_number on older imports and trace_number on newer ones.
     * Both fields hold the same logical identifier for ERA-driven
     * payments. We coalesce: prefer trace_number, fall back to
     * check_number. Each unique key = one ERA.
     *
     * We also keep payment_type IN (eft, check) so patient_pay rows
     * never pollute the ERA view.
     *
     * Synthesizing rather than reading a dedicated era_imports table
     * because that table doesn't exist yet — and most agencies only
     * need the rollup, not the file metadata. If per-file storage
     * (raw 835 archival, file_size) is needed later, an era_imports
     * table can be added without breaking this endpoint's contract.
     */
    public function eraHistory(Request $request): JsonResponse
    {
        $aid = $request->user()->effectiveAgencyId($request);
        $payments = ClaimPayment::where('agency_id', $aid)
            ->whereIn('payment_type', ['eft', 'check'])
            // Need SOME identifier. Reject rows where both check_number
            // and trace_number are blank — those are likely cash or
            // manual patient pays mistyped as check.
            ->where(function ($q) {
                $q->whereNotNull('trace_number')->where('trace_number', '!=', '')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('check_number')->where('check_number', '!=', '');
                  });
            })
            ->with(['allocations:id,claim_payment_id,claim_id,paid_amount'])
            ->orderByDesc('payment_date')
            ->get();

        // Group by the coalesced identifier — one ERA per
        // (trace_number ?: check_number).
        $byKey = $payments->groupBy(fn ($p) => $p->trace_number ?: $p->check_number);
        $imports = [];

        foreach ($byKey as $key => $group) {
            /** @var \Illuminate\Support\Collection $group */
            $first = $group->first();
            $totalAmount = (float) $group->sum('total_amount');
            $totalPosted = (float) $group->sum('posted_amount');
            $claimCount = $group->reduce(fn ($carry, $p) => $carry + $p->allocations->count(), 0);
            $matchedCount = $group->reduce(fn ($carry, $p) => $carry + $p->allocations->where('claim_id', '!=', null)->count(), 0);
            $unmatchedCount = $claimCount - $matchedCount;

            $imports[] = [
                'id' => (string) $key,
                'trace_number' => $first->trace_number,
                'check_number' => $first->check_number,
                'payer_name' => $first->payer_name,
                'payment_date' => $first->payment_date,
                // Deposit date — what your bank says, set manually by
                // the operator after reconciling against the bank
                // statement. Pulled from the primary (oldest) payment
                // in the group. Often null today because ERA imports
                // and CSV bulk-matches don't set it automatically.
                'deposit_date' => $first->deposit_date,
                'imported_at' => $first->posted_at ?: $first->created_at,
                'created_at' => $first->created_at,
                'total_amount' => $totalAmount,
                'posted_amount' => $totalPosted,
                'claim_count' => $claimCount,
                'matched_count' => $matchedCount,
                'unmatched_count' => $unmatchedCount,
                'posted' => $matchedCount,
                'status' => $unmatchedCount > 0 ? 'partial' : 'posted',
                // The primary ClaimPayment id for this ERA group —
                // V2 uses it to deep-link the row's Check # + actions
                // straight to /rcm/payments/:id. One ERA may have
                // multiple payment rows (rare; usually one per 835
                // file), so we expose the first/oldest by payment_date.
                'payment_id' => $first->id,
                // All ids in the group — V2 falls through to a
                // payment-tab search filter when there's > 1.
                'payment_ids' => $group->pluck('id')->all(),
            ];
        }

        return response()->json(['success' => true, 'data' => $imports]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $request->validate([
            // Added 'wire' for large self-pay / out-of-network arrangements
            // and 'cash' for in-office collections. Existing types unchanged.
            'payment_type' => 'required|in:check,eft,virtual_card,patient,ach,wire,cash',
            'payment_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0.01',
        ]);

        // Duplicate-entry guard. The same payment can be entered twice
        // through two different surfaces — once via "Collect Payment" on
        // a patient page, once via "Record Payment" on the Payments tab.
        // We block silent dupes when the same (agency, payer, reference#,
        // amount) hits within ±2 days. Pass force=true to override (rare:
        // legitimate two-check payments with identical refs and amounts).
        $agencyId = $request->user()->effectiveAgencyId($request);
        $ref = $request->input('check_number') ?: $request->input('trace_number');
        if ($ref && !$request->boolean('force')) {
            $window = [
                \Carbon\Carbon::parse($request->payment_date)->subDays(2),
                \Carbon\Carbon::parse($request->payment_date)->addDays(2),
            ];
            $duplicate = ClaimPayment::where('agency_id', $agencyId)
                ->where(function ($q) use ($ref) {
                    $q->where('check_number', $ref)->orWhere('trace_number', $ref);
                })
                ->where('total_amount', $request->total_amount)
                ->whereBetween('payment_date', $window)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        'A matching payment already exists (id %d, %s, $%s on %s). Pass force=true to record it anyway.',
                        $duplicate->id,
                        $duplicate->payer_name ?: 'no payer',
                        number_format((float) $duplicate->total_amount, 2),
                        optional($duplicate->payment_date)->format('Y-m-d') ?: '?',
                    ),
                    'error' => 'duplicate_payment',
                    'existing_payment_id' => $duplicate->id,
                ], 409);
            }
        }

        // Atomicity: payment + every allocation + every claim status flip
        // must commit together. Without the transaction a partial failure
        // (e.g. one PaymentAllocation insert violates a check constraint
        // mid-loop) leaves an orphaned payment row with some allocations
        // applied and some not, plus inconsistent claim balances.
        [$payment, $claimsFlippedToPaid] = \DB::transaction(function () use ($request, $agencyId) {
            $payment = ClaimPayment::create([
                'agency_id' => $agencyId,
                'created_by' => $request->user()->id,
                'remaining_amount' => $request->total_amount,
                ...$request->only([
                    'billing_client_id', 'payer_name', 'payment_type', 'check_number',
                    'trace_number', 'payment_date', 'deposit_date', 'total_amount', 'notes',
                ]),
            ]);
            $claimsFlippedToPaid = [];
            if ($request->has('allocations')) {
                $claimIds = array_filter(array_column($request->allocations, 'claim_id'));
                // Explicit agency filter (defense-in-depth — TenantScope
                // already does this, but a future withoutGlobalScopes()
                // somewhere could regress it).
                $claimsById = Claim::whereIn('id', $claimIds)
                    ->where('agency_id', $agencyId)
                    ->get()
                    ->keyBy('id');

                foreach ($request->allocations as $alloc) {
                    PaymentAllocation::create(['claim_payment_id' => $payment->id, ...$alloc]);
                    $claim = $claimsById->get($alloc['claim_id'] ?? null);
                    if (!$claim) continue;

                    $oldStatus = $claim->status;
                    $claim->total_paid = ($claim->total_paid ?? 0) + ($alloc['paid_amount'] ?? 0);
                    $claim->balance = $claim->total_charges - $claim->total_paid - ($claim->adjustments ?? 0);
                    if ($claim->balance <= 0) $claim->status = 'paid';
                    elseif ($claim->total_paid > 0) $claim->status = 'partial_paid';
                    $claim->paid_date = $request->payment_date;
                    $claim->save();

                    if ($oldStatus !== 'paid' && $claim->status === 'paid') {
                        $claimsFlippedToPaid[] = $claim;
                    }
                }
                $payment->recalculate();
            }
            return [$payment, $claimsFlippedToPaid];
        });

        // Webhooks fire AFTER the commit so receivers never see a payment
        // that gets rolled back. A failed webhook delivery doesn't
        // poison the write.
        WebhookDispatcher::dispatch($payment->agency_id, WebhookDispatcher::PAYMENT_POSTED, WebhookPayloads::payment($payment));
        foreach ($claimsFlippedToPaid as $c) {
            WebhookDispatcher::dispatch($c->agency_id, WebhookDispatcher::CLAIM_PAID, $this->claimWebhookData($c));
        }

        $payment->load(['allocations.claim:id,claim_number', 'billingClient:id,organization_name']);
        return response()->json(['success' => true, 'data' => $payment], 201);
    }

    /**
     * Bulk match payment CSV rows to existing claims.
     * Matches by patient_name + date_of_service (+ total_charges if provided).
     * Updates claim with paid amount, status, check number, paid date, denial info.
     */
    public function bulkMatchPayments(Request $request): JsonResponse
    {
        $request->validate([
            'payments' => 'required|array|min:1|max:500',
            // Optional reconciliation map: { "checkNumber": expectedTotal }.
            'check_totals' => 'nullable|array',
            'check_totals.*' => 'numeric',
            // Gate for CSV-created records. We learned the hard way
            // (see 802379101 + the 42 date_unverified flags 2026-05-13)
            // that CSV bulk-imports treated as authoritative produce
            // a long tail of incorrect data: dates defaulted to
            // import-day, totals derived from "sum of CSV rows" rather
            // than check face value, missing claims when the export was
            // paginated. The fix isn't more patches — it's restricting
            // CSV to a reconciliation tool, not a posting one.
            //
            // Default behavior (safe): match rows against EXISTING
            // ClaimPayment + Claim records, update where applicable,
            // do NOT mint new ones. The operator gets a report of
            // what would have been created and re-runs with the flag
            // if they truly want to. For real posting they should
            // be using the 835 ERA importer or Availity API pull.
            'allow_create_payments' => 'nullable|boolean',
            'allow_create_claims' => 'nullable|boolean',
        ]);
        $agencyId = $request->user()->effectiveAgencyId($request);
        $allowCreatePayments = $request->boolean('allow_create_payments');
        $allowCreateClaims = $request->boolean('allow_create_claims');
        $matched = 0;
        $created = 0;
        $skippedPaymentCreates = [];
        $skippedClaimCreates = [];
        $errors = [];
        // Track every ClaimPayment touched during the loop so we can
        // recalculate() each at the end. Without this, posted_amount
        // and remaining_amount drift from the actual sum of allocations
        // — exactly how check 802379101 ended up showing posted_amount=$0
        // with $9,161 of allocations linked.
        $touchedPaymentIds = [];
        $expectedTotals = (array) $request->input('check_totals', []);

        foreach ($request->payments as $i => $row) {
            // Per-row transaction: if any DB write inside this iteration
            // fails (constraint violation, FK conflict, etc.) the row
            // rolls back cleanly. The outer loop continues with the next
            // row — so one bad row in a 500-row CSV doesn't lose the
            // other 499. The error is recorded in $errors for the caller.
            try {
                \DB::beginTransaction();
                $patientName = $row['patient_name'] ?? null;
                $dos = $row['date_of_service'] ?? null;
                $claimNumber = $row['claim_number'] ?? null;

                if (!$dos) {
                    $errors[] = "Row " . ($i + 1) . ": date_of_service required";
                    \DB::rollBack();
                    continue;
                }

                // Normalize name: strip middle initials, special chars, extra spaces
                $normalizeName = function($name) {
                    if (!$name) return '';
                    $n = mb_strtoupper($name);
                    // Fix encoding issues (â€™ -> ', etc.)
                    $n = preg_replace('/\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2|\xE2\x80\x99|â€™/', "'", $n);
                    // Remove special chars except letters and spaces
                    $n = preg_replace('/[^A-Z\s]/', '', $n);
                    // Split into parts, remove single-letter middle initials
                    $parts = preg_split('/\s+/', trim($n));
                    $parts = array_values(array_filter($parts, fn($p) => strlen($p) > 1));
                    return implode(' ', $parts);
                };

                $dosDate = substr($dos, 0, 10);
                $claim = null;

                // Match 1: exact claim_number
                if ($claimNumber) {
                    $claim = Claim::where('agency_id', $agencyId)
                        ->where('claim_number', $claimNumber)
                        ->first();
                }

                // Match 2: exact name + DOS + charges
                if (!$claim && $patientName) {
                    $query = Claim::where('agency_id', $agencyId)
                        ->whereRaw('UPPER(patient_name) = ?', [strtoupper($patientName)])
                        ->whereDate('date_of_service', $dosDate);
                    if (!empty($row['total_charges'])) {
                        $query->where('total_charges', (float) $row['total_charges']);
                    }
                    $claim = $query->first();
                }

                // Match 3: fuzzy name (no middle initial) + DOS + charges
                if (!$claim && $patientName) {
                    $normalized = $normalizeName($patientName);
                    if ($normalized) {
                        $candidates = Claim::where('agency_id', $agencyId)
                            ->whereDate('date_of_service', $dosDate)
                            ->where('total_charges', (float) ($row['total_charges'] ?? 0))
                            ->get();
                        foreach ($candidates as $c) {
                            if ($normalizeName($c->patient_name) === $normalized) {
                                $claim = $c;
                                break;
                            }
                        }
                    }
                }

                // Match 4: last name + DOS + charges (last resort)
                if (!$claim && $patientName) {
                    $lastNameParts = preg_split('/\s+/', trim($patientName));
                    $lastName = end($lastNameParts);
                    if ($lastName && strlen($lastName) > 1) {
                        $claim = Claim::where('agency_id', $agencyId)
                            ->whereRaw('UPPER(patient_name) LIKE ?', ['%' . strtoupper($lastName)])
                            ->whereDate('date_of_service', $dosDate)
                            ->where('total_charges', (float) ($row['total_charges'] ?? 0))
                            ->first();
                    }
                }

                if ($claim) {
                    // Defense-in-depth: TenantScope already covers this,
                    // but every $claim sourced from the match branches
                    // above must belong to the effective agency.
                    if ((int) $claim->agency_id !== $agencyId) {
                        $errors[] = "Row " . ($i + 1) . ": claim not in your agency";
                        \DB::rollBack();
                        continue;
                    }
                    // Update existing claim with payment data
                    $oldStatus = $claim->status;
                    $createdPaymentForWebhook = null;
                    $paidAmount = (float) ($row['total_paid'] ?? $row['paid_amount'] ?? 0);
                    $patientResp = (float) ($row['patient_responsibility'] ?? 0);
                    $denialReason = $row['denial_reason'] ?? null;
                    $status = strtolower($row['status'] ?? '');
                    $checkNumber = $row['check_number'] ?? null;

                    if ($paidAmount > 0) {
                        $claim->total_paid = $paidAmount;
                        $claim->patient_responsibility = $patientResp;
                        $claim->balance = $claim->total_charges - $paidAmount - $patientResp;
                        $claim->status = $claim->balance <= 0 ? 'paid' : 'partial_paid';
                        $claim->paid_date = $row['paid_date'] ?? now()->toDateString();
                        $claim->check_number = $checkNumber ?? $claim->check_number;

                        // Pro-rate the paid amount across this claim's
                        // service lines, weighted by line charges. Without
                        // this, claims.total_paid is set but every line
                        // shows paid_amount=0, which silently breaks every
                        // CPT-level analysis downstream (rate analysis,
                        // payer detail CPT tab, underpayments). We only
                        // overwrite line paid_amount when (a) charges>0 to
                        // get a divisor and (b) the current line value is
                        // empty — never clobber a value an ERA importer
                        // already wrote line-by-line.
                        $serviceLines = $claim->serviceLines()->get();
                        $sumLineCharges = $serviceLines->sum('charges');
                        if ($sumLineCharges > 0) {
                            $accumPaid = 0.0;
                            $lastIdx = $serviceLines->count() - 1;
                            foreach ($serviceLines as $idx => $sl) {
                                if ((float) $sl->paid_amount > 0.005) continue;
                                // Last line takes the rounding remainder
                                // so the line sum equals the claim total
                                // exactly. Prevents 1¢ drift breaking
                                // recalculate() invariants later.
                                $linePaid = $idx === $lastIdx
                                    ? round($paidAmount - $accumPaid, 2)
                                    : round($paidAmount * ((float) $sl->charges / $sumLineCharges), 2);
                                $sl->paid_amount = max(0, $linePaid);
                                $sl->save();
                                $accumPaid += $sl->paid_amount;
                            }
                        }

                        // Dedup BEFORE we mint a synthetic check number,
                        // because the synthetic generator uses sequence
                        // counts that change every run — re-running the
                        // same CSV would otherwise produce PAY-…-001,
                        // PAY-…-002, PAY-…-003 for the same physical
                        // payment. That's exactly how 15 duplicate
                        // payments leaked into prod (3 dupe groups,
                        // \$3,306 phantom dollars discovered 2026-05-13).
                        //
                        // Lookup order:
                        //   1. Real check#/trace# from the CSV row
                        //   2. Existing allocation for this claim from
                        //      ANY payment (idempotent re-import of the
                        //      same row finds the prior payment)
                        //   3. Mint a synthetic check# (true first import)
                        $existingPayment = null;
                        if ($checkNumber) {
                            // Real check number in the CSV — trust it.
                            $existingPayment = ClaimPayment::where('agency_id', $agencyId)
                                ->where('check_number', $checkNumber)->first();
                        } else {
                            // No real check number — look for an existing
                            // allocation for this claim with the same
                            // paid amount. If found, attach to that
                            // payment instead of creating a duplicate.
                            $existingAlloc = PaymentAllocation::where('claim_id', $claim->id)
                                ->where('paid_amount', $paidAmount)
                                ->whereHas('payment', fn ($q) => $q->where('agency_id', $agencyId))
                                ->first();
                            if ($existingAlloc) {
                                $existingPayment = ClaimPayment::find($existingAlloc->claim_payment_id);
                                $checkNumber = $existingPayment?->check_number;
                                $claim->check_number = $checkNumber ?? $claim->check_number;
                            }
                        }

                        if (!$existingPayment) {
                            // No matching ClaimPayment in our DB. By
                            // default we DO NOT create one from the
                            // CSV — the operator should be uploading
                            // an 835 / pulling from Availity for posting.
                            // CSV is a reconciliation tool, not a
                            // source of authoritative payment dates +
                            // totals. Skip the row and report it.
                            if (!$allowCreatePayments) {
                                $skippedPaymentCreates[] = [
                                    'row' => $i + 1,
                                    'claim_number' => $claim->claim_number,
                                    'patient' => $claim->patient_name,
                                    'check_number' => $checkNumber,
                                    'paid_amount' => $paidAmount,
                                    'reason' => 'no_matching_payment_in_db',
                                ];
                                \DB::rollBack();
                                continue;
                            }

                            // Operator explicitly opted in. Mint a
                            // synthetic check# if the CSV didn't supply one.
                            if (!$checkNumber) {
                                $payDate = $row['paid_date'] ?? now()->toDateString();
                                $datePart = str_replace('-', '', substr($payDate, 0, 10));
                                $seqNum = ClaimPayment::where('agency_id', $agencyId)
                                    ->where('check_number', 'LIKE', "PAY-{$datePart}-%")
                                    ->count() + 1;
                                $checkNumber = "PAY-{$datePart}-" . str_pad($seqNum, 3, '0', STR_PAD_LEFT);
                                $claim->check_number = $checkNumber;
                            }
                            $existingPayment = ClaimPayment::create([
                                'agency_id' => $agencyId,
                                'created_by' => $request->user()->id,
                                'payer_name' => $row['payer_name'] ?? $claim->payer_name,
                                'payment_type' => 'eft',
                                'check_number' => $checkNumber,
                                // Operator-supplied paid_date OR null.
                                // We deliberately don't fall back to now()
                                // anymore — that's how 42 payments got
                                // wrong dates last time. If the row has
                                // no date, the payment gets created with
                                // payment_date=null and status='date_unverified'
                                // so it surfaces in the queue for review.
                                'payment_date' => $row['paid_date'] ?? null,
                                'total_amount' => $paidAmount,
                                'remaining_amount' => 0,
                                'status' => ($row['paid_date'] ?? null) ? 'posted' : 'date_unverified',
                            ]);
                            $createdPaymentForWebhook = $existingPayment;
                        } else {
                            $existingPayment->increment('total_amount', $paidAmount);
                        }
                        // Create allocation linking payment to claim
                        PaymentAllocation::firstOrCreate(
                            ['claim_payment_id' => $existingPayment->id, 'claim_id' => $claim->id],
                            ['paid_amount' => $paidAmount, 'charged_amount' => $claim->total_charges, 'patient_responsibility' => $patientResp]
                        );
                        // Mark for post-loop recalculate() so posted_amount
                        // and remaining_amount reflect the actual sum of
                        // allocations (not just the running increment).
                        $touchedPaymentIds[$existingPayment->id] = true;
                    } elseif ($status === 'denied' || $denialReason) {
                        $claim->status = 'denied';
                        $claim->denial_reason = $denialReason;

                        // Create ClaimDenial record
                        ClaimDenial::firstOrCreate(
                            ['agency_id' => $agencyId, 'claim_id' => $claim->id],
                            [
                                'billing_client_id' => $claim->billing_client_id,
                                'denial_category' => 'other',
                                'denial_reason' => $denialReason ?? 'Denied per payer remittance',
                                'denied_amount' => $claim->total_charges,
                                'status' => 'new',
                                'priority' => 'normal',
                                'denial_date' => $dosDate,
                                'created_by' => $request->user()->id,
                            ]
                        );
                    } elseif ($status && in_array($status, ['paid', 'denied', 'pending', 'submitted', 'partial_paid'])) {
                        $claim->status = $status;
                    }

                    // Update payer info if provided and different
                    if (!empty($row['payer_name'])) {
                        $claim->payer_name = $row['payer_name'];
                    }
                    if (!empty($row['payer_id_number'])) {
                        $claim->payer_id_number = $row['payer_id_number'];
                    }

                    $claim->save();

                    // Fire webhook events for transitions detected in this row.
                    if ($createdPaymentForWebhook) {
                        WebhookDispatcher::dispatch($claim->agency_id, WebhookDispatcher::PAYMENT_POSTED, WebhookPayloads::payment($createdPaymentForWebhook));
                    }
                    $this->fireClaimStatusEvent($claim, $oldStatus);

                    // Sync charge entries to match claim status
                    ChargeEntry::where('claim_id', $claim->id)->update(['status' => $claim->status]);

                    $matched++;
                } else {
                    // No matching claim in our DB. Same default-safe
                    // policy as payments: CSV is a reconciliation tool,
                    // not a posting one. Don't mint a claim from a
                    // CSV row without explicit opt-in. The operator
                    // should be submitting claims via 837 and reading
                    // payments back via 835.
                    if (!$allowCreateClaims) {
                        $skippedClaimCreates[] = [
                            'row' => $i + 1,
                            'claim_number' => $row['claim_number'] ?? null,
                            'patient' => $patientName,
                            'dos' => $dos,
                            'total_charges' => (float) ($row['total_charges'] ?? 0),
                            'reason' => 'no_matching_claim_in_db',
                        ];
                        \DB::rollBack();
                        continue;
                    }

                    // Operator opted in. Same body as before.
                    $totalCharges = (float) ($row['total_charges'] ?? 0);
                    $totalPaid = (float) ($row['total_paid'] ?? $row['paid_amount'] ?? 0);
                    $status = strtolower($row['status'] ?? '');
                    if ($totalPaid > 0 && !$status) $status = 'paid';
                    if (!$status) $status = 'submitted';

                    $newClaim = Claim::create([
                        'agency_id' => $agencyId,
                        'claim_number' => $row['claim_number'] ?? $row['payer_id_number'] ?? 'PAY-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                        'created_by' => $request->user()->id,
                        'claim_type' => '837P',
                        'status' => $status,
                        'billing_client_id' => $row['billing_client_id'] ?? null,
                        'provider_name' => $row['provider_name'] ?? null,
                        'patient_name' => $patientName,
                        'patient_dob' => $row['patient_dob'] ?? null,
                        'patient_member_id' => $row['patient_member_id'] ?? null,
                        'payer_name' => $row['payer_name'] ?? null,
                        'payer_id_number' => $row['payer_id_number'] ?? null,
                        'date_of_service' => $dos,
                        'total_charges' => $totalCharges,
                        'total_paid' => $totalPaid,
                        'patient_responsibility' => (float) ($row['patient_responsibility'] ?? 0),
                        'balance' => $totalCharges - $totalPaid,
                        // No more fall-back to now() when paid_date is
                        // missing — that defaulting is what created the
                        // 42 wrong-dated payments. If no source date,
                        // leave it null and the operator sees the claim
                        // in a date_unverified state.
                        'paid_date' => $row['paid_date'] ?? null,
                        'check_number' => $row['check_number'] ?? null,
                        'denial_reason' => $row['denial_reason'] ?? null,
                        'submission_method' => 'electronic',
                        'submitted_date' => $row['submitted_date'] ?? null,
                    ]);
                    // Treat as a transition from null so the helper picks the right event.
                    $this->fireClaimStatusEvent($newClaim, null);
                    $created++;
                }
                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        // ── Post-loop reconciliation ──
        //
        // The CSV path historically left ClaimPayment.posted_amount at $0
        // and silently accepted "sum of rows in this CSV" as the check
        // total. Both wrong. Fix:
        //   1. Recalculate every touched payment so posted_amount /
        //      remaining_amount match the sum of allocations.
        //   2. If the operator supplied check_totals (the actual check
        //      face value from the EOB header), compare and mark
        //      mismatches with status='unbalanced'. Return them in the
        //      response so the operator sees the gap immediately.
        // Without (2), incomplete CSVs (paginated exports, filtered
        // by NPI/date) post a partial total and nothing flags it —
        // exactly how check 802379101 ended up $226.42 short of its
        // physical face value.
        $unbalanced = [];
        if (!empty($touchedPaymentIds)) {
            $touched = ClaimPayment::where('agency_id', $agencyId)
                ->whereIn('id', array_keys($touchedPaymentIds))
                ->get();
            foreach ($touched as $payment) {
                $payment->recalculate();
                $checkNo = $payment->check_number;
                if (!$checkNo || !array_key_exists($checkNo, $expectedTotals)) continue;
                $expected = (float) $expectedTotals[$checkNo];
                $actual = (float) $payment->posted_amount;
                // Penny-tolerance for float math; anything beyond a cent
                // is a real gap worth flagging.
                if (abs($expected - $actual) > 0.01) {
                    $payment->status = 'unbalanced';
                    $payment->total_amount = $expected;
                    $payment->remaining_amount = round($expected - $actual, 2);
                    $payment->save();
                    $unbalanced[] = [
                        'payment_id' => $payment->id,
                        'check_number' => $checkNo,
                        'expected_total' => $expected,
                        'allocated_total' => $actual,
                        'gap' => round($expected - $actual, 2),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'matched' => $matched,
            'created' => $created,
            'errors' => $errors,
            'total_submitted' => count($request->payments),
            // Caller-visible reconciliation results. Empty array when
            // every supplied check_total matched the imported rows
            // (or when no check_totals were supplied at all).
            'unbalanced' => $unbalanced,
            'recalculated_payment_ids' => array_keys($touchedPaymentIds),
            // Rows the CSV would have created brand-new records for.
            // We refuse by default (CSV is reconciliation, not posting)
            // and surface them here so the operator sees what got
            // skipped. Re-run with allow_create_payments=true and/or
            // allow_create_claims=true to opt in. The right answer
            // for ongoing posting is uploading the 835 / pulling
            // from Availity instead.
            'skipped_payment_creates' => $skippedPaymentCreates,
            'skipped_claim_creates' => $skippedClaimCreates,
            'csv_create_gate' => [
                'allow_create_payments' => $allowCreatePayments,
                'allow_create_claims' => $allowCreateClaims,
                'hint' => 'CSV bulk-match defaults to MATCH-ONLY mode. Use the 835 ERA upload or the Availity pull for new payment posting. To create from CSV anyway, re-submit with allow_create_payments=true.',
            ],
        ], 201);
    }

    public function updatePayment(Request $request, int $id): JsonResponse
    {
        $payment = ClaimPayment::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $payment->update($request->only([
            'billing_client_id', 'payer_name', 'payment_type', 'check_number',
            'trace_number', 'payment_date', 'deposit_date', 'total_amount', 'status', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $payment]);
    }

    public function destroyPayment(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $payment = ClaimPayment::where('agency_id', $agencyId)->findOrFail($id);

        // Cascade: delete child PaymentAllocations BEFORE the parent
        // payment, and re-derive each touched claim's total_paid +
        // balance + status from its remaining allocations.
        //
        // Without the cascade (the old behavior), deleting a payment
        // soft-deleted the parent but left allocations dangling. They
        // still summed into ClaimPayment::sum('paid_amount') aggregates
        // and still inflated claim.total_paid, producing negative
        // claim balances and the "$2,033 gap" we just hunted down.
        //
        // PaymentAllocation has no SoftDeletes trait — these are hard
        // deletes. That's intentional: an orphaned allocation is
        // strictly noise; soft-deleting them would just preserve the
        // bug under a different filter.
        \DB::transaction(function () use ($payment) {
            $affectedClaimIds = $payment->allocations()->pluck('claim_id')->unique()->all();
            $payment->allocations()->delete();
            $payment->delete();

            foreach ($affectedClaimIds as $cid) {
                $claim = Claim::find($cid);
                if (!$claim) continue;
                $remainingPaid = (float) \App\Models\PaymentAllocation::where('claim_id', $cid)
                    ->whereHas('payment', fn ($q) => $q->where('agency_id', $claim->agency_id))
                    ->sum('paid_amount');
                $remainingPtResp = (float) \App\Models\PaymentAllocation::where('claim_id', $cid)
                    ->whereHas('payment', fn ($q) => $q->where('agency_id', $claim->agency_id))
                    ->sum('patient_responsibility');
                $claim->total_paid = $remainingPaid;
                $claim->patient_responsibility = $remainingPtResp;
                $claim->balance = (float) $claim->total_charges - $remainingPaid - (float) ($claim->adjustments ?? 0);
                // Demote status only when the demotion makes sense —
                // a paid claim losing payment goes back to partial_paid
                // (if some remains) or submitted (if none does).
                // Don't touch denied / appealed / written_off — those
                // are explicit human decisions.
                if (!in_array($claim->status, ['denied', 'appealed', 'rejected', 'written_off'], true)) {
                    if ($claim->balance <= 0.005 && $remainingPaid > 0) {
                        $claim->status = 'paid';
                    } elseif ($remainingPaid > 0) {
                        $claim->status = 'partial_paid';
                    } else {
                        $claim->status = 'submitted';
                    }
                }
                $claim->save();
            }
        });

        return response()->json(['success' => true, 'cascaded_to_claims' => count($payment->allocations ?? [])]);
    }

    // ── Charge Capture ──

    public function charges(Request $request): JsonResponse
    {
        $query = ChargeEntry::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->with(['billingClient:id,organization_name']);
        if ($cid = $request->input('billing_client_id')) $query->where('billing_client_id', $cid);
        if ($s = $request->input('status')) $query->where('status', $s);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('date_of_service')->get()]);
    }

    public function storeCharge(Request $request): JsonResponse
    {
        $request->validate([
            'date_of_service' => 'required|date',
            'cpt_code' => 'required|string|max:10',
            'charge_amount' => 'required|numeric|min:0',
        ]);
        $charge = ChargeEntry::create([
            'agency_id' => $request->user()->effectiveAgencyId($request),
            'created_by' => $request->user()->id,
            ...$request->only([
                'billing_client_id', 'provider_id', 'provider_name', 'patient_name', 'payer_name',
                'date_of_service', 'cpt_code', 'cpt_description', 'modifiers', 'icd_codes',
                'icd_descriptions', 'units', 'charge_amount', 'allowed_amount', 'place_of_service',
                'facility_name', 'authorization_number', 'status', 'notes',
            ]),
        ]);
        return response()->json(['success' => true, 'data' => $charge], 201);
    }

    public function updateCharge(Request $request, int $id): JsonResponse
    {
        $charge = ChargeEntry::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id);
        $charge->update($request->only([
            'billing_client_id', 'provider_id', 'provider_name', 'patient_name', 'payer_name',
            'date_of_service', 'cpt_code', 'cpt_description', 'modifiers', 'icd_codes',
            'icd_descriptions', 'units', 'charge_amount', 'allowed_amount', 'place_of_service',
            'facility_name', 'authorization_number', 'status', 'claim_id', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $charge]);
    }

    public function destroyCharge(Request $request, int $id): JsonResponse
    {
        ChargeEntry::where('agency_id', $request->user()->effectiveAgencyId($request))->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function bulkImportCharges(Request $request): JsonResponse
    {
        $request->validate(['charges' => 'required|array|min:1|max:500']);
        $agencyId = $request->user()->effectiveAgencyId($request);
        $userId = $request->user()->id;
        $created = 0;
        $errors = [];

        foreach ($request->charges as $i => $row) {
            try {
                if (empty($row['date_of_service'])) { $errors[] = "Row " . ($i + 1) . ": date_of_service required"; continue; }
                if (empty($row['cpt_code'])) { $errors[] = "Row " . ($i + 1) . ": cpt_code required"; continue; }

                ChargeEntry::create([
                    'agency_id' => $agencyId,
                    'created_by' => $userId,
                    'billing_client_id' => $row['billing_client_id'] ?? null,
                    'provider_name' => $row['provider_name'] ?? null,
                    'patient_name' => $row['patient_name'] ?? null,
                    'payer_name' => $row['payer_name'] ?? null,
                    'date_of_service' => $row['date_of_service'],
                    'cpt_code' => $row['cpt_code'],
                    'cpt_description' => $row['cpt_description'] ?? '',
                    'modifiers' => $row['modifiers'] ?? '',
                    'icd_codes' => $row['icd_codes'] ?? '',
                    'icd_descriptions' => $row['icd_descriptions'] ?? '',
                    'units' => $row['units'] ?? 1,
                    'charge_amount' => $row['charge_amount'] ?? $row['total_charges'] ?? 0,
                    'allowed_amount' => $row['allowed_amount'] ?? 0,
                    'place_of_service' => $row['place_of_service'] ?? null,
                    'authorization_number' => $row['authorization_number'] ?? null,
                    'status' => $row['status'] ?? 'pending',
                    'notes' => $row['notes'] ?? null,
                ]);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        return response()->json(['success' => true, 'imported' => $created, 'errors' => $errors, 'total_submitted' => count($request->charges)], 201);
    }

    // ── AR Aging ──

    public function arAging(Request $request): JsonResponse
    {
        $aid = $request->user()->effectiveAgencyId($request);
        $openStatuses = ['submitted', 'acknowledged', 'pending', 'partial_paid', 'in_process'];
        $claims = Claim::where('agency_id', $aid)->whereIn('status', $openStatuses)
            ->with(['billingClient:id,organization_name'])->where('balance', '>', 0)
            ->orderBy('date_of_service')->get();

        $now = now();
        $buckets = ['0_30' => [], '31_60' => [], '61_90' => [], '91_plus' => []];
        $byPayer = [];
        // New: aggregate by the rendering provider too. Operators care
        // because some providers consistently have downstream billing
        // problems (bad documentation, wrong taxonomy, missing modifier
        // habits). A by-provider rollup surfaces those patterns without
        // requiring a separate report.
        $byProvider = [];

        foreach ($claims as $c) {
            // diffInDays returns a SIGNED int — negative when the
            // target is in the past. We want age-in-days (always
            // positive), so abs() it. Without this, every claim with
            // a past DOS lands in 0_30 because -347 <= 30 is true.
            $days = (int) abs($now->diffInDays($c->date_of_service));
            $bucket = $days <= 30 ? '0_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '91_plus'));
            $buckets[$bucket][] = $c;

            $payer = $c->payer_name ?: 'Unknown';
            if (!isset($byPayer[$payer])) $byPayer[$payer] = ['payer' => $payer, 'total' => 0, 'count' => 0, 'days_sum' => 0];
            $byPayer[$payer]['total'] += $c->balance;
            $byPayer[$payer]['count']++;
            $byPayer[$payer]['days_sum'] += $days;

            $provider = $c->rendering_provider_name ?: ($c->provider_name ?: 'Unknown');
            if (!isset($byProvider[$provider])) {
                $byProvider[$provider] = [
                    'provider' => $provider,
                    'total' => 0,
                    'count' => 0,
                    'days_sum' => 0,
                    'oldest_days' => 0,
                ];
            }
            $byProvider[$provider]['total'] += $c->balance;
            $byProvider[$provider]['count']++;
            $byProvider[$provider]['days_sum'] += $days;
            if ($days > $byProvider[$provider]['oldest_days']) {
                $byProvider[$provider]['oldest_days'] = $days;
            }
        }
        foreach ($byPayer as &$p) {
            $p['avg_days'] = $p['count'] > 0 ? round($p['days_sum'] / $p['count']) : 0;
            unset($p['days_sum']);
        }
        unset($p); // break reference

        foreach ($byProvider as &$pr) {
            $pr['avg_days'] = $pr['count'] > 0 ? round($pr['days_sum'] / $pr['count']) : 0;
            unset($pr['days_sum']);
        }
        unset($pr);

        return response()->json(['success' => true, 'data' => [
            'total_ar' => $claims->sum('balance'),
            'avg_days_in_ar' => $claims->count() > 0 ? round($claims->avg(fn($c) => abs($now->diffInDays($c->date_of_service)))) : 0,
            'claim_count' => $claims->count(),
            'buckets' => [
                '0_30' => ['count' => count($buckets['0_30']), 'total' => collect($buckets['0_30'])->sum('balance')],
                '31_60' => ['count' => count($buckets['31_60']), 'total' => collect($buckets['31_60'])->sum('balance')],
                '61_90' => ['count' => count($buckets['61_90']), 'total' => collect($buckets['61_90'])->sum('balance')],
                '91_plus' => ['count' => count($buckets['91_plus']), 'total' => collect($buckets['91_plus'])->sum('balance')],
            ],
            'by_payer' => array_values($byPayer),
            'by_provider' => array_values($byProvider),
            'claims' => $claims,
        ]]);
    }

    private function fireClaimStatusEvent(Claim $claim, ?string $oldStatus): void
    {
        if ($oldStatus === $claim->status) return;
        $eventMap = [
            'submitted' => WebhookDispatcher::CLAIM_SUBMITTED,
            'paid'      => WebhookDispatcher::CLAIM_PAID,
            'denied'    => WebhookDispatcher::CLAIM_DENIED,
        ];
        if (isset($eventMap[$claim->status])) {
            WebhookDispatcher::dispatch($claim->agency_id, $eventMap[$claim->status], WebhookPayloads::claim($claim));
        }
    }
}
