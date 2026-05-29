<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f3faf6;font-family:Arial,Helvetica,sans-serif;color:#0f2c3a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3faf6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="width:640px;max-width:94%;background:#ffffff;border:1px solid #d7e8df;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(120deg,#00a86b,#00445c);padding:18px 22px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ url('/img/logo.jpg') }}" alt="Logo AIM" style="height:42px;width:auto;display:block;border:0;">
                                    </td>
                                    <td align="right" style="color:#eafff5;font-size:13px;font-weight:600;">
                                        Gestión de Proyectos
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 22px;">
                            <h1 style="margin:0 0 10px 0;font-size:22px;line-height:1.2;color:#00445c;">{{ $title }}</h1>
                            <p style="margin:0 0 14px 0;font-size:14px;line-height:1.6;color:#35505a;">
                                Proyecto: <strong>{{ $projectName }}</strong>
                            </p>

                            <div style="border:1px solid #d7e8df;background:#f8fff9;border-radius:10px;padding:14px;">
                                <p style="margin:0 0 8px 0;font-size:13px;color:#2b4650;"><strong>Evento:</strong> {{ $eventLabel }}</p>
                                @if(!empty($detail))
                                    <p style="margin:0;font-size:13px;line-height:1.5;color:#2b4650;">{{ $detail }}</p>
                                @endif
                            </div>

                            @if(!empty($actionUrl) && !empty($actionLabel))
                                <div style="padding-top:14px;text-align:center;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#8fd400;color:#103126;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:10px;">
                                        {{ $actionLabel }}
                                    </a>
                                    <p style="margin:14px 0 0 0;font-size:12px;line-height:1.5;color:#5f7a84;">
                                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                        <a href="{{ $actionUrl }}" style="color:#00445c;word-break:break-all;text-decoration:underline;">{{ $actionUrl }}</a>
                                    </p>
                                </div>
                            @endif

                            <p style="margin:18px 0 0 0;font-size:12px;line-height:1.5;color:#5f7a84;">
                                Este es un mensaje automático de la Plataforma integral de gestión de proyectos de AIM.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
