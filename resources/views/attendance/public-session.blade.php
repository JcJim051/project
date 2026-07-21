<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $session->title ?: 'Sesión de asistencia' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: radial-gradient(circle at top, #ecfccb 0%, #f8fafc 38%, #e0f2fe 100%); color: #0f172a; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 16px 48px; }
        .layout { display: grid; gap: 24px; }
        .panel { background: #fff; border: 1px solid #dbe4f0; border-radius: 24px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.07); overflow: hidden; }
        .side { padding: 24px; background: linear-gradient(180deg, #0f172a 0%, #164e63 100%); color: #e2e8f0; }
        .card { background: #fff; border: 1px solid #dbe4f0; border-radius: 24px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .hero { padding: 24px; background: radial-gradient(circle at top right, #d9f99d 0%, #f8fafc 45%, #ffffff 100%); border-bottom: 1px solid #e2e8f0; }
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
        .people-card { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #eef2f7; text-align: left; font-size: 14px; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
        .empty { padding: 18px; border-radius: 18px; background: #f8fafc; color: #64748b; font-size: 14px; }
        @media (min-width: 960px) { .layout { grid-template-columns: 360px minmax(0, 1fr); } }
    </style>
</head>
<body>
<div class="wrap">
    @php($statusClass = match($status) { 'open' => 'badge-open', 'scheduled' => 'badge-warning', 'expired' => 'badge-danger', 'inactive', 'closed' => 'badge-off', default => 'badge-off' })
    @php($statusLabel = match($status) { 'open' => 'Sesión abierta', 'scheduled' => 'Programada', 'expired' => 'Vencida', 'inactive' => 'Apagada', 'closed' => 'Cerrada', default => 'No disponible' })
    <div class="layout">
        <aside class="panel side">
            <div class="badge {{ $statusClass }}">{{ $statusLabel }}</div>
            <h1>{{ $session->title ?: 'Sesión de asistencia' }}</h1>
            <p>{{ $session->objetivo ?: 'Escanea el código QR para registrar tu asistencia.' }}</p>

            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="QR de registro de asistencia">
            </div>

            <div class="meta-grid">
                <div class="stat">
                    <strong>Fecha</strong>
                    <span>{{ optional($session->fecha)->format('d/m/Y') ?: '-' }}</span>
                </div>
                <div class="stat">
                    <strong>Hora inicio</strong>
                    <span>{{ $session->hora_inicio instanceof \DateTimeInterface ? $session->hora_inicio->format('H:i') : ($session->hora_inicio ? \Illuminate\Support\Carbon::parse($session->hora_inicio)->format('H:i') : '-') }}</span>
                </div>
                <div class="stat">
                    <strong>Hora fin</strong>
                    <span>{{ $session->hora_terminacion instanceof \DateTimeInterface ? $session->hora_terminacion->format('H:i') : ($session->hora_terminacion ? \Illuminate\Support\Carbon::parse($session->hora_terminacion)->format('H:i') : '-') }}</span>
                </div>
                <div class="stat">
                    <strong>Lugar</strong>
                    <span>{{ $session->lugar ?: '-' }}</span>
                </div>
                <div class="stat">
                    <strong>Registros</strong>
                    <span data-attendance-count>{{ $summary['count'] }}</span>
                </div>
            </div>

            <div class="toolbar">
                <a href="{{ route('attendance.download.xlsx', $session->public_token) }}" class="link-button link-dark">Descargar XLSX</a>
                <a href="{{ route('attendance.download.pdf', $session->public_token) }}" class="link-button link-dark">Descargar PDF</a>
            </div>
        </aside>

        <main class="card">
            <div class="hero">
                <h2 style="margin:0 0 6px;font-size:28px;">{{ $session->title ?: 'Sesión de asistencia' }}</h2>
                <p style="margin:0;color:#475569;">{{ $session->objetivo ?: 'Información pública de la sesión de asistencia.' }}</p>
                <div class="counter">
                    <span><strong>Fecha:</strong> {{ optional($session->fecha)->format('d/m/Y') ?: '-' }}</span>
                    <span><strong>Hora inicio:</strong> {{ $session->hora_inicio instanceof \DateTimeInterface ? $session->hora_inicio->format('H:i') : ($session->hora_inicio ? \Illuminate\Support\Carbon::parse($session->hora_inicio)->format('H:i') : '-') }}</span>
                    <span><strong>Hora fin:</strong> {{ $session->hora_terminacion instanceof \DateTimeInterface ? $session->hora_terminacion->format('H:i') : ($session->hora_terminacion ? \Illuminate\Support\Carbon::parse($session->hora_terminacion)->format('H:i') : '-') }}</span>
                    <span><strong>Lugar:</strong> {{ $session->lugar ?: '-' }}</span>
                </div>
                <div class="toolbar">
                    <a href="{{ $registerUrl }}" class="link-button link-light">Ir al registro de asistencia</a>
                </div>
            </div>
            <div class="body">
                <div class="people-card" style="margin-top:0;border-top:0;padding-top:0;">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:18px;font-weight:700;">Asistentes registrados</div>
                            <div style="font-size:14px;color:#64748b;">El contador se actualiza automáticamente en esta página.</div>
                        </div>
                        <div style="font-size:14px;color:#0f172a;">Total: <strong data-attendance-count-inline>{{ $summary['count'] }}</strong></div>
                    </div>

                    <div style="margin-top:16px;overflow-x:auto;">
                        <table>
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Documento</th>
                                <th>Entidad / área</th>
                                <th>Registro</th>
                            </tr>
                            </thead>
                            <tbody data-attendance-table>
                            @forelse($summary['entries'] as $entry)
                                <tr>
                                    <td>{{ $entry['sequence_number'] }}</td>
                                    <td>{{ $entry['full_name'] }}</td>
                                    <td>{{ $entry['document_number'] }}</td>
                                    <td>{{ $entry['organization_area'] ?: '-' }}</td>
                                    <td>{{ $entry['registered_at'] ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"><div class="empty">Aún no hay asistentes registrados en esta reunión.</div></td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script>
setInterval(async () => {
    try {
        const response = await fetch(@js(route('attendance.summary', $session->public_token)), {
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) return;
        const data = await response.json();
        document.querySelectorAll('[data-attendance-count]').forEach((node) => node.textContent = String(data.count ?? 0));
        document.querySelectorAll('[data-attendance-count-inline]').forEach((node) => node.textContent = String(data.count ?? 0));
        const tbody = document.querySelector('[data-attendance-table]');
        if (!tbody) return;
        if (!Array.isArray(data.entries) || data.entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5"><div class="empty">Aún no hay asistentes registrados en esta reunión.</div></td></tr>';
            return;
        }
        tbody.innerHTML = data.entries.map((entry) => `
            <tr>
                <td>${entry.sequence_number ?? ''}</td>
                <td>${entry.full_name ?? ''}</td>
                <td>${entry.document_number ?? ''}</td>
                <td>${entry.organization_area ?? '-'}</td>
                <td>${entry.registered_at ?? '-'}</td>
            </tr>
        `).join('');
    } catch (error) {
        console.debug(error);
    }
}, 10000);
</script>
</body>
</html>
