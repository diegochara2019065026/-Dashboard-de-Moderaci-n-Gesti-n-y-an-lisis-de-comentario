<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tech Hub Forum – Comparte tus ideas y participa en la comunidad tecnológica">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Tech Hub Forum' }} | Aegis Filter</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        /* ════════════════════════════════════════════
           Aegis Filter – Design System
           Tech Hub Forum | Formulario de Comentarios
        ════════════════════════════════════════════ */

        :root {
            --color-bg:          #0a0e1a;
            --color-bg-card:     #111827;
            --color-bg-input:    #1a2235;
            --color-border:      #1f2d45;
            --color-border-focus:#6366f1;
            --color-primary:     #6366f1;
            --color-primary-dark:#4f46e5;
            --color-accent:      #06b6d4;
            --color-success:     #10b981;
            --color-warning:     #f59e0b;
            --color-error:       #ef4444;
            --color-text:        #f1f5f9;
            --color-text-muted:  #64748b;
            --color-text-sub:    #94a3b8;
            --font-sans:         'Inter', system-ui, sans-serif;
            --radius-sm:         8px;
            --radius-md:         12px;
            --radius-lg:         20px;
            --shadow-glow:       0 0 40px rgba(99,102,241,0.15);
            --transition:        all 0.25s cubic-bezier(0.4,0,0.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-sans);
            background-color: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image:
                radial-gradient(ellipse at 20% 10%, rgba(99,102,241,0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 90%, rgba(6,182,212,0.06) 0%, transparent 60%);
        }

        /* ─── Header ─────────────────────────────────── */
        .header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 1rem;
            backdrop-filter: blur(10px);
            background: rgba(17,24,39,0.8);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--color-text);
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--color-primary), var(--color-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-sub {
            font-size: 0.7rem;
            color: var(--color-text-muted);
            font-weight: 400;
            display: block;
            margin-top: -4px;
        }

        .header-nav {
            margin-left: auto;
            display: flex;
            gap: 1rem;
        }

        .nav-link {
            color: var(--color-text-sub);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .nav-link:hover {
            color: var(--color-text);
            background: rgba(99,102,241,0.1);
        }

        /* ─── Main Layout ────────────────────────────── */
        .main-container {
            flex: 1;
            max-width: 720px;
            width: 100%;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }

        /* ─── Hero Section ───────────────────────────── */
        .hero {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.3);
            color: var(--color-primary);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            margin-bottom: 1.2rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .hero-title {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff 0%, var(--color-text-sub) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            color: var(--color-text-muted);
            font-size: 1rem;
            line-height: 1.6;
            max-width: 520px;
            margin: 0 auto;
        }

        /* ─── Alert Messages ─────────────────────────── */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            border: 1px solid;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: rgba(16,185,129,0.08);
            border-color: rgba(16,185,129,0.3);
            color: #34d399;
        }

        .alert-warning {
            background: rgba(245,158,11,0.08);
            border-color: rgba(245,158,11,0.3);
            color: #fbbf24;
        }

        .alert-error {
            background: rgba(239,68,68,0.08);
            border-color: rgba(239,68,68,0.3);
            color: #f87171;
        }

        /* ─── Card ───────────────────────────────────── */
        .card {
            background: var(--color-bg-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-glow);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg,
                transparent,
                rgba(99,102,241,0.5),
                rgba(6,182,212,0.5),
                transparent);
        }

        /* ─── Form Elements ──────────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-text-sub);
            letter-spacing: 0.02em;
        }

        .form-label .required {
            color: var(--color-error);
            margin-left: 2px;
        }

        .form-input,
        .form-textarea {
            background: var(--color-bg-input);
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            color: var(--color-text);
            font-family: var(--font-sans);
            font-size: 0.9375rem;
            padding: 0.75rem 1rem;
            transition: var(--transition);
            width: 100%;
            outline: none;
        }

        .form-input:focus,
        .form-textarea:focus {
            border-color: var(--color-border-focus);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: var(--color-text-muted);
        }

        .form-input.is-invalid,
        .form-textarea.is-invalid {
            border-color: var(--color-error);
        }

        .form-textarea {
            resize: vertical;
            min-height: 160px;
            line-height: 1.6;
        }

        .form-error {
            font-size: 0.8rem;
            color: var(--color-error);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .char-counter {
            font-size: 0.75rem;
            color: var(--color-text-muted);
            text-align: right;
            margin-top: 0.25rem;
            transition: var(--transition);
        }

        .char-counter.warning { color: var(--color-warning); }
        .char-counter.danger  { color: var(--color-error); }

        /* ─── Shield Info ────────────────────────────── */
        .shield-info {
            background: rgba(99,102,241,0.06);
            border: 1px solid rgba(99,102,241,0.15);
            border-radius: var(--radius-sm);
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.82rem;
            color: var(--color-text-muted);
        }

        .shield-info .icon {
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* ─── Submit Button ──────────────────────────── */
        .btn-submit {
            width: 100%;
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-family: var(--font-sans);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(99,102,241,0.4);
        }

        .btn-submit:hover::after { left: 100%; }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* ─── Footer ─────────────────────────────────── */
        .footer {
            text-align: center;
            padding: 1.5rem;
            border-top: 1px solid var(--color-border);
            color: var(--color-text-muted);
            font-size: 0.8rem;
        }

        .footer a { color: var(--color-primary); text-decoration: none; }

        /* ─── Responsive ─────────────────────────────── */
        @media (max-width: 580px) {
            .form-grid { grid-template-columns: 1fr; }
            .card { padding: 1.5rem; }
            .header { padding: 1rem; }
        }
    </style>
</head>
<body>

    {{-- ─── Header ─────────────────────────────────────────── --}}
    <header class="header" role="banner">
        <a href="{{ route('comments.form') }}" class="header-logo" aria-label="Tech Hub Forum inicio">
            <div class="logo-icon" aria-hidden="true">🛡️</div>
            <div>
                <span class="logo-text">Aegis Filter</span>
                <span class="logo-sub">Tech Hub Forum</span>
            </div>
        </a>
        <nav class="header-nav" role="navigation" aria-label="Navegación principal">
            <a href="{{ route('comments.form') }}" class="nav-link">📝 Comentar</a>
            <a href="{{ route('dashboard') }}" class="nav-link">📊 Dashboard</a>
        </nav>
    </header>

    {{-- ─── Main Content ────────────────────────────────────── --}}
    <main class="main-container" role="main">

        {{-- Hero Section --}}
        <section class="hero" aria-labelledby="form-title">
            <div class="hero-badge" aria-label="Protegido por Aegis Filter">
                🛡️ Protegido por Aegis Filter
            </div>
            <h1 class="hero-title" id="form-title">
                Comparte tu opinión<br>en Tech Hub Forum
            </h1>
            <p class="hero-description">
                Participa en nuestra comunidad tecnológica. Tu mensaje será analizado
                por nuestro sistema antispam <strong>Aegis Filter</strong> para mantener
                un espacio seguro y de calidad.
            </p>
        </section>

        {{-- Mensajes de sesión --}}
        @if(session('success'))
            <div class="alert alert-success" role="alert" aria-live="polite">
                <span aria-hidden="true">✅</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning" role="alert" aria-live="polite">
                <span aria-hidden="true">⚠️</span>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error" role="alert" aria-live="assertive">
                <span aria-hidden="true">❌</span>
                <div>
                    <strong>Por favor corrige los siguientes errores:</strong>
                    <ul style="margin-top:0.5rem; padding-left:1.2rem;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Formulario Principal --}}
        <div class="card">

            {{-- Info del filtro antispam --}}
            <div class="shield-info" role="note">
                <span class="icon" aria-hidden="true">🔍</span>
                <span>
                    Aegis Filter analiza tu mensaje en tiempo real para detectar
                    spam, palabras prohibidas y exceso de enlaces.
                </span>
            </div>

            <form
                id="comment-form"
                action="{{ route('comments.store') }}"
                method="POST"
                novalidate
                aria-label="Formulario de comentario"
            >
                @csrf

                <div class="form-grid">

                    {{-- Campo: Autor --}}
                    <div class="form-group" id="group-author">
                        <label class="form-label" for="author">
                            Nombre <span class="required" aria-label="requerido">*</span>
                        </label>
                        <input
                            type="text"
                            id="author"
                            name="author"
                            class="form-input {{ $errors->has('author') ? 'is-invalid' : '' }}"
                            placeholder="Ej. Ana García"
                            value="{{ old('author') }}"
                            required
                            minlength="2"
                            maxlength="100"
                            autocomplete="name"
                            aria-describedby="{{ $errors->has('author') ? 'error-author' : '' }}"
                            aria-invalid="{{ $errors->has('author') ? 'true' : 'false' }}"
                        >
                        @error('author')
                            <span class="form-error" id="error-author" role="alert">
                                ⚠ {{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- Campo: Email --}}
                    <div class="form-group" id="group-email">
                        <label class="form-label" for="email">
                            Correo electrónico
                            <span style="color: var(--color-text-muted); font-weight:400;">(opcional)</span>
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                            placeholder="correo@ejemplo.com"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            aria-describedby="{{ $errors->has('email') ? 'error-email' : '' }}"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        >
                        @error('email')
                            <span class="form-error" id="error-email" role="alert">
                                ⚠ {{ $message }}
                            </span>
                        @enderror
                    </div>

                    {{-- Campo: Contenido --}}
                    <div class="form-group full-width" id="group-content">
                        <label class="form-label" for="content">
                            Comentario <span class="required" aria-label="requerido">*</span>
                        </label>
                        <textarea
                            id="content"
                            name="content"
                            class="form-textarea {{ $errors->has('content') ? 'is-invalid' : '' }}"
                            placeholder="Escribe tu comentario aquí. Ten en cuenta que el sistema Aegis Filter analizará tu mensaje automáticamente..."
                            required
                            minlength="10"
                            maxlength="2000"
                            aria-describedby="content-counter {{ $errors->has('content') ? 'error-content' : '' }}"
                            aria-invalid="{{ $errors->has('content') ? 'true' : 'false' }}"
                        >{{ old('content') }}</textarea>
                        <div id="content-counter" class="char-counter" aria-live="polite" aria-atomic="true">
                            0 / 2000 caracteres
                        </div>
                        @error('content')
                            <span class="form-error" id="error-content" role="alert">
                                ⚠ {{ $message }}
                            </span>
                        @enderror
                    </div>

                </div>{{-- /form-grid --}}

                <button
                    type="submit"
                    id="btn-submit-comment"
                    class="btn-submit"
                    aria-label="Enviar comentario al foro Tech Hub"
                >
                    🚀 Enviar Comentario
                </button>

            </form>
        </div>

    </main>

    {{-- ─── Footer ──────────────────────────────────────────── --}}
    <footer class="footer" role="contentinfo">
        <p>
            Protegido por <strong>Aegis Filter</strong> &middot;
            <a href="{{ route('dashboard') }}">Panel de Administración</a> &middot;
            Curso SI784 – Calidad y Pruebas de Software &copy; {{ date('Y') }}
        </p>
    </footer>

    <script>
        // ─── Contador de caracteres en tiempo real ─────────────
        const textarea   = document.getElementById('content');
        const counter    = document.getElementById('content-counter');
        const btnSubmit  = document.getElementById('btn-submit-comment');
        const MAX_LENGTH = 2000;

        function updateCounter() {
            const length = textarea.value.length;
            counter.textContent = `${length} / ${MAX_LENGTH} caracteres`;
            counter.classList.remove('warning', 'danger');

            if (length > MAX_LENGTH * 0.9) {
                counter.classList.add('danger');
            } else if (length > MAX_LENGTH * 0.75) {
                counter.classList.add('warning');
            }
        }

        textarea.addEventListener('input', updateCounter);

        // ─── Prevenir doble submit ─────────────────────────────
        const form = document.getElementById('comment-form');
        form.addEventListener('submit', function () {
            btnSubmit.disabled = true;
            btnSubmit.textContent = '⏳ Enviando...';
        });

        // Inicializar contador si hay valor previo (old input)
        updateCounter();
    </script>
</body>
</html>
