<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Application;
use App\Models\Provider;
use App\Models\ProviderDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', '');
        $this->model = config('services.anthropic.model', 'claude-sonnet-4-20250514');
    }

    /**
     * Extract structured data from an uploaded document using Claude Vision.
     */
    public function extractDocument(ProviderDocument $doc): array
    {
        if (!$doc->file_path) {
            return ['error' => 'No file attached to this document'];
        }

        $disk = Storage::disk($doc->file_disk ?? 's3');
        if (!$disk->exists($doc->file_path)) {
            return ['error' => 'File not found in storage'];
        }

        $contents = $disk->get($doc->file_path);
        $mimeType = $doc->mime_type ?? 'application/pdf';
        $base64 = base64_encode($contents);

        // For PDFs, we can only use document type; for images, use image type
        $isImage = str_starts_with($mimeType, 'image/');

        $contentBlock = $isImage
            ? ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64]]
            : ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]];

        $prompt = <<<PROMPT
Extract all structured data from this {$doc->document_type} document. Return a JSON object with these fields (use null for any field not found):

{
  "document_type": "the type of document (license, certificate, diploma, insurance policy, etc.)",
  "issuing_authority": "who issued this document",
  "holder_name": "full name of the person this document belongs to",
  "license_number": "license or certificate number if present",
  "state": "US state code (2 letter) if applicable",
  "issue_date": "YYYY-MM-DD format",
  "expiration_date": "YYYY-MM-DD format",
  "status": "active, expired, etc. if stated",
  "specialty": "medical specialty if mentioned",
  "npi": "NPI number if present",
  "dea_number": "DEA number if present",
  "additional_info": "any other important details as key-value pairs"
}

Return ONLY the JSON object, no other text.
PROMPT;

        return $this->callClaude([
            ['role' => 'user', 'content' => [$contentBlock, ['type' => 'text', 'text' => $prompt]]],
        ], 1024);
    }

    /**
     * Generate an AI-powered email draft for a payer follow-up.
     */
    public function draftEmail(Application $app, string $emailType, ?string $additionalContext = null): array
    {
        $app->loadMissing(['provider', 'payer', 'organization']);

        $provider = $app->provider;
        $payer = $app->payer;
        $org = $app->organization;
        $agency = Agency::find($app->agency_id);

        $followupCount = $app->followups()->count();
        $daysSinceSubmit = $app->submitted_date ? now()->diffInDays($app->submitted_date) : null;
        $lastFollowup = $app->followups()->orderByDesc('due_date')->first();

        $context = <<<CTX
Application Context:
- Provider: {$provider?->full_name} (NPI: {$provider?->npi}, {$provider?->specialty})
- Payer: {$payer?->name}
- Organization: {$org?->name}
- Agency: {$agency?->name}
- State: {$app->state}
- Status: {$app->status}
- Application Ref: {$app->application_ref}
- Submitted: {$app->submitted_date?->toDateString()} ({$daysSinceSubmit} days ago)
- Follow-ups done: {$followupCount}
- Last follow-up: {$lastFollowup?->due_date?->toDateString()} - outcome: {$lastFollowup?->outcome}
- Estimated monthly revenue: \${$app->est_monthly_revenue}
- Notes: {$app->notes}
CTX;

        if ($additionalContext) {
            $context .= "\n\nAdditional context from user: {$additionalContext}";
        }

        $prompt = <<<PROMPT
You are a healthcare credentialing specialist writing a professional email. Generate a {$emailType} email based on this application context.

{$context}

Email types:
- "followup": Polite but firm status check to the payer
- "escalation": Firm escalation when the application has been delayed too long
- "document_request": Request specific documents or information from the payer
- "initial_submission": Confirmation of application submission
- "provider_update": Update to the provider about their application status

Return a JSON object with exactly these fields:
{
  "subject": "email subject line",
  "body": "full email body text",
  "tone": "professional/urgent/friendly",
  "suggested_followup_days": number of days to follow up after sending
}

Return ONLY the JSON object, no other text.
PROMPT;

        return $this->callClaude([
            ['role' => 'user', 'content' => $prompt],
        ], 2048);
    }

    /**
     * Detect anomalies in a provider's profile (gaps, missing items, expired credentials).
     */
    public function detectAnomalies(Provider $provider, int $agencyId): array
    {
        $provider->loadMissing(['licenses', 'applications']);

        // Gather all provider data
        $licenses = $provider->licenses()->get();
        $education = \App\Models\ProviderEducation::where('provider_id', $provider->id)->where('agency_id', $agencyId)->get();
        $workHistory = \App\Models\WorkHistory::where('provider_id', $provider->id)->where('agency_id', $agencyId)->get();
        $malpractice = \App\Models\MalpracticePolicy::where('provider_id', $provider->id)->where('agency_id', $agencyId)->get();
        $boards = \App\Models\BoardCertification::where('provider_id', $provider->id)->where('agency_id', $agencyId)->get();
        $documents = ProviderDocument::where('provider_id', $provider->id)->where('agency_id', $agencyId)->get();
        $deas = \App\Models\DeaRegistration::withoutGlobalScopes()->where('provider_id', $provider->id)->where('agency_id', $agencyId)->get();

        $profileData = <<<DATA
Provider Profile for Anomaly Detection:

Name: {$provider->full_name}
NPI: {$provider->npi}
Specialty: {$provider->specialty}
Credentials: {$provider->credentials}
Email: {$provider->email}
Is Active: {$provider->is_active}

Licenses ({$licenses->count()}):
{$licenses->map(fn($l) => "- {$l->state} {$l->license_type} #{$l->license_number} | Status: {$l->status} | Expires: {$l->expiration_date?->toDateString()}")->implode("\n")}

Education ({$education->count()}):
{$education->map(fn($e) => "- {$e->degree} from {$e->institution} ({$e->graduation_year})")->implode("\n")}

Work History ({$workHistory->count()}):
{$workHistory->map(fn($w) => "- {$w->employer}: {$w->start_date?->toDateString()} to {$w->end_date?->toDateString() ?? 'Present'}")->implode("\n")}

Board Certifications ({$boards->count()}):
{$boards->map(fn($b) => "- {$b->board_name} | Expires: {$b->expiration_date?->toDateString()} | Lifetime: " . ($b->is_lifetime ? 'Yes' : 'No'))->implode("\n")}

Malpractice Insurance ({$malpractice->count()}):
{$malpractice->map(fn($m) => "- {$m->carrier}: {$m->policy_start?->toDateString()} to {$m->policy_end?->toDateString()}")->implode("\n")}

DEA Registrations ({$deas->count()}):
{$deas->map(fn($d) => "- {$d->dea_number} | State: {$d->state} | Status: {$d->status} | Expires: {$d->expiration_date?->toDateString()}")->implode("\n")}

Documents on File ({$documents->count()}):
{$documents->map(fn($d) => "- {$d->document_type}: {$d->document_name} | Status: {$d->status} | Expires: {$d->expiration_date?->toDateString()}")->implode("\n")}
DATA;

        $prompt = <<<PROMPT
You are a healthcare credentialing compliance expert. Analyze this provider's profile and identify ALL anomalies, gaps, and compliance risks.

{$profileData}

Check for:
1. **Expired credentials** — licenses, boards, malpractice, DEA that are past expiration
2. **Missing critical items** — active provider should have: at least 1 license, malpractice insurance, education records, work history
3. **Employment gaps** — periods > 30 days between work history entries
4. **License gaps** — practicing in states without an active license
5. **Malpractice coverage gaps** — periods without active coverage
6. **Missing documents** — common documents that should be on file (CV, diplomas, license copies, malpractice face sheet)
7. **Data inconsistencies** — mismatched names, credentials that don't match specialty, etc.
8. **Upcoming expirations** — items expiring within 90 days

Return a JSON object:
{
  "risk_level": "low|medium|high|critical",
  "anomalies": [
    {
      "severity": "critical|high|medium|low",
      "category": "expired|missing|gap|inconsistency|expiring_soon",
      "item": "what the issue is",
      "detail": "specific description of the problem",
      "recommendation": "what to do about it"
    }
  ],
  "summary": "one paragraph summary of overall compliance status",
  "score": 0-100 (100 = fully compliant, 0 = critical issues)
}

Return ONLY the JSON object, no other text.
PROMPT;

        return $this->callClaude([
            ['role' => 'user', 'content' => $prompt],
        ], 4096);
    }

    /**
     * Predict credentialing timeline for an application based on historical data.
     */
    public function predictTimeline(Application $app, int $agencyId): array
    {
        $app->loadMissing(['provider', 'payer']);

        // Get historical applications for this payer
        $historicalApps = Application::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->where('payer_id', $app->payer_id)
            ->whereNotNull('submitted_date')
            ->whereIn('status', ['approved', 'denied'])
            ->select('status', 'submitted_date', 'effective_date', 'state', 'type', 'denial_reason')
            ->orderByDesc('submitted_date')
            ->limit(50)
            ->get();

        $stats = [];
        foreach ($historicalApps as $ha) {
            if ($ha->submitted_date && $ha->effective_date) {
                $days = $ha->submitted_date->diffInDays($ha->effective_date);
                $stats[] = [
                    'days' => $days,
                    'status' => $ha->status,
                    'state' => $ha->state,
                    'type' => $ha->type,
                    'denial_reason' => $ha->denial_reason,
                ];
            }
        }

        $avgDays = $app->payer?->avg_cred_days;
        $currentDays = $app->submitted_date ? now()->diffInDays($app->submitted_date) : 0;

        $context = <<<CTX
Predict the credentialing timeline for this application:

Current Application:
- Provider: {$app->provider?->full_name} ({$app->provider?->specialty})
- Payer: {$app->payer?->name}
- State: {$app->state}
- Type: {$app->type}
- Status: {$app->status}
- Submitted: {$app->submitted_date?->toDateString()} ({$currentDays} days ago)
- Payer avg credentialing days: {$avgDays}

Historical Applications for this Payer ({$historicalApps->count()} records):
CTX;

        if (count($stats) > 0) {
            $context .= "\n" . json_encode($stats, JSON_PRETTY_PRINT);
        } else {
            $context .= "\nNo historical data available for this payer.";
        }

        $prompt = <<<PROMPT
You are a healthcare credentialing timeline analyst. Based on the application data and historical patterns, predict the credentialing timeline.

{$context}

Return a JSON object:
{
  "estimated_days_total": number (total days from submission to approval),
  "estimated_days_remaining": number (days remaining from now),
  "estimated_completion_date": "YYYY-MM-DD",
  "confidence": "low|medium|high",
  "approval_probability": 0-100 (percentage chance of approval),
  "risk_factors": ["list of factors that could delay this application"],
  "recommendations": ["actionable suggestions to speed up the process"],
  "reasoning": "brief explanation of the prediction"
}

Return ONLY the JSON object, no other text.
PROMPT;

        return $this->callClaude([
            ['role' => 'user', 'content' => $prompt],
        ], 2048);
    }

    /**
     * Call Claude API via HTTP.
     */
    private function callClaude(array $messages, int $maxTokens = 2048): array
    {
        if (!$this->apiKey) {
            return ['error' => 'Anthropic API key not configured. Set ANTHROPIC_API_KEY in environment.'];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'messages' => $messages,
                ]);

            if (!$response->successful()) {
                Log::warning('Claude API error', ['status' => $response->status(), 'body' => $response->body()]);
                return ['error' => 'AI service error: ' . ($response->json('error.message') ?? $response->body())];
            }

            $text = $response->json('content.0.text', '');

            // Try to parse as JSON
            $json = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return ['success' => true, 'data' => $json];
            }

            // If not valid JSON, try to extract JSON from the text
            if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                $json = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return ['success' => true, 'data' => $json];
                }
            }

            return ['success' => true, 'data' => ['raw_text' => $text]];
        } catch (\Exception $e) {
            Log::error('Claude API exception', ['error' => $e->getMessage()]);
            return ['error' => 'AI service unavailable: ' . $e->getMessage()];
        }
    }
}
