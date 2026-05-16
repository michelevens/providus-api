{{-- Base appeal-letter template. Plain text output (no HTML).
     Per-category templates render their argument paragraph
     separately, then this template wraps it with the standard
     header / claim-reference block / closing / signature.

     Layout note: the php directive below must sit on its own line.
     Gluing it to the closing of a Blade comment on the same line
     confuses the directive extractor — the block leaks as a raw
     placeholder and the local variables never get assigned. Bit
     me 2026-05-16. --}}
@php
    $addrLine2 = trim(implode(', ', array_filter([
        $brand['address_city'] ?? null,
        trim(($brand['address_state'] ?? '') . ' ' . ($brand['address_zip'] ?? ''))
    ])));
    $level = (int) ($appeal_level ?: 1);
    $levelLabel = $level === 1 ? 'Appeal' : ($level === 2 ? 'Second-Level Appeal' : 'Third-Level Appeal');
@endphp
{{ $today }}

{{ $payer_name }}
[Payer Appeals Address]
[City, State ZIP]

RE: {{ $levelLabel }} of Denied Claim
Patient: {{ $patient_name }}@if($patient_dob)  (DOB: {{ $patient_dob }})@endif
Member ID: {{ $patient_id }}
Provider Claim #: {{ $claim_number }}@if($payer_icn)

Payer Claim # (ICN): {{ $payer_icn }}@endif
Date of Service: {{ $service_date ?? '[DOS]' }}
Denial Date: {{ $denial_date ?? '[Date]' }}
Denial Code: {{ $denial_code }}
Denied Amount: ${{ $denied_amount }}

To Whom It May Concern:

This letter serves as a formal {{ strtolower($levelLabel) }} of the above-referenced
claim, which was denied on {{ $denial_date ?? '[Date]' }} for the following reason:

"{{ $denial_reason }}"

{{ $argument }}

We respectfully request that the original determination be reviewed and the
claim be reprocessed for payment. Please confirm receipt of this appeal in
writing and provide a determination within the timeframe required by your
appeals process.

Should you require additional information or documentation, please contact
our office at the number below.

Sincerely,


[Billing Manager Name]
{{ $brand['name'] ?? 'Provider' }}
@if(!empty($brand['address_street']))
{{ $brand['address_street'] }}
@endif
@if($addrLine2)
{{ $addrLine2 }}
@endif
@if(!empty($brand['phone']))
Phone: {{ $brand['phone'] }}
@endif
@if(!empty($brand['email']))
Email: {{ $brand['email'] }}
@endif

Enclosures: [List supporting documentation attached]
