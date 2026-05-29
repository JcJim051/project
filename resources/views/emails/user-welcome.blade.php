<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bienvenido a Gestión de Proyectos</title>
</head>
<body style="margin:0;padding:0;background:#f3faf6;font-family:Arial,Helvetica,sans-serif;color:#0f2c3a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3faf6;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px;max-width:94%;background:#ffffff;border:1px solid #d7e8df;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(120deg,#00a86b,#00445c);padding:18px 22px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ $logoUrl }}" alt="Logo AIM" style="height:42px;width:auto;display:block;border:0;" />
                                    </td>
                                    <td align="right" style="color:#eafff5;font-size:13px;font-weight:600;">
                                        Gestión de Proyectos
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 22px 8px 22px;">
                            <h1 style="margin:0 0 10px 0;font-size:22px;line-height:1.2;color:#00445c;">Bienvenido(a), {{ $name ?: 'usuario' }}</h1>
                            <p style="margin:0 0 14px 0;font-size:14px;line-height:1.6;color:#35505a;">
                                Tu usuario fue creado correctamente en la plataforma <strong>Gestión de Proyectos</strong>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 22px 8px 22px;">
                            <div style="border:1px solid #d7e8df;background:#f8fff9;border-radius:10px;padding:14px 14px;">
                                <p style="margin:0 0 8px 0;font-size:13px;color:#2b4650;"><strong>Usuario:</strong> {{ $email }}</p>
                                <p style="margin:0;font-size:13px;color:#2b4650;"><strong>Contraseña inicial:</strong> tu cédula registrada.</p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 22px;">
                            <div style="border:1px solid #facc15;background:#fffbea;border-radius:10px;padding:12px;">
                                <p style="margin:0;font-size:13px;line-height:1.5;color:#7c5a02;">
                                    Por seguridad, en tu primer ingreso deberás <strong>cambiar la contraseña y cargar foto de perfil</strong> para poder continuar en el panel.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:12px 22px 22px 22px;">
                            <a href="{{ $loginUrl }}" style="display:inline-block;background:#8fd400;color:#103126;text-decoration:none;font-weight:700;font-size:14px;padding:12px 22px;border-radius:10px;">
                                Ingresar al panel
                            </a>
                            <p style="margin:14px 0 0 0;font-size:12px;line-height:1.5;color:#5f7a84;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                                <a href="{{ $loginUrl }}" style="color:#00445c;word-break:break-all;text-decoration:underline;">{{ $loginUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 22px 22px 22px;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#5f7a84;">
                                Si no solicitaste este acceso, por favor informa al administrador de la plataforma.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
