<?php

namespace App\Services;

use App\Models\Application;
use App\Models\BoardCertification;
use App\Models\License;
use App\Models\MalpracticePolicy;
use App\Models\Provider;
use App\Models\ProviderCme;
use App\Models\ProviderDocument;
use App\Models\ProviderEducation;
use App\Models\ProviderReference;
use App\Models\ProviderWorkHistory;
use Barryvdh\DomPDF\Facade\Pdf;

class CredentialingPacketService
{
    public static function generate(int $agencyId, int $providerId): \Barryvdh\DomPDF\PDF
    {
        $provider = Provider::where('agency_id', $agencyId)
            ->with(['organization:id,name,npi,tax_id', 'licenses'])
            ->findOrFail($providerId);

        $agency = \App\Models\Agency::findOrFail($agencyId);

        $data = [
            'agency' => $agency,
            'provider' => $provider,
            'licenses' => License::where('agency_id', $agencyId)->where('provider_id', $providerId)->orderBy('state')->get(),
            'education' => ProviderEducation::where('agency_id', $agencyId)->where('provider_id', $providerId)->orderByDesc('graduation_date')->get(),
            'boards' => BoardCertification::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
            'malpractice' => MalpracticePolicy::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
            'work_history' => ProviderWorkHistory::where('agency_id', $agencyId)->where('provider_id', $providerId)->orderByDesc('start_date')->get(),
            'cme' => ProviderCme::where('agency_id', $agencyId)->where('provider_id', $providerId)->orderByDesc('completion_date')->get(),
            'references' => ProviderReference::where('agency_id', $agencyId)->where('provider_id', $providerId)->get(),
            'documents' => ProviderDocument::where('agency_id', $agencyId)->where('provider_id', $providerId)->orderBy('document_type')->get(),
            'applications' => Application::where('agency_id', $agencyId)->where('provider_id', $providerId)->with('payer:id,name')->get(),
            'generated_at' => now(),
        ];

        $html = self::renderHtml($data);

        return Pdf::loadHTML($html)
            ->setPaper('letter')
            ->setOption('isPhpEnabled', true)
            ->setOption('defaultFont', 'Helvetica');
    }

    private static function renderHtml(array $data): string
    {
        $p = $data['provider'];
        $agency = $data['agency'];
        $name = trim($p->first_name . ' ' . $p->last_name);
        $fullName = $p->credentials ? "$name, {$p->credentials}" : $name;
        $date = $data['generated_at']->format('F j, Y');
        $primaryColor = $agency->primary_color ?? '#2C4A5A';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; }
    .header { background: {$primaryColor}; color: #fff; padding: 24px 32px; margin-bottom: 24px; }
    .header h1 { font-size: 20px; margin-bottom: 4px; }
    .header .subtitle { font-size: 12px; opacity: 0.85; }
    .header .agency { font-size: 10px; opacity: 0.7; margin-top: 8px; }
    .section { margin: 0 32px 20px; }
    .section-title { font-size: 13px; font-weight: bold; color: {$primaryColor}; border-bottom: 2px solid {$primaryColor}; padding-bottom: 4px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 10px; font-weight: 600; color: #374151; border: 1px solid #e5e7eb; }
    td { padding: 5px 8px; font-size: 10px; border: 1px solid #e5e7eb; }
    .info-grid { display: table; width: 100%; margin-bottom: 12px; }
    .info-row { display: table-row; }
    .info-label { display: table-cell; width: 140px; font-weight: 600; color: #374151; padding: 3px 0; font-size: 10px; }
    .info-value { display: table-cell; padding: 3px 0; font-size: 10px; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 9px; font-weight: 600; }
    .badge-active { background: #dcfce7; color: #166534; }
    .badge-expired { background: #fee2e2; color: #991b1b; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .footer { text-align: center; font-size: 9px; color: #9ca3af; margin-top: 24px; padding: 12px; border-top: 1px solid #e5e7eb; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

<div class="header">
    <h1>Credentialing Packet</h1>
    <div class="subtitle">{$fullName} &bull; NPI: {$p->npi}</div>
    <div class="agency">Prepared by {$agency->name} &bull; Generated {$date}</div>
</div>

<div class="section">
    <div class="section-title">Provider Information</div>
    <div class="info-grid">
        <div class="info-row"><div class="info-label">Full Name</div><div class="info-value">{$fullName}</div></div>
        <div class="info-row"><div class="info-label">NPI</div><div class="info-value">{$p->npi}</div></div>
        <div class="info-row"><div class="info-label">Taxonomy</div><div class="info-value">{$p->taxonomy}</div></div>
        <div class="info-row"><div class="info-label">Specialty</div><div class="info-value">{$p->specialty}</div></div>
        <div class="info-row"><div class="info-label">Email</div><div class="info-value">{$p->email}</div></div>
        <div class="info-row"><div class="info-label">Phone</div><div class="info-value">{$p->phone}</div></div>
        <div class="info-row"><div class="info-label">CAQH ID</div><div class="info-value">{$p->caqh_id}</div></div>
HTML;

        if ($p->organization) {
            $org = $p->organization;
            $html .= <<<HTML
        <div class="info-row"><div class="info-label">Organization</div><div class="info-value">{$org->name} (NPI: {$org->npi})</div></div>
HTML;
        }

        $html .= '</div></div>';

        // Licenses
        if ($data['licenses']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Licenses</div><table><tr><th>State</th><th>License #</th><th>Type</th><th>Status</th><th>Issue Date</th><th>Expiration</th></tr>';
            foreach ($data['licenses'] as $l) {
                $status = self::badge($l->status ?? 'active');
                $issue = $l->issue_date?->format('m/d/Y') ?? '—';
                $exp = $l->expiration_date?->format('m/d/Y') ?? '—';
                $html .= "<tr><td>{$l->state}</td><td>{$l->license_number}</td><td>{$l->license_type}</td><td>{$status}</td><td>{$issue}</td><td>{$exp}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // Education
        if ($data['education']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Education</div><table><tr><th>Institution</th><th>Degree</th><th>Field</th><th>Graduation</th></tr>';
            foreach ($data['education'] as $e) {
                $grad = $e->graduation_date?->format('m/d/Y') ?? '—';
                $html .= "<tr><td>{$e->institution_name}</td><td>{$e->degree}</td><td>{$e->field_of_study}</td><td>{$grad}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // Board Certifications
        if ($data['boards']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Board Certifications</div><table><tr><th>Board</th><th>Specialty</th><th>Cert #</th><th>Status</th><th>Expiration</th></tr>';
            foreach ($data['boards'] as $b) {
                $status = $b->is_lifetime ? '<span class="badge badge-active">Lifetime</span>' : self::badge($b->status ?? 'active');
                $exp = $b->is_lifetime ? 'Lifetime' : ($b->expiration_date?->format('m/d/Y') ?? '—');
                $html .= "<tr><td>{$b->board_name}</td><td>{$b->specialty}</td><td>{$b->certificate_number}</td><td>{$status}</td><td>{$exp}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // Malpractice
        if ($data['malpractice']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Malpractice Insurance</div><table><tr><th>Carrier</th><th>Policy #</th><th>Coverage</th><th>Effective</th><th>Expiration</th></tr>';
            foreach ($data['malpractice'] as $m) {
                $eff = $m->effective_date?->format('m/d/Y') ?? '—';
                $exp = $m->expiration_date?->format('m/d/Y') ?? '—';
                $coverage = $m->per_incident_amount ? ('$' . number_format($m->per_incident_amount) . '/$' . number_format($m->aggregate_amount)) : '—';
                $html .= "<tr><td>{$m->carrier_name}</td><td>{$m->policy_number}</td><td>{$coverage}</td><td>{$eff}</td><td>{$exp}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // Work History
        if ($data['work_history']->isNotEmpty()) {
            $html .= '<div class="section page-break"><div class="section-title">Work History</div><table><tr><th>Employer</th><th>Position</th><th>Start</th><th>End</th><th>City/State</th></tr>';
            foreach ($data['work_history'] as $w) {
                $start = $w->start_date?->format('m/d/Y') ?? '—';
                $end = $w->is_current ? 'Present' : ($w->end_date?->format('m/d/Y') ?? '—');
                $loc = trim(($w->city ?? '') . ', ' . ($w->state ?? ''), ', ');
                $html .= "<tr><td>{$w->employer_name}</td><td>{$w->position_title}</td><td>{$start}</td><td>{$end}</td><td>{$loc}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // CME
        if ($data['cme']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Continuing Education</div><table><tr><th>Course</th><th>Provider</th><th>Hours</th><th>Completed</th><th>Expires</th></tr>';
            foreach ($data['cme'] as $c) {
                $comp = $c->completion_date?->format('m/d/Y') ?? '—';
                $exp = $c->expiration_date?->format('m/d/Y') ?? '—';
                $html .= "<tr><td>{$c->course_name}</td><td>{$c->provider_org}</td><td>{$c->credit_hours}</td><td>{$comp}</td><td>{$exp}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // References
        if ($data['references']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Professional References</div><table><tr><th>Name</th><th>Title</th><th>Organization</th><th>Phone</th><th>Email</th></tr>';
            foreach ($data['references'] as $r) {
                $html .= "<tr><td>{$r->reference_name}</td><td>{$r->reference_title}</td><td>{$r->reference_organization}</td><td>{$r->phone}</td><td>{$r->email}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // Document Checklist
        if ($data['documents']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Document Checklist</div><table><tr><th>Type</th><th>Name</th><th>Status</th><th>Received</th><th>Expires</th><th>File</th></tr>';
            foreach ($data['documents'] as $d) {
                $status = self::badge($d->status);
                $recv = $d->received_date?->format('m/d/Y') ?? '—';
                $exp = $d->expiration_date?->format('m/d/Y') ?? '—';
                $hasFile = $d->file_path ? 'Yes' : 'No';
                $html .= "<tr><td>{$d->document_type}</td><td>{$d->document_name}</td><td>{$status}</td><td>{$recv}</td><td>{$exp}</td><td>{$hasFile}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // Applications Summary
        if ($data['applications']->isNotEmpty()) {
            $html .= '<div class="section"><div class="section-title">Payer Applications</div><table><tr><th>Payer</th><th>State</th><th>Type</th><th>Status</th><th>Submitted</th></tr>';
            foreach ($data['applications'] as $a) {
                $status = self::badge($a->status);
                $submitted = $a->submitted_date?->format('m/d/Y') ?? '—';
                $payerName = $a->payer?->name ?? '—';
                $html .= "<tr><td>{$payerName}</td><td>{$a->state}</td><td>{$a->type}</td><td>{$status}</td><td>{$submitted}</td></tr>";
            }
            $html .= '</table></div>';
        }

        $html .= <<<HTML
<div class="footer">
    This credentialing packet was generated by {$agency->name} using Credentik on {$date}.<br>
    This document contains confidential information and is intended for credentialing purposes only.
</div>
</body>
</html>
HTML;

        return $html;
    }

    private static function badge(string $status): string
    {
        $class = match (strtolower($status)) {
            'active', 'received', 'verified', 'approved', 'enrolled' => 'badge-active',
            'expired', 'terminated', 'denied', 'missing' => 'badge-expired',
            default => 'badge-pending',
        };
        return "<span class=\"badge {$class}\">" . ucfirst($status) . "</span>";
    }
}
