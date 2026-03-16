<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Credentik')</title>
    <!--[if mso]>
    <style>table,td{font-family:Arial,sans-serif!important;}</style>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
                    {{-- Header --}}
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            @if(!empty($agency->logo_url))
                                <img src="{{ $agency->logo_url }}" alt="{{ $agency->name }}" height="40" style="height:40px;max-width:200px;">
                            @else
                                <div style="font-size:22px;font-weight:700;color:{{ $agency->primary_color ?? '#2C4A5A' }};letter-spacing:-0.5px;">
                                    {{ $agency->name ?? 'Credentik' }}
                                </div>
                            @endif
                        </td>
                    </tr>

                    {{-- Body Card --}}
                    <tr>
                        <td style="background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
                            {{-- Accent bar --}}
                            <div style="height:4px;background:linear-gradient(90deg,{{ $agency->primary_color ?? '#2C4A5A' }},{{ $agency->accent_color ?? '#D4A855' }});"></div>

                            <td style="padding:32px 28px;">
                                @yield('content')
                            </td>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:24px 16px;font-size:12px;color:#9ca3af;line-height:1.5;">
                            @hasSection('footer')
                                @yield('footer')
                            @else
                                <p style="margin:0;">{{ $agency->name ?? 'Credentik' }}</p>
                                @if(!empty($agency->phone))
                                    <p style="margin:4px 0 0;">{{ $agency->phone }}</p>
                                @endif
                                @if(!empty($agency->address_city))
                                    <p style="margin:4px 0 0;">{{ $agency->address_city }}, {{ $agency->address_state }} {{ $agency->address_zip }}</p>
                                @endif
                                <p style="margin:12px 0 0;font-size:11px;color:#d1d5db;">
                                    Powered by <a href="https://credentik.com" style="color:#d1d5db;text-decoration:underline;">Credentik</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
