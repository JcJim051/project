<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Archivo' }}</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            max-width: 640px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }
        .eyebrow {
            display: inline-block;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #ecfccb;
            color: #3f6212;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
        }
        h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        p {
            margin: 0 0 12px 0;
            line-height: 1.55;
            color: #475569;
        }
        .meta {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .meta strong {
            color: #0f172a;
        }
        .actions {
            margin-top: 22px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: #8fd400;
            color: #103126;
            border-color: #7bb800;
        }
        .btn-secondary {
            background: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="eyebrow">Portal AIM</div>
            <h1>{{ $title ?? 'Archivo' }}</h1>
            <p>{{ $message ?? 'No fue posible mostrar este archivo.' }}</p>

            @if(!empty($fileName) || !empty($projectName))
                <div class="meta">
                    @if(!empty($fileName))
                        <p><strong>Archivo:</strong> {{ $fileName }}</p>
                    @endif
                    @if(!empty($projectName))
                        <p><strong>Proyecto:</strong> {{ $projectName }}</p>
                    @endif
                </div>
            @endif

            <div class="actions">
                @if(!empty($downloadUrl))
                    <a href="{{ $downloadUrl }}" class="btn btn-primary">Descargar archivo</a>
                @endif
                <a href="javascript:window.close();" class="btn btn-secondary">Cerrar</a>
            </div>
        </div>
    </div>
</body>
</html>
