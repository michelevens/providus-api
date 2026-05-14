<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Threaded email messaging built on top of communication_logs.
 *
 * Why this lives on communication_logs (not a separate "messages"
 * table): a claim's conversation history should mix phone calls,
 * emails, and faxes in one timeline. Phone calls already write to
 * communication_logs via LogCallModal. Splitting messages off would
 * fragment that history and double the read paths.
 *
 * Threading model:
 *   - First outbound message: thread_id = its own id (set after
 *     insert), parent_id = NULL.
 *   - Reply: thread_id = first message's thread_id, parent_id =
 *     the message being replied to.
 *   - Inbound webhook reply (future): same shape with direction=inbound.
 *
 * Delivery is via Resend, the same provider PaymentLinkController
 * and NotificationSendController already use. resend_id is the
 * message id Resend returns; the webhook updates delivery_status
 * and timestamps when delivered/bounced events arrive.
 */
class MessageController extends Controller
{
    /**
     * List threads (one row per thread = the most recent message in
     * each). Optional filters: entity, recipient, status.
     */
    public function threads(Request $request): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);

        // One row per thread = the LAST message in each thread. Easiest
        // honest way to compute: pull thread_ids ordered by max(created_at),
        // then fetch the latest message per thread.
        $threadQuery = CommunicationLog::query()
            ->where('agency_id', $agencyId)
            ->where('channel', 'email')
            ->whereNotNull('thread_id');

        if ($request->filled('entity_type') && $request->filled('entity_id')) {
            $threadQuery->where('entity_type', $request->input('entity_type'))
                ->where('entity_id', $request->input('entity_id'));
        }
        if ($request->filled('claim_id')) {
            $threadQuery->where('claim_id', $request->input('claim_id'));
        }
        if ($request->filled('billing_client_id')) {
            $threadQuery->where('billing_client_id', $request->input('billing_client_id'));
        }
        if ($request->filled('application_id')) {
            $threadQuery->where('application_id', $request->input('application_id'));
        }
        if ($request->filled('provider_id')) {
            $threadQuery->where('provider_id', $request->input('provider_id'));
        }
        if ($q = $request->input('search')) {
            $threadQuery->where(function ($w) use ($q) {
                $w->where('subject', 'ilike', "%{$q}%")
                  ->orWhere('recipient_email', 'ilike', "%{$q}%")
                  ->orWhere('recipient_name', 'ilike', "%{$q}%")
                  ->orWhere('body', 'ilike', "%{$q}%");
            });
        }

        // Get distinct thread_ids ordered by recency of the latest message in each.
        $threadIds = (clone $threadQuery)
            ->selectRaw('thread_id, max(created_at) as last_at')
            ->groupBy('thread_id')
            ->orderByDesc('last_at')
            ->limit($request->integer('per_page', 50))
            ->pluck('thread_id');

        if ($threadIds->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Fetch every message in those threads in one query, group by
        // thread_id in PHP. With 50 threads × ~5 messages avg that's
        // 250 rows — well within a single-query budget.
        $allMessages = CommunicationLog::where('agency_id', $agencyId)
            ->whereIn('thread_id', $threadIds)
            ->with('creator:id,first_name,last_name')
            ->orderBy('thread_id')
            ->orderBy('created_at')
            ->get();

        $byThread = $allMessages->groupBy('thread_id');

        $threads = $threadIds->map(function ($tid) use ($byThread) {
            $messages = $byThread->get($tid, collect());
            $first = $messages->first();
            $last = $messages->last();
            return [
                'thread_id' => (int) $tid,
                'subject' => $first?->subject,
                'recipient_email' => $first?->recipient_email,
                'recipient_name' => $first?->recipient_name,
                'entity_type' => $first?->entity_type,
                'entity_id' => $first?->entity_id ? (int) $first->entity_id : null,
                'claim_id' => $first?->claim_id ? (int) $first->claim_id : null,
                'billing_client_id' => $first?->billing_client_id ? (int) $first->billing_client_id : null,
                'application_id' => $first?->application_id ? (int) $first->application_id : null,
                'provider_id' => $first?->provider_id ? (int) $first->provider_id : null,
                'message_count' => $messages->count(),
                'unread_count' => $messages->whereNull('read_at')
                    ->where('direction', 'inbound')
                    ->count(),
                'last_message_at' => $last?->created_at?->toIso8601String(),
                'last_direction' => $last?->direction,
                'last_status' => $last?->delivery_status,
                'last_preview' => $last ? \Illuminate\Support\Str::limit(strip_tags($last->body ?? ''), 120) : null,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $threads]);
    }

    /**
     * One thread = subject metadata + every message in order.
     */
    public function showThread(Request $request, int $threadId): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);

        $messages = CommunicationLog::where('agency_id', $agencyId)
            ->where('thread_id', $threadId)
            ->with('creator:id,first_name,last_name')
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'Thread not found'], 404);
        }

        $first = $messages->first();

        return response()->json([
            'success' => true,
            'data' => [
                'thread_id' => $threadId,
                'subject' => $first->subject,
                'recipient_email' => $first->recipient_email,
                'recipient_name' => $first->recipient_name,
                'entity_type' => $first->entity_type,
                'entity_id' => $first->entity_id ? (int) $first->entity_id : null,
                'claim_id' => $first->claim_id ? (int) $first->claim_id : null,
                'billing_client_id' => $first->billing_client_id ? (int) $first->billing_client_id : null,
                'application_id' => $first->application_id ? (int) $first->application_id : null,
                'provider_id' => $first->provider_id ? (int) $first->provider_id : null,
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Send a new message — starts a new thread.
     *
     * Required: recipient_email, subject, body.
     * Optional entity binding via any of: claim_id, billing_client_id,
     * application_id, provider_id, or generic entity_type/entity_id.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'recipient_email' => 'required|email|max:255',
            'recipient_name' => 'nullable|string|max:200',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:50000',
            'claim_id' => 'nullable|integer|exists:claims,id',
            'billing_client_id' => 'nullable|integer|exists:billing_clients,id',
            'application_id' => 'nullable|integer|exists:applications,id',
            'provider_id' => 'nullable|integer|exists:providers,id',
            'entity_type' => 'nullable|string|max:32',
            'entity_id' => 'nullable|integer',
        ]);

        $agencyId = $request->user()->effectiveAgencyId($request);
        $user = $request->user();
        $html = $this->renderEmailHtml($request->subject, $request->body, $user);

        $log = DB::transaction(function () use ($request, $agencyId, $user, $html) {
            $log = CommunicationLog::create([
                'agency_id' => $agencyId,
                'created_by' => $user->id,
                'channel' => 'email',
                'direction' => 'outbound',
                'subject' => $request->subject,
                'body' => $request->body,
                'html_body' => $html,
                'recipient_email' => strtolower(trim($request->recipient_email)),
                'recipient_name' => $request->recipient_name,
                'claim_id' => $request->claim_id,
                'billing_client_id' => $request->billing_client_id,
                'application_id' => $request->application_id,
                'provider_id' => $request->provider_id,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'delivery_status' => 'queued',
                'outcome' => 'sent',
            ]);
            // Thread id = own id for the first message. Self-reference
            // gives us a stable thread key without a separate sequence.
            $log->thread_id = $log->id;
            $log->save();
            return $log;
        });

        $this->deliver($log);

        return response()->json(['success' => true, 'data' => $log->fresh()], 201);
    }

    /**
     * Reply on an existing thread. Subject defaults to "Re: <original>"
     * if not provided.
     */
    public function reply(Request $request, int $threadId): JsonResponse
    {
        $request->validate([
            'body' => 'required|string|max:50000',
            'subject' => 'nullable|string|max:255',
        ]);

        $agencyId = $request->user()->effectiveAgencyId($request);
        $user = $request->user();

        $parent = CommunicationLog::where('agency_id', $agencyId)
            ->where('thread_id', $threadId)
            ->orderByDesc('created_at')
            ->firstOrFail();

        $subject = $request->subject ?: (str_starts_with($parent->subject, 'Re: ')
            ? $parent->subject
            : 'Re: ' . $parent->subject);

        $html = $this->renderEmailHtml($subject, $request->body, $user);

        $log = CommunicationLog::create([
            'agency_id' => $agencyId,
            'created_by' => $user->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'thread_id' => $threadId,
            'parent_id' => $parent->id,
            'subject' => $subject,
            'body' => $request->body,
            'html_body' => $html,
            // Carry forward recipient + entity context from the thread's
            // first message — the operator doesn't have to re-pick them.
            'recipient_email' => $parent->recipient_email,
            'recipient_name' => $parent->recipient_name,
            'claim_id' => $parent->claim_id,
            'billing_client_id' => $parent->billing_client_id,
            'application_id' => $parent->application_id,
            'provider_id' => $parent->provider_id,
            'entity_type' => $parent->entity_type,
            'entity_id' => $parent->entity_id,
            'delivery_status' => 'queued',
            'outcome' => 'sent',
        ]);

        $this->deliver($log);

        return response()->json(['success' => true, 'data' => $log->fresh()], 201);
    }

    /**
     * Mark every message in a thread as read for the current user.
     * Today this is agency-wide read state (no per-user table); good
     * enough for v1.
     */
    public function markRead(Request $request, int $threadId): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        CommunicationLog::where('agency_id', $agencyId)
            ->where('thread_id', $threadId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }

    /**
     * Resend webhook receiver. Configure in Resend dashboard to POST
     * here. Updates delivery_status + timestamps based on event type.
     * Public route, secured by a shared signing secret in the
     * X-Resend-Signature header (env: RESEND_WEBHOOK_SECRET).
     */
    public function resendWebhook(Request $request): JsonResponse
    {
        $secret = env('RESEND_WEBHOOK_SECRET');
        if ($secret) {
            $signature = $request->header('X-Resend-Signature') ?? $request->header('Svix-Signature');
            if (!$signature || !hash_equals($secret, (string) $signature)) {
                return response()->json(['success' => false], 401);
            }
        }

        $type = $request->input('type');
        $resendId = $request->input('data.email_id') ?? $request->input('data.id');
        if (!$resendId) {
            return response()->json(['success' => true, 'ignored' => 'no_id']);
        }
        $log = CommunicationLog::where('resend_id', $resendId)->first();
        if (!$log) {
            Log::info('Resend webhook for unknown id', ['id' => $resendId, 'type' => $type]);
            return response()->json(['success' => true, 'ignored' => 'no_match']);
        }

        switch ($type) {
            case 'email.delivered':
                $log->delivery_status = 'delivered';
                $log->delivered_at = now();
                break;
            case 'email.bounced':
                $log->delivery_status = 'bounced';
                $log->bounced_at = now();
                break;
            case 'email.complained':
                $log->delivery_status = 'complained';
                break;
            case 'email.opened':
                if (!$log->read_at) $log->read_at = now();
                break;
            case 'email.sent':
                if ($log->delivery_status === 'queued') $log->delivery_status = 'sent';
                break;
        }
        $log->save();

        return response()->json(['success' => true]);
    }

    /**
     * Send via Resend and update the log row with the result.
     * Errors don't throw — we don't want a Resend outage to block the
     * UI from acknowledging the operator's send. Status reflects the
     * truth: 'sent' on 2xx, 'failed' otherwise.
     */
    private function deliver(CommunicationLog $log): void
    {
        $apiKey = config('services.resend.key');
        if (!$apiKey) {
            $log->delivery_status = 'skipped_no_key';
            $log->save();
            Log::warning('Message not sent: RESEND_API_KEY missing', ['log_id' => $log->id]);
            return;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post('https://api.resend.com/emails', [
                    'from' => config('mail.from.address', 'noreply@credentik.com'),
                    'to' => [$log->recipient_email],
                    'subject' => $log->subject,
                    'html' => $log->html_body ?: nl2br(e($log->body)),
                    'reply_to' => $log->creator?->email ?? config('mail.from.address'),
                ]);
            if ($response->successful()) {
                $log->resend_id = $response->json('id');
                $log->delivery_status = 'sent';
            } else {
                $log->delivery_status = 'failed';
                Log::warning('Resend failed', ['body' => $response->body(), 'log_id' => $log->id]);
            }
        } catch (\Throwable $e) {
            $log->delivery_status = 'failed';
            Log::error('Resend exception', ['error' => $e->getMessage(), 'log_id' => $log->id]);
        }
        $log->save();
    }

    /**
     * Render the operator's plain-text body into branded email HTML.
     * Kept simple intentionally — a div with the agency name in the
     * header, the body with line breaks preserved, and a footer with
     * reply instructions. No Markdown parsing in v1: most operator
     * messages are short prose, not formatted documents.
     */
    private function renderEmailHtml(string $subject, string $body, $user): string
    {
        $agency = $user->agency;
        $agencyName = e($agency?->name ?? 'Credentik');
        $senderName = e(trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->email ?? ''));
        $bodyHtml = nl2br(e($body));
        $subjectHtml = e($subject);

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{$subjectHtml}</title></head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#0f172a;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
    <div style="padding:14px 24px;border-bottom:1px solid #e2e8f0;background:#4f46e5;color:#fff;">
      <div style="font-size:13px;font-weight:600;letter-spacing:0.02em;">{$agencyName}</div>
    </div>
    <div style="padding:24px;font-size:14px;line-height:1.6;">
      {$bodyHtml}
    </div>
    <div style="padding:14px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;font-size:12px;color:#64748b;">
      Sent by {$senderName} via {$agencyName}. Reply to this email and we'll see it.
    </div>
  </div>
</body></html>
HTML;
    }
}
