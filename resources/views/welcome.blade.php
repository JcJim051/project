<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plataforma integral de gestion de proyectos de AIM</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        :root {
            --aim-bg: #f3faf6;
            --aim-surface: #ffffff;
            --aim-primary: #00445c;
            --aim-secondary: #00a86b;
            --aim-accent: #8fd400;
            --aim-text: #0f2c3a;
            --aim-muted: #58707c;
            --aim-border: #d7e8df;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Figtree', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            color: var(--aim-text);
            background:
                radial-gradient(circle at 8% 10%, rgba(143,212,0,.20), transparent 36%),
                radial-gradient(circle at 85% 0%, rgba(0,168,107,.18), transparent 40%),
                linear-gradient(180deg, #f6fdf9 0%, #eef8f2 50%, #f8fcfa 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1150px;
            margin: 0 auto;
            padding: 28px 20px;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(150deg, var(--aim-secondary), var(--aim-primary));
            box-shadow: 0 10px 20px rgba(0, 68, 92, .20);
        }

        .brand-title {
            margin: 0;
            font-size: 14px;
            line-height: 1.2;
            color: var(--aim-muted);
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .brand-sub {
            margin: 2px 0 0;
            font-weight: 700;
            font-size: 16px;
            color: var(--aim-primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: 1px solid transparent;
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s ease;
        }

        .btn-login {
            background: var(--aim-primary);
            color: #fff;
            box-shadow: 0 8px 20px rgba(0, 68, 92, .25);
        }

        .btn-login:hover { transform: translateY(-1px); }

        .hero {
            background: linear-gradient(135deg, rgba(255,255,255,.94), rgba(243,251,246,.94));
            border: 1px solid var(--aim-border);
            border-radius: 22px;
            box-shadow: 0 24px 56px rgba(7, 45, 55, 0.10);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.1fr .9fr;
        }

        .hero-copy {
            padding: 44px;
        }

        .kicker {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--aim-secondary);
            background: rgba(0,168,107,.10);
            border: 1px solid rgba(0,168,107,.25);
            padding: 7px 10px;
            border-radius: 999px;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: clamp(30px, 5.6vw, 54px);
            line-height: 1.03;
            color: var(--aim-primary);
            letter-spacing: -.02em;
        }

        h1 span { color: var(--aim-accent); }

        .lead {
            margin: 18px 0 0;
            font-size: 17px;
            line-height: 1.62;
            color: var(--aim-muted);
            max-width: 62ch;
        }

        .hero-actions {
            margin-top: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-main {
            background: linear-gradient(120deg, var(--aim-secondary), var(--aim-primary));
            color: #fff;
            box-shadow: 0 14px 30px rgba(0, 68, 92, .22);
        }

        .btn-ghost {
            border-color: var(--aim-border);
            color: var(--aim-primary);
            background: #fff;
        }

        .hero-visual {
            position: relative;
            min-height: 390px;
            background: linear-gradient(160deg, rgba(0,68,92,.06), rgba(143,212,0,.12));
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 24px;
        }

        .hero-visual img {
            width: min(420px, 100%);
            border-radius: 16px;
            border: 1px solid rgba(0,68,92,.12);
            box-shadow: 0 20px 45px rgba(0, 68, 92, .18);
        }

        .stats {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 12px;
        }

        .stat {
            background: var(--aim-surface);
            border: 1px solid var(--aim-border);
            border-radius: 14px;
            padding: 16px;
        }

        .stat strong {
            display: block;
            color: var(--aim-primary);
            font-size: 22px;
            line-height: 1.1;
        }

        .stat span {
            display: block;
            margin-top: 5px;
            font-size: 13px;
            color: var(--aim-muted);
        }

        footer {
            margin-top: 24px;
            text-align: center;
            color: #5f747d;
            font-size: 13px;
        }

        footer a {
            color: var(--aim-primary);
            font-weight: 700;
            text-decoration: none;
        }

        @media (max-width: 960px) {
            .hero { grid-template-columns: 1fr; }
            .hero-copy { padding: 28px 24px; }
            .hero-visual { min-height: 280px; }
            .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="nav">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true"></div>
                <div>
                    <p class="brand-title">AIM - Meta</p>
                    <p class="brand-sub">Plataforma de Gestión de Proyectos</p>
                </div>
            </div>
            <a href="{{ url('/panel/login') }}" class="btn btn-login">Ingresar</a>
        </header>

        <main class="hero" role="main">
            <section class="hero-copy">
                <span class="kicker">Direccion tecnica para la estructuracion de proyectos</span>
                <h1>Plataforma integral de gestion de proyectos de <span>AIM</span></h1>
                <p class="lead">
                    Bienvenido al ecosistema digital para planear, estructurar y hacer seguimiento de proyectos estratégicos del Meta.
                    Centraliza requisitos, checklist, evidencias, certificaciones y trazabilidad en un solo lugar.
                </p>

                <div class="hero-actions">
                    <a href="{{ url('/panel/login') }}" class="btn btn-main">Entrar a la plataforma</a>
                    <a href="https://www.aim-meta.gov.co" class="btn btn-ghost" target="_blank" rel="noopener">Sitio oficial AIM</a>
                </div>

            </section>

            <aside class="hero-visual" aria-label="Identidad visual institucional">
                <img src="{{ asset('img/logo.jpg') }}" alt="Identidad gráfica AIM Meta" loading="lazy">
            </aside>
        </main>

        <footer>
            Desarrollado por
            <a href="https://procesos.shipper.com.co" target="_blank" rel="noopener">Jonathan Jimenez</a>
        </footer>
    </div>
</body>
</html>
