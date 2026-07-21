<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Registro de asistencia</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: linear-gradient(180deg, #f5f7eb 0%, #eef4ff 100%); color: #0f172a; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: #fff; border: 1px solid #dbe4f0; border-radius: 24px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .hero { padding: 24px; background: radial-gradient(circle at top right, #d9f99d 0%, #f8fafc 45%, #ffffff 100%); border-bottom: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .badge-open { background: #dcfce7; color: #166534; }
        .badge-off { background: #e5e7eb; color: #374151; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .body { padding: 24px; }
        label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #475569; margin-bottom: 6px; }
        input { width: 100%; border: 1px solid #cbd5e1; border-radius: 14px; padding: 12px 14px; font-size: 15px; box-sizing: border-box; }
        .grid { display: grid; gap: 16px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .counter { margin-top: 14px; display: flex; gap: 12px; flex-wrap: wrap; font-size: 14px; color: #334155; }
        .sig { border: 1px dashed #94a3b8; border-radius: 16px; background: #f8fafc; }
        canvas { width: 100%; height: 180px; display: block; touch-action: none; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; }
        button, .link-button { border: none; border-radius: 14px; padding: 12px 18px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .primary { background: #0f766e; color: white; }
        .secondary { background: #e2e8f0; color: #0f172a; }
        .alert { margin-bottom: 16px; padding: 12px 14px; border-radius: 14px; font-size: 14px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="hero">
            @php($statusClass = match($status) { 'open' => 'badge-open', 'scheduled' => 'badge-warning', 'expired' => 'badge-danger', 'inactive', 'closed' => 'badge-off', default => 'badge-off' })
            <span class="badge {{ $statusClass }}">
                {{ match($status) { 'open' => 'Sesión abierta', 'scheduled' => 'Programada', 'expired' => 'Vencida', 'inactive' => 'Apagada', 'closed' => 'Cerrada', default => 'No disponible' } }}
            </span>
            <h1 style="margin:14px 0 6px;font-size:28px;">{{ $session->title ?: 'Registro de asistencia' }}</h1>
            <p style="margin:0;color:#475569;">Completa tus datos y firma para registrar tu participación.</p>
            <div class="counter">
                <span><strong>Fecha:</strong> {{ optional($session->fecha)->format('d/m/Y') ?: '-' }}</span>
                <span><strong>Lugar:</strong> {{ $session->lugar ?: '-' }}</span>
                <span><strong>Registrados:</strong> {{ $summary['count'] }}</span>
            </div>
        </div>
        <div class="body">
            @if (session('status'))
                <div class="alert success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert error">{{ $errors->first() }}</div>
            @endif
            @if (!$canSubmit)
                <div class="alert error">Este enlace no se encuentra disponible para registrar nuevas asistencias.</div>
            @endif

            <div class="actions" style="margin-top:0;margin-bottom:16px;">
                <a href="{{ $sessionUrl }}" class="link-button secondary">Volver a la sesión</a>
            </div>

            <form method="POST" action="{{ route('attendance.submit', $session->public_token) }}" id="attendance-form">
                @csrf
                <div class="grid grid-2">
                    <div>
                        <label>Documento</label>
                        <input type="text" name="document_number" value="{{ old('document_number') }}" @disabled(!$canSubmit) required>
                    </div>
                    <div>
                        <label>Celular</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" @disabled(!$canSubmit)>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label>Nombres y apellidos</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" @disabled(!$canSubmit) required>
                </div>

                <div style="margin-top:16px;">
                    <label>Área / Empresa / Sector / Municipio / Barrio</label>
                    <input type="text" name="organization_area" value="{{ old('organization_area') }}" @disabled(!$canSubmit)>
                </div>

                <div style="margin-top:16px;">
                    <label>Email o dirección</label>
                    <input type="text" name="email_or_address" value="{{ old('email_or_address') }}" @disabled(!$canSubmit)>
                </div>

                <div style="margin-top:16px;">
                    <label>Firma</label>
                    <div class="sig">
                        <canvas id="signature-pad"></canvas>
                    </div>
                    <input type="hidden" name="signature_data" id="signature_data">
                    <div class="actions">
                        <button type="button" class="secondary" id="clear-signature">Limpiar firma</button>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="primary" @disabled(!$canSubmit)>Registrar asistencia</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const canvas = document.getElementById('signature-pad');
    const hidden = document.getElementById('signature_data');
    const clearButton = document.getElementById('clear-signature');
    if (!canvas || !hidden) return;

    const ctx = canvas.getContext('2d');
    let drawing = false;

    const resize = () => {
        const ratio = window.devicePixelRatio || 1;
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = 180 * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f172a';
    };
    resize();
    window.addEventListener('resize', resize);

    const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        const source = event.touches ? event.touches[0] : event;
        return { x: source.clientX - rect.left, y: source.clientY - rect.top };
    };

    const start = (event) => {
        drawing = true;
        const p = point(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        event.preventDefault();
    };

    const move = (event) => {
        if (!drawing) return;
        const p = point(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        event.preventDefault();
    };

    const end = () => {
        drawing = false;
        hidden.value = canvas.toDataURL('image/png');
    };

    ['mousedown', 'touchstart'].forEach((name) => canvas.addEventListener(name, start, { passive: false }));
    ['mousemove', 'touchmove'].forEach((name) => canvas.addEventListener(name, move, { passive: false }));
    ['mouseup', 'mouseleave', 'touchend'].forEach((name) => canvas.addEventListener(name, end));

    clearButton?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hidden.value = '';
    });

    document.getElementById('attendance-form')?.addEventListener('submit', (event) => {
        hidden.value = canvas.toDataURL('image/png');
        if (!hidden.value || hidden.value.length < 64) {
            event.preventDefault();
            alert('Debes registrar la firma antes de continuar.');
        }
    });
})();
</script>
</body>
</html>
