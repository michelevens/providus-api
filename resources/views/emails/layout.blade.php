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
    $headerType = $headerType ?? 'default';
    $headerColors = [
        'default' => ['from' => $primaryColor, 'to' => '#065f46'],
        'success' => ['from' => '#059669', 'to' => '#047857'],
        'danger'  => ['from' => '#dc2626', 'to' => '#991b1b'],
        'warning' => ['from' => '#d97706', 'to' => '#b45309'],
        'info'    => ['from' => '#2563eb', 'to' => '#1d4ed8'],
    ];
    $hc = $headerColors[$headerType] ?? $headerColors['default'];
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>@yield('title', 'Credentik')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <style>
        table { border-collapse: collapse; }
        td, th { font-family: Arial, sans-serif !important; }
        a { text-decoration: none; }
    </style>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #1a1a2e !important; }
            .card-bg { background-color: #16213e !important; }
            .card-border { border-color: #2a2a4a !important; }
            .text-dark { color: #e2e8f0 !important; }
            .text-body { color: #cbd5e1 !important; }
            .text-muted { color: #94a3b8 !important; }
            .text-light { color: #64748b !important; }
            .details-bg { background-color: #1e293b !important; }
            .details-border { border-color: #334155 !important; }
            .detail-border { border-color: #334155 !important; }
            .footer-bg { background-color: #0f172a !important; }
            .footer-border { border-color: #1e293b !important; }
            .callout-info { background-color: #164e63 !important; border-color: #0891b2 !important; }
            .callout-success { background-color: #064e3b !important; border-color: #10b981 !important; }
            .callout-warning { background-color: #78350f !important; border-color: #f59e0b !important; }
            .callout-danger { background-color: #7f1d1d !important; border-color: #ef4444 !important; }
        }

        /* Mobile */
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .content-padding { padding: 24px 20px !important; }
            .header-padding { padding: 28px 20px !important; }
            .footer-padding { padding: 20px !important; }
            .detail-table { width: 100% !important; }
            .btn-full { display: block !important; width: 100% !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

    {{-- Preheader text (hidden preview text) --}}
    @hasSection('preheader')
    <div style="display:none;font-size:1px;color:#f1f5f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        @yield('preheader')
    </div>
    @endif

    {{-- Full-width background --}}
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f1f5f9;" class="email-bg">
        <tr>
            <td align="center" style="padding:24px 16px;">

                {{-- Email Container --}}
                <table role="presentation" cellpadding="0" cellspacing="0" width="580" style="max-width:580px;width:100%;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.07),0 2px 4px -2px rgba(0,0,0,0.05);" class="email-container">

                    {{-- Header with gradient --}}
                    <tr>
                        <td style="background:{{ $hc['from'] }};background:linear-gradient(135deg, {{ $hc['from'] }} 0%, {{ $hc['to'] }} 100%);padding:32px 32px 28px;" class="header-padding">
                            <!--[if mso]>
                            <v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:580px;">
                                <v:fill type="gradient" color="{{ $hc['from'] }}" color2="{{ $hc['to'] }}" angle="135"/>
                                <v:textbox inset="32px,32px,32px,28px">
                            <![endif]-->
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                @if(!empty($agency->logo_url))
                                <tr>
                                    <td style="padding-bottom:12px;">
                                        <img src="{{ $agency->logo_url }}" alt="{{ $agency->name }}" height="36" style="height:36px;max-width:180px;display:block;">
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.3px;line-height:1.2;">
                                        {{ $agency->name ?? 'Credentik' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px;color:rgba(255,255,255,0.75);padding-top:4px;letter-spacing:0.2px;">
                                        Credentialing &amp; Licensing Platform
                                    </td>
                                </tr>
                            </table>
                            <!--[if mso]></v:textbox></v:rect><![endif]-->
                        </td>
                    </tr>

                    {{-- Content --}}
                    <tr>
                        <td style="background-color:#ffffff;padding:36px 32px;" class="card-bg content-padding">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:24px 32px;" class="footer-bg footer-border footer-padding">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="font-size:12px;color:#94a3b8;line-height:1.6;" class="text-light">
                                        @hasSection('footer')
                                            @yield('footer')
                                        @else
                                            <p style="margin:0;font-weight:600;color:#64748b;" class="text-muted">&copy; {{ date('Y') }} {{ $agency->name ?? 'Credentik' }}</p>
                                            @if(!empty($agency->phone))
                                                <p style="margin:4px 0 0;">{{ $agency->phone }}</p>
                                            @endif
                                            @if(!empty($agency->address_city))
                                                <p style="margin:4px 0 0;">{{ $agency->address_city }}, {{ $agency->address_state }} {{ $agency->address_zip }}</p>
                                            @endif
                                            <p style="margin:12px 0 0;font-size:11px;color:#cbd5e1;">
                                                Powered by <a href="https://credentik.com" style="color:{{ $primaryColor }};text-decoration:none;font-weight:500;">Credentik</a>
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                {{-- End Email Container --}}

            </td>
        </tr>
    </table>
</body>
</html>
