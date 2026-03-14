<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Application;
use App\Models\Followup;
use App\Models\Provider;

class EmailGeneratorService
{
    /**
     * Generate a followup email for an application.
     *
     * @return array{subject: string, body: string}
     */
    public function generateFollowupEmail(Application $app, Followup $followup): array
    {
        $app->loadMissing(['provider', 'payer', 'organization']);

        $providerName = $app->provider?->full_name ?? 'the provider';
        $payerName    = $app->payer?->name ?? $app->payer_name ?? 'the payer';
        $orgName      = $app->organization?->name ?? 'our organization';
        $appRef       = $app->application_ref ? " (Ref: {$app->application_ref})" : '';
        $state        = $app->state ?? '';

        $typeLabels = [
            'status_check'        => 'Status Check',
            'document_request'    => 'Document Request',
            'document_collection' => 'Document Collection',
            'info_response'       => 'Information Response',
            'escalation'          => 'Escalation',
            'renewal_check'       => 'Renewal Check',
            'general'             => 'Follow-Up',
        ];
        $typeLabel = $typeLabels[$followup->type] ?? 'Follow-Up';

        $subject = "{$typeLabel}: {$providerName} - {$payerName}{$appRef}";

        $body = <<<EOT
Dear {$payerName} Credentialing Department,

I am writing to follow up on the credentialing application for the following provider:

Provider: {$providerName}
NPI: {$app->provider?->npi}
Organization: {$orgName}
State: {$state}
Application Reference: {$app->application_ref}
Submitted Date: {$this->formatDate($app->submitted_date)}

This follow-up is regarding: {$typeLabel}

Current Application Status: {$this->statusLabel($app->status)}

Could you please provide an update on the status of this application? If any additional documentation or information is needed, please let us know so we can provide it promptly.

Thank you for your time and assistance.

Best regards,
{$orgName}
EOT;

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Generate a status-update notification email.
     *
     * @return array{subject: string, body: string}
     */
    public function generateStatusUpdateEmail(Application $app, string $oldStatus, string $newStatus): array
    {
        $app->loadMissing(['provider', 'payer', 'organization']);

        $providerName = $app->provider?->full_name ?? 'the provider';
        $payerName    = $app->payer?->name ?? $app->payer_name ?? 'the payer';
        $orgName      = $app->organization?->name ?? 'our organization';

        $oldLabel = $this->statusLabel($oldStatus);
        $newLabel = $this->statusLabel($newStatus);

        $subject = "Application Update: {$providerName} - {$payerName} [{$newLabel}]";

        $nextSteps = $this->nextStepsForStatus($newStatus, $providerName, $payerName);

        $body = <<<EOT
Credentialing Application Status Update

Provider: {$providerName}
Payer: {$payerName}
Organization: {$orgName}
State: {$app->state}
Application Reference: {$app->application_ref}

Status Change: {$oldLabel} -> {$newLabel}
Date: {$this->formatDate(now())}

{$nextSteps}

If you have any questions regarding this update, please contact the credentialing team.

Best regards,
{$orgName} Credentialing Team
EOT;

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Generate the initial submission confirmation email.
     *
     * @return array{subject: string, body: string}
     */
    public function generateInitialSubmissionEmail(Application $app): array
    {
        $app->loadMissing(['provider', 'payer', 'organization']);

        $providerName = $app->provider?->full_name ?? 'the provider';
        $payerName    = $app->payer?->name ?? $app->payer_name ?? 'the payer';
        $orgName      = $app->organization?->name ?? 'our organization';
        $npi          = $app->provider?->npi ?? 'N/A';
        $taxonomy     = $app->provider?->taxonomy ?? 'N/A';
        $avgDays      = $app->payer?->avg_cred_days;

        $subject = "Credentialing Application Submitted: {$providerName} - {$payerName}";

        $timelineNote = $avgDays
            ? "Based on historical data, the average processing time for {$payerName} is approximately {$avgDays} days."
            : "Processing times vary by payer. We will follow up regularly to track progress.";

        $body = <<<EOT
Dear {$payerName} Credentialing Department,

Please accept this notification that a credentialing application has been submitted for the following provider:

Provider: {$providerName}
NPI: {$npi}
Taxonomy: {$taxonomy}
Organization: {$orgName}
State: {$app->state}
Application Type: {$app->type}

{$timelineNote}

If you require any additional documentation or have questions, please do not hesitate to reach out.

Thank you for processing this application.

Best regards,
{$orgName}
EOT;

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Generate a welcome email for a newly onboarded provider.
     *
     * @return array{subject: string, body: string}
     */
    public function generateProviderWelcomeEmail(Provider $provider, Agency $agency): array
    {
        $providerName = $provider->full_name;

        $subject = "Welcome to {$agency->name}, {$provider->first_name}!";

        $body = <<<EOT
Dear {$providerName},

Welcome to {$agency->name}! We are excited to have you on board.

Your provider profile has been created and our credentialing team will begin the payer enrollment process on your behalf. Here is a summary of your information on file:

Name: {$providerName}
NPI: {$provider->npi}
Specialty: {$provider->specialty}
Email: {$provider->email}

What happens next:
1. Our team will review your credentials and supporting documentation.
2. We will submit credentialing applications to the appropriate insurance payers.
3. You will receive status updates as applications progress through review.
4. Once approved, you will be notified with your enrollment details for each payer.

If you have any questions or need to update your information, please contact us at {$agency->email} or {$agency->phone}.

We look forward to working with you!

Best regards,
The {$agency->name} Credentialing Team
{$agency->email}
{$agency->phone}
EOT;

        return ['subject' => $subject, 'body' => $body];
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Convert a status slug into a human-readable label.
     */
    private function statusLabel(string $status): string
    {
        $labels = [
            'not_started'  => 'Not Started',
            'submitted'    => 'Submitted',
            'in_review'    => 'In Review',
            'pending_info' => 'Pending Info',
            'approved'     => 'Approved',
            'denied'       => 'Denied',
            'withdrawn'    => 'Withdrawn',
        ];

        return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    /**
     * Generate contextual next-steps text based on the new status.
     */
    private function nextStepsForStatus(string $status, string $providerName, string $payerName): string
    {
        return match ($status) {
            'submitted'    => "Next Steps:\nThe application for {$providerName} has been submitted to {$payerName}. Our team will follow up within 14 business days to check on the application status.",
            'in_review'    => "Next Steps:\nThe application is now under review by {$payerName}. No action is required at this time. We will continue to monitor progress and provide updates.",
            'pending_info' => "Action Required:\n{$payerName} has requested additional information for {$providerName}'s application. Please review the request and provide the necessary documentation as soon as possible to avoid delays.",
            'approved'     => "Congratulations!\nThe credentialing application for {$providerName} with {$payerName} has been approved. Our team will collect the enrollment details and update your records.",
            'denied'       => "Application Denied:\nUnfortunately, the application for {$providerName} with {$payerName} has been denied. Our team will review the denial reason and determine next steps, which may include resubmission.",
            'withdrawn'    => "Application Withdrawn:\nThe credentialing application for {$providerName} with {$payerName} has been withdrawn. If you have questions about this decision, please contact the credentialing team.",
            default        => "The application status has been updated. Please contact the credentialing team if you have any questions.",
        };
    }

    /**
     * Safely format a date value, returning 'N/A' for null.
     */
    private function formatDate(mixed $date): string
    {
        if (!$date) {
            return 'N/A';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return (string) $date;
    }
}
