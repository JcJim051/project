<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Caracterización de equipo</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: linear-gradient(180deg, #eff6ff 0%, #fefce8 100%); color: #0f172a; }
        .wrap { max-width: 820px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: #fff; border: 1px solid #dbe4f0; border-radius: 24px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .hero { padding: 24px; background: radial-gradient(circle at top right, #bfdbfe 0%, #f8fafc 45%, #ffffff 100%); border-bottom: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .badge-open { background: #dcfce7; color: #166534; }
        .badge-off { background: #e5e7eb; color: #374151; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .body { padding: 24px; }
        label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #475569; margin-bottom: 6px; }
        input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 14px; padding: 12px 14px; font-size: 15px; box-sizing: border-box; }
        textarea { min-height: 110px; resize: vertical; }
        .grid { display: grid; gap: 16px; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
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
                {{ match($status) { 'open' => 'Campaña abierta', 'scheduled' => 'Programada', 'expired' => 'Vencida', 'inactive' => 'Apagada', 'closed' => 'Cerrada', default => 'No disponible' } }}
            </span>
            <h1 style="margin:14px 0 6px;font-size:28px;">{{ $campaign->title }}</h1>
            <p style="margin:0;color:#475569;">Selecciona tu rol y diligencia tu información para quedar pendiente de revisión.</p>
        </div>
        <div class="body">
            @if (session('status'))
                <div class="alert success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert error">{{ $errors->first() }}</div>
            @endif
            @if (!$canSubmit)
                <div class="alert error">Este enlace no se encuentra disponible para nuevas caracterizaciones.</div>
            @endif

            <div class="actions" style="margin-top:0;margin-bottom:16px;">
                <a href="{{ $campaignUrl }}" class="link-button secondary">Volver a la campaña</a>
            </div>

            <form method="POST" action="{{ route('team-onboarding.submit', $campaign->public_token) }}" id="team-onboarding-form">
                @csrf
                <div class="grid">
                    <div>
                        <label>Rol</label>
                        <select name="requested_role" id="requested_role" @disabled(!$canSubmit) required>
                            <option value="">Selecciona una opción</option>
                            <option value="formulador" @selected(old('requested_role') === 'formulador')>Formulador</option>
                            <option value="estructurador" @selected(old('requested_role') === 'estructurador')>Estructurador</option>
                            <option value="especialista" @selected(old('requested_role') === 'especialista')>Especialista</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-2" style="margin-top:16px;">
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
                    <label>Correo</label>
                    <input type="email" name="email" value="{{ old('email') }}" @disabled(!$canSubmit) required>
                </div>

                <div id="specialist-fields" style="display:none;">
                    <div style="margin-top:16px;">
                        <label>Especialidad</label>
                        <input type="text" name="specialty" value="{{ old('specialty') }}" @disabled(!$canSubmit)>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="primary" @disabled(!$canSubmit)>Enviar caracterización</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const roleSelect = document.getElementById('requested_role');
    const specialistFields = document.getElementById('specialist-fields');
    const specialtyInput = document.querySelector('input[name="specialty"]');

    const syncVisibility = () => {
        const isSpecialist = roleSelect?.value === 'especialista';
        if (specialistFields) specialistFields.style.display = isSpecialist ? 'block' : 'none';
        if (specialtyInput) specialtyInput.required = isSpecialist;
    };

    roleSelect?.addEventListener('change', syncVisibility);
    syncVisibility();
})();
</script>
</body>
</html>
