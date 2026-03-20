@php
    $agency = $agency ?? (object)[
        'name' => 'Credentik',
        'primary_color' => '#0891b2',
        'accent_color' => '#06b6d4',
        'phone' => null,
        'email' => null,
        'address_city' => null,
        'address_state' => null,
        'address_zip' => null,
        'logo_url' => null,
    ];
    $primaryColor = $agency->primary_color ?? '#0891b2';
    $accentColor = $agency->accent_color ?? '#06b6d4';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Credentik')</title>
    <!--[if mso]>
    <style>table,td,div{font-family:Arial,sans-serif!important;}</style>
    <![endif]-->
    <style>
        body { margin:0; padding:0; background:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; }
        .wrapper { max-width:600px; margin:24px auto; }
        .card { background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06); }
        .header { background:{{ $primaryColor }}; padding:24px 32px; }
        .header h1 { color:#ffffff; font-size:20px; margin:0; font-weight:700; letter-spacing:-0.3px; }
        .header p { color:rgba(255,255,255,.8); font-size:13px; margin:4px 0 0; }
        .content { padding:32px; color:#374151; font-size:15px; line-height:1.65; }
        .content h2 { color:#111827; margin:0 0 16px; font-size:20px; font-weight:700; }
        .content p { margin:0 0 12px; }

        /* Buttons */
        .btn { display:inline-block; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px; margin:16px 0; }
        .btn-primary { background:{{ $primaryColor }}; color:#ffffff !important; }
        .btn-success { background:#059669; color:#ffffff !important; }
        .btn-danger { background:#dc2626; color:#ffffff !important; }
        .btn-outline { background:transparent; color:{{ $primaryColor }} !important; border:2px solid {{ $primaryColor }}; }

        /* Callout Boxes */
        .info-box { background:#ecfeff; border-left:4px solid {{ $primaryColor }}; padding:14px 16px; border-radius:0 6px 6px 0; margin:16px 0; font-size:14px; color:#164e63; }
        .success-box { background:#ecfdf5; border-left:4px solid #10b981; padding:14px 16px; border-radius:0 6px 6px 0; margin:16px 0; font-size:14px; color:#065f46; }
        .alert-box { background:#fffbeb; border-left:4px solid #f59e0b; padding:14px 16px; border-radius:0 6px 6px 0; margin:16px 0; font-size:14px; color:#92400e; }
        .danger-box { background:#fef2f2; border-left:4px solid #ef4444; padding:14px 16px; border-radius:0 6px 6px 0; margin:16px 0; font-size:14px; color:#991b1b; }

        /* Detail Rows */
        .details { margin:16px 0; background:#f9fafb; border-radius:8px; overflow:hidden; border:1px solid #f3f4f6; }
        .detail-row { display:flex; justify-content:space-between; align-items:center; padding:10px 16px; border-bottom:1px solid #f3f4f6; font-size:14px; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { color:#6b7280; }
        .detail-value { font-weight:600; color:#111827; text-align:right; }

        /* Status Badges */
        .badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:600; letter-spacing:0.3px; text-transform:uppercase; }

        /* Divider */
        .divider { border:none; border-top:1px solid #e5e7eb; margin:24px 0; }

        /* Footer */
        .footer { background:#f9fafb; padding:20px 32px; text-align:center; color:#9ca3af; font-size:12px; border-top:1px solid #e5e7eb; line-height:1.6; }
        .footer a { color:{{ $primaryColor }}; text-decoration:none; }
        .footer .powered { margin-top:12px; font-size:11px; color:#d1d5db; }
        .footer .powered a { color:#d1d5db; text-decoration:underline; }

        /* Mobile */
        @media only screen and (max-width:640px) {
            .wrapper { margin:0 !important; }
            .card { border-radius:0 !important; }
            .header, .content, .footer { padding-left:20px !important; padding-right:20px !important; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        {{-- Header --}}
        <div class="header">
            @if(!empty($agency->logo_url))
                <img src="{{ $agency->logo_url }}" alt="{{ $agency->name }}" style="max-height:36px;margin-bottom:8px;display:block;">
            @endif
            <h1>{{ $agency->name ?? 'Credentik' }}</h1>
            <p>Credentialing & Licensing Platform</p>
        </div>

        {{-- Content --}}
        <div class="content">
            @yield('content')
        </div>

        {{-- Footer --}}
        <div class="footer">
            @hasSection('footer')
                @yield('footer')
            @else
                <p style="margin:0;">&copy; {{ date('Y') }} {{ $agency->name ?? 'Credentik' }}. All rights reserved.</p>
                @if(!empty($agency->phone))
                    <p style="margin:4px 0 0;">{{ $agency->phone }}</p>
                @endif
                @if(!empty($agency->address_city))
                    <p style="margin:4px 0 0;">{{ $agency->address_city }}, {{ $agency->address_state }} {{ $agency->address_zip }}</p>
                @endif
                <p class="powered">
                    Powered by <a href="https://credentik.com">Credentik</a>
                </p>
            @endif
        </div>
    </div>
</div>
</body>
</html>
