<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationLogEntry;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationSendController extends Controller
{
    /**
     * Send an email notification via Resend.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'           => 'required|string|in:status_change,expiration_warning,document_needed,welcome,milestone,followup_created',
            'recipientEmail' => 'required|email',
            'recipientName'  => 'nullable|string|max:255',
            'subject'        => 'required|string|max:500',
            'body'           => 'required|string',
            'providerId'     => 'nullable|integer',
            'metadata'       => 'nullable|array',
        ]);

        $resendId = null;
        $status = 'sent';

        try {
            $apiKey = config('services.resend.key');
            if ($apiKey) {
                $response = Http::withToken($apiKey)->post('https://api.resend.com/emails', [
                    'from'    => config('mail.from.address', 'noreply@credentik.com'),
                    'to'      => [$data['recipientEmail']],
                    'subject' => $data['subject'],
                    'html'    => $data['body'],
                ]);

                if ($response->successful()) {
                    $resendId = $response->json('id');
                    $status = 'delivered';
                } else {
                    $status = 'failed';
                    Log::warning('Resend email failed', ['response' => $response->body()]);
                }
            } else {
                $status = 'skipped';
                Log::info('Resend API key not configured, skipping email send');
            }
        } catch (\Exception $e) {
            $status = 'failed';
            Log::error('Email send error', ['error' => $e->getMessage()]);
        }

        $logEntry = NotificationLogEntry::create([
            'agency_id'       => $request->user()->agency_id,
            'type'            => $data['type'],
            'recipient_email' => $data['recipientEmail'],
            'recipient_name'  => $data['recipientName'] ?? null,
            'subject'         => $data['subject'],
            'body'            => $data['body'],
            'status'          => $status,
            'resend_id'       => $resendId,
            'metadata'        => $data['metadata'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $logEntry], 201);
    }

    /**
     * List notification log.
     */
    public function index(Request $request): JsonResponse
    {
        $log = NotificationLogEntry::orderByDesc('created_at')
            ->limit($request->integer('limit', 100))
            ->get();

        return response()->json(['success' => true, 'data' => $log]);
    }

    /**
     * Get notification preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        $prefs = NotificationPreference::where('agency_id', $request->user()->agency_id)->first();

        if (!$prefs) {
            $prefs = NotificationPreference::create([
                'agency_id'              => $request->user()->agency_id,
                'status_changes'         => true,
                'license_expiration'     => true,
                'license_expiration_days' => 30,
                'document_requests'      => true,
                'weekly_summary'         => false,
            ]);
        }

        return response()->json(['success' => true, 'data' => $prefs]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_recipient_email' => 'nullable|email',
            'status_changes'          => 'sometimes|boolean',
            'license_expiration'      => 'sometimes|boolean',
            'license_expiration_days' => 'sometimes|integer|in:30,60,90',
            'document_requests'       => 'sometimes|boolean',
            'weekly_summary'          => 'sometimes|boolean',
        ]);

        $prefs = NotificationPreference::updateOrCreate(
            ['agency_id' => $request->user()->agency_id],
            $data
        );

        return response()->json(['success' => true, 'data' => $prefs]);
    }

    /**
     * Send a test email.
     */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipientEmail' => 'required|email',
        ]);

        $apiKey = config('services.resend.key');
        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'Resend API key not configured'], 422);
        }

        try {
            $response = Http::withToken($apiKey)->post('https://api.resend.com/emails', [
                'from'    => config('mail.from.address', 'noreply@credentik.com'),
                'to'      => [$data['recipientEmail']],
                'subject' => 'Credentik Test Notification',
                'html'    => '<h2>Test Notification</h2><p>This is a test email from Credentik. If you received this, your email notifications are configured correctly.</p><p style="color:#6b7280;font-size:12px;">Sent at ' . now()->toDateTimeString() . '</p>',
            ]);

            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'Test email sent successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Email send failed: ' . $response->body()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
