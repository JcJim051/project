<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->title }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: radial-gradient(circle at top, #dbeafe 0%, #f8fafc 45%, #fefce8 100%); color: #0f172a; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 16px 48px; }
        .layout { display: grid; gap: 24px; }
        .panel { background: #fff; border: 1px solid #dbe4f0; border-radius: 24px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.07); overflow: hidden; }
        .side { padding: 24px; background: linear-gradient(180deg, #082f49 0%, #0f766e 100%); color: #e2e8f0; }
        .card { background: #fff; border: 1px solid #dbe4f0; border-radius: 24px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .hero { padding: 24px; background: radial-gradient(circle at top right, #bfdbfe 0%, #f8fafc 50%, #ffffff 100%); border-bottom: 1px solid #e2e8f0; }
        .body { padding: 24px; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .badge-open { background: #dcfce7; color: #166534; }
        .badge-off { background: #e5e7eb; color: #374151; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .side h1 { margin: 14px 0 8px; font-size: 28px; color: #fff; }
        .side p { margin: 0; color: #cbd5e1; }
        .qr-box { margin-top: 18px; border-radius: 24px; background: #fff; padding: 18px; text-align: center; }
        .qr-box img { width: min(100%, 280px); height: auto; }
        .meta-grid { margin-top: 18px; display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .stat { border-radius: 18px; background: rgba(255,255,255,0.08); padding: 14px; }
        .stat strong { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #bae6fd; margin-bottom: 6px; }
        .stat span { font-size: 16px; font-weight: 700; color: #fff; }
        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .link-button { display: inline-flex; align-items: center; justify-content: center; border-radius: 14px; padding: 12px 18px; font-size: 14px; font-weight: 700; text-decoration: none; }
        .link-dark { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.15); }
        .link-light { background: #f8fafc; color: #0f172a; }
        .counter { margin-top: 14px; display: flex; gap: 12px; flex-wrap: wrap; font-size: 14px; color: #334155; }
        .empty { padding: 18px; border-radius: 18px; background: #f8fafc; color: #64748b; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #eef2f7; text-align: left; font-size: 14px; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
        @media (min-width: 960px) { .layout { grid-template-columns: 360px minmax(0, 1fr); } }
    </style>
</head>
<body>
<div class="wrap">
    @php($statusClass = match($status) { 'open' => 'badge-open', 'scheduled' => 'badge-warning', 'expired' => 'badge-danger', 'inactive', 'closed' => 'badge-off', default => 'badge-off' })
    @php($statusLabel = match($status) { 'open' => 'Campaña abierta', 'scheduled' => 'Programada', 'expired' => 'Vencida', 'inactive' => 'Apagada', 'closed' => 'Cerrada', default => 'No disponible' })
    <div class="layout">
        <aside class="panel side">
            <div class="badge {{ $statusClass }}">{{ $statusLabel }}</div>
            <h1>{{ $campaign->title }}</h1>
            <p>{{ $campaign->description ?: 'Escanea el código QR para caracterizarte según tu rol.' }}</p>
            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="QR de caracterización">
            </div>
            <div class="meta-grid">
                <div class="stat">
                    <strong>Total</strong>
                    <span>{{ $summary['count'] }}</span>
                </div>
                <div class="stat">
                    <strong>Pendientes</strong>
                    <span>{{ $summary['pending'] }}</span>
                </div>
                <div class="stat">
                    <strong>Aprobadas</strong>
                    <span>{{ $summary['approved'] }}</span>
                </div>
                <div class="stat">
                    <strong>Rechazadas</strong>
                    <span>{{ $summary['rejected'] }}</span>
                </div>
            </div>
        </aside>

        <main class="card">
            <div class="hero">
                <h2 style="margin:0 0 6px;font-size:28px;">Caracterización de equipo</h2>
                <p style="margin:0;color:#475569;">Selecciona tu rol y diligencia tu información para continuar con el alta en la plataforma.</p>
                <div class="counter">
                    <span><strong>Estado:</strong> {{ $statusLabel }}</span>
                    <span><strong>Solicitudes:</strong> {{ $summary['count'] }}</span>
                </div>
                <div class="toolbar">
                    <a href="{{ $registerUrl }}" class="link-button link-light">Ir al formulario</a>
                </div>
            </div>
            <div class="body">
                <div class="text-sm font-semibold text-gray-800">Cómo funciona</div>
                <div class="mt-4 overflow-x-auto">
                    <table>
                        <thead>
                        <tr>
                            <th>Paso</th>
                            <th>Descripción</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>1</td>
                            <td>Escanea el código QR o abre el formulario de registro.</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Selecciona si eres formulador, estructurador o especialista.</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>Diligencia tu información y envíala para quedar pendiente de revisión administrativa.</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>El equipo administrador validará la solicitud y hará el alta en la plataforma si aplica.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
