{{-- PDF-formatted appeal letter. Rendered by DomPDF in
     RcmController::denialPdf(). Letterhead branding pulls from
     BrandingResolver; body content is letter_text (already rendered
     by AppealLetterService and possibly edited by the operator).

     Layout constraints:
       - DomPDF supports a CSS subset (no flex, limited grid, no JS).
       - Inline styles work best for predictable rendering.
       - 8.5x11 page; ~1 inch margins.
       - Logo URL must be remote HTTPS or an absolute file path; the
         template renders text-only when no logo is set.
--}}<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Appeal Letter — Claim {{ $claim_number }}</title>
    <style>
        @page { margin: 0.75in 0.75in 1in 0.75in; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1f2937;
            margin: 0;
        }
        .letterhead {
            border-bottom: 2pt solid {{ $brand['primary_color'] ?? '#4f46e5' }};
            padding-bottom: 16pt;
            margin-bottom: 24pt;
        }
        .letterhead-name {
            font-size: 18pt;
            font-weight: bold;
            color: {{ $brand['primary_color'] ?? '#4f46e5' }};
            letter-spacing: -0.5pt;
        }
        .letterhead-meta {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 4pt;
            line-height: 1.4;
        }
        .meta-block {
            background: #f9fafb;
            border-left: 3pt solid {{ $brand['primary_color'] ?? '#4f46e5' }};
            padding: 10pt 14pt;
            margin: 18pt 0;
            font-size: 10pt;
            line-height: 1.6;
        }
        .meta-block strong { color: #374151; }
        .meta-row { display: block; margin-bottom: 2pt; }
        h1.subject {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            margin: 0 0 12pt 0;
            color: #111827;
        }
        p { margin: 0 0 10pt 0; text-align: justify; }
        .body-text { white-space: pre-line; }
        .signature {
            margin-top: 36pt;
        }
        .signature-line {
            display: inline-block;
            border-bottom: 1pt solid #9ca3af;
            width: 240pt;
            height: 24pt;
        }
        .signature-block {
            margin-top: 8pt;
            font-size: 10pt;
            line-height: 1.5;
        }
        .footer {
            position: fixed;
            bottom: 0.4in;
            left: 0.75in;
            right: 0.75in;
            border-top: 1pt solid #e5e7eb;
            padding-top: 6pt;
            font-size: 8pt;
            color: #9ca3af;
            text-align: center;
        }
        .enclosures {
            margin-top: 24pt;
            padding-top: 12pt;
            border-top: 1pt dashed #d1d5db;
            font-size: 10pt;
            color: #4b5563;
        }
        .enclosures strong { color: #1f2937; }
    </style>
</head>
<body>
    {{-- Letterhead --}}
    <div class="letterhead">
        <div class="letterhead-name">{{ $brand['name'] ?? 'Provider' }}</div>
        <div class="letterhead-meta">
            @if(!empty($brand['address_street'])){{ $brand['address_street'] }}<br>@endif
            @php
                $addrLine2 = trim(implode(', ', array_filter([
                    $brand['address_city'] ?? null,
                    trim(($brand['address_state'] ?? '') . ' ' . ($brand['address_zip'] ?? ''))
                ])));
            @endphp
            @if($addrLine2){{ $addrLine2 }}<br>@endif
            @if(!empty($brand['phone']))Phone: {{ $brand['phone'] }}@endif
            @if(!empty($brand['email']))@if(!empty($brand['phone'])) &nbsp;·&nbsp; @endif Email: {{ $brand['email'] }}@endif
        </div>
    </div>

    {{-- Date --}}
    <p>{{ $today }}</p>

    {{-- Payer block --}}
    <p>
        {{ $payer_name }}<br>
        [Payer Appeals Address]<br>
        [City, State ZIP]
    </p>

    {{-- Subject line --}}
    @php
        $level = (int) ($appeal_level ?: 1);
        $levelLabel = $level === 1 ? 'Appeal' : ($level === 2 ? 'Second-Level Appeal' : 'Third-Level Appeal');
    @endphp
    <h1 class="subject">RE: {{ $levelLabel }} of Denied Claim</h1>

    {{-- Claim reference block --}}
    <div class="meta-block">
        <span class="meta-row"><strong>Patient:</strong> {{ $patient_name }}@if($patient_dob)  (DOB: {{ $patient_dob }})@endif</span>
        <span class="meta-row"><strong>Member ID:</strong> {{ $patient_id }}</span>
        <span class="meta-row"><strong>Provider Claim #:</strong> {{ $claim_number }}</span>
        @if($payer_icn)<span class="meta-row"><strong>Payer Claim # (ICN):</strong> {{ $payer_icn }}</span>@endif
        <span class="meta-row"><strong>Date of Service:</strong> {{ $service_date ?? '[DOS]' }}</span>
        <span class="meta-row"><strong>Denial Date:</strong> {{ $denial_date ?? '[Date]' }}</span>
        <span class="meta-row"><strong>Denial Code:</strong> {{ $denial_code }}</span>
        <span class="meta-row"><strong>Denied Amount:</strong> ${{ $denied_amount }}</span>
    </div>

    {{-- Body. The letter_text was already produced by
         AppealLetterService and may have been edited by the operator.
         We strip the top header lines (date, payer block, subject,
         claim ref) because the PDF renders those with proper styling
         above; the body-only portion follows the "To Whom It May
         Concern:" line. --}}
    @php
        // Split the operator-edited letter_text at the salutation so
        // the PDF doesn't double-render the header. If salutation isn't
        // found, render the full text — safer than dropping content.
        $body = $letter_text ?? '';
        $marker = 'To Whom It May Concern:';
        $pos = strpos($body, $marker);
        $bodyOnly = $pos !== false ? substr($body, $pos + strlen($marker)) : $body;
        // Also trim the "Sincerely," signature block at the end so we
        // can render our own styled signature.
        $sincerelyPos = strrpos($bodyOnly, 'Sincerely,');
        if ($sincerelyPos !== false) {
            $bodyOnly = substr($bodyOnly, 0, $sincerelyPos);
        }
        // Strip enclosures line if present — we render our own.
        $bodyOnly = preg_replace('/Enclosures:[^\n]*/i', '', $bodyOnly);
    @endphp

    <p>To Whom It May Concern:</p>

    <div class="body-text">{!! nl2br(e(trim($bodyOnly))) !!}</div>

    {{-- Signature --}}
    <div class="signature">
        <p>Sincerely,</p>
        <div class="signature-line"></div>
        <div class="signature-block">
            [Billing Manager Name]<br>
            {{ $brand['name'] ?? 'Provider' }}
            @if(!empty($brand['phone']))<br>Phone: {{ $brand['phone'] }}@endif
            @if(!empty($brand['email']))<br>Email: {{ $brand['email'] }}@endif
        </div>
    </div>

    {{-- Enclosures --}}
    <div class="enclosures">
        <strong>Enclosures:</strong>
        @if(!empty($attachments_count) && $attachments_count > 0)
            {{ $attachments_count }} document{{ $attachments_count === 1 ? '' : 's' }} attached (see accompanying list).
        @else
            [List supporting documentation attached]
        @endif
    </div>

    {{-- Page footer --}}
    <div class="footer">
        Appeal letter generated {{ $today }} · Claim {{ $claim_number }} · Denial #{{ $denial->id }}
    </div>
</body>
</html>
