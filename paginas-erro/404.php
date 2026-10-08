<?php
// errors/404.php
// Página de erro 404 personalizada, com a identidade visual do sistema.
require_once __DIR__ . '/../config/session.php';

$destino_label = 'Ir para o login';
$destino_url   = url('login');

if (isLoggedIn()) {
    $destino_label = 'Ir para o Dashboard';
    $destino_url   = url('dashboard');
}

// Garante que o cabeçalho de resposta HTTP continua a ser 404,
// mesmo quando esta página é servida via ErrorDocument.
if (!headers_sent()) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada (404) - I.P.F.V.A Calemba 2</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --blue-50: #e8f2fe;
            --blue-100: #d1e5fd;
            --blue-200: #a3ccfb;
            --blue-300: #75b2f9;
            --blue-400: #4799f7;
            --blue-500: #1a7ff5;
            --blue-600: #0a6bd4;
            --blue-700: #0752a8;
            --blue-800: #053a7c;
            --blue-900: #032150;
            --gold-100: #faf0d7;
            --gold-300: #e8c96a;
            --gold-500: #d4af37;
            --gold-700: #b8941e;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --danger: #ef4444;
            --radius: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Montserrat', sans-serif;
            color: var(--gray-800);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            background: linear-gradient(135deg, var(--blue-50), var(--white));
            -webkit-font-smoothing: antialiased;
        }

        .error-shell {
            width: 100%;
            max-width: 620px;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            background: var(--white);
        }

        .error-hero {
            position: relative;
            background: linear-gradient(180deg, var(--blue-600), var(--blue-800));
            color: var(--white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 44px 32px 36px;
            overflow: hidden;
        }

        .error-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: repeating-radial-gradient(circle at 20% 100%, rgba(255,255,255,0.05) 0 2px, transparent 2px 40px);
            opacity: .5;
        }

        .hero-emblem {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: linear-gradient(160deg, var(--white), var(--blue-100));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
            box-shadow: 0 12px 30px rgba(0,0,0,0.25), inset 0 0 0 3px var(--gold-500);
            color: var(--blue-700);
        }

        .code-404 {
            position: relative;
            z-index: 1;
            font-size: 56px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: .04em;
            background: linear-gradient(90deg, #dfeaff, #ffffff 45%, #dfeaff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-line {
            position: relative;
            z-index: 1;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .18em;
            color: var(--gold-300);
            text-transform: uppercase;
            margin-top: 6px;
        }

        .error-panel {
            padding: 40px 36px 36px;
            text-align: center;
        }

        .error-panel h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--blue-800);
            margin-bottom: 10px;
        }

        .error-panel p {
            font-size: 14px;
            color: var(--gray-500);
            line-height: 1.6;
            margin-bottom: 26px;
        }

        .error-hr {
            width: 60px;
            height: 3px;
            background: var(--gold-500);
            margin: 0 auto 20px;
            border-radius: 2px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            background: linear-gradient(90deg, var(--blue-600), var(--blue-400));
            color: var(--white);
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .02em;
            text-decoration: none;
            box-shadow: 0 10px 20px -6px rgba(10, 107, 212, 0.4);
            transition: transform .15s, box-shadow .15s;
            font-family: 'Montserrat', sans-serif;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 26px -6px rgba(10, 107, 212, 0.5);
        }

        .error-footer {
            text-align: center;
            color: var(--gray-400);
            font-size: 11.5px;
            margin-top: 26px;
            line-height: 1.6;
        }

        @media (max-width: 480px) {
            .error-hero { padding: 36px 24px 28px; }
            .error-panel { padding: 32px 24px 28px; }
            .code-404 { font-size: 44px; }
        }
    </style>
</head>
<body>
    <div class="error-shell">
        <div class="error-hero">
            <div class="hero-emblem">
                <i class="fas fa-bible"></i>
            </div>
            <div class="code-404">404</div>
            <div class="hero-line">I.P.F.V.A · CALEMBA 2</div>
        </div>
        <div class="error-panel">
            <h1><i class="fas fa-map-signs"></i> Página não encontrada</h1>
            <div class="error-hr"></div>
            <p>
                A página que procura não existe, foi movida ou o endereço está incorreto.<br>
                Verifique o link ou volte para um local seguro do sistema.
            </p>
            <a href="<?= htmlspecialchars($destino_url) ?>" class="btn-primary">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($destino_label) ?>
            </a>
            <div class="error-footer">
                <i class="far fa-copyright"></i> 2026 Igreja Pentecostal Fonte da Vida Angola — EPFVA Calemba 2<br>
                Todos os direitos reservados.
            </div>
        </div>
    </div>
</body>
</html>
