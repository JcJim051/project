<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#8fd400;padding:16px 24px;">
                            <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#1f2937;font-weight:700;">AIM · Plataforma Integral</div>
                            <div style="margin-top:6px;font-size:20px;line-height:1.2;color:#0f172a;font-weight:800;">{{ $title }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <div style="margin-bottom:14px;font-size:13px;color:#6b7280;">Proyecto</div>
                            <div style="margin-bottom:18px;font-size:22px;line-height:1.25;color:#111827;font-weight:800;">{{ $projectName }}</div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;">Evento</div>
                                        <div style="margin-top:6px;font-size:16px;color:#111827;font-weight:700;">{{ $eventLabel }}</div>
                                        @if(!empty($detail))
                                            <div style="margin-top:10px;font-size:14px;line-height:1.45;color:#374151;">{{ $detail }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if(!empty($actionUrl) && !empty($actionLabel))
                                <div style="margin-top:22px;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#8fd400;color:#111827;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:8px;">
                                        {{ $actionLabel }}
                                    </a>
                                </div>
                            @endif

                            <div style="margin-top:26px;font-size:12px;line-height:1.5;color:#6b7280;">
                                Este es un mensaje oficial automático de la Plataforma integral de gestión de proyectos de AIM.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

