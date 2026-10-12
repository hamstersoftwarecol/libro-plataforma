@php
    $appName = config('app.name', 'Libro');
@endphp
<!DOCTYPE html>
<html lang="es" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} · @yield('title') · {{ $appName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@500&family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,600;1,9..144,300&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Self-contained styles: error pages must render even without compiled assets. */
        :root { --night: #0f0d0b; --paper: #ebe2d3; --ember: #e8833a; --blood: #c0442b; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body.err { background: var(--night); color: var(--paper); font-family: Inter, ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
        .err-scene { position: relative; min-height: 100%; display: grid; place-items: center; padding: 2rem 1.25rem; overflow: hidden; }
        .err-sun { position: absolute; width: 46rem; height: 46rem; border-radius: 9999px; left: 50%; bottom: -26rem; transform: translateX(-50%); filter: blur(30px);
            background: radial-gradient(circle, rgba(255, 224, 160, .95) 0%, rgba(232, 131, 58, .7) 18%, rgba(192, 68, 43, .25) 45%, transparent 70%); animation: err-breathe 9s ease-in-out infinite; }
        .err-horizon { position: absolute; left: 0; right: 0; bottom: 9%; height: 1px; background: linear-gradient(90deg, transparent, rgba(240, 170, 90, .9) 50%, transparent); box-shadow: 0 0 40px 4px rgba(232, 131, 58, .45); }
        .err-vignette { position: absolute; inset: 0; pointer-events: none; background: radial-gradient(ellipse at center, transparent 40%, rgba(5, 4, 3, .9) 100%); }
        .err-grain { position: fixed; inset: -50%; pointer-events: none; opacity: .07; animation: err-grain .9s steps(6) infinite;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"); }
        .err-card { position: relative; z-index: 1; max-width: 40rem; text-align: center; animation: err-rise 1.2s cubic-bezier(.2, .7, .2, 1) both; }
        .err-brand { display: inline-block; margin-bottom: 2.5rem; font-family: Fraunces, Georgia, serif; font-weight: 600; font-size: 1.15rem; color: var(--paper); text-decoration: none; opacity: .85; }
        .err-code { font-family: Fraunces, Georgia, serif; font-weight: 600; font-size: clamp(6rem, 22vw, 11rem); line-height: .85; letter-spacing: -.04em; color: transparent; -webkit-text-stroke: 1.5px rgba(235, 226, 211, .35); margin: 0; }
        .err-eyebrow { margin: 1.75rem 0 0; font-size: .72rem; font-weight: 600; letter-spacing: .35em; text-transform: uppercase; color: var(--ember); }
        .err-title { margin: .9rem 0 0; font-family: Fraunces, Georgia, serif; font-weight: 300; font-size: clamp(2rem, 5vw, 3rem); line-height: 1.1; letter-spacing: -.02em; }
        .err-title em { font-style: italic; color: #f4b56d; }
        .err-text { margin: 1.25rem auto 0; max-width: 30rem; font-size: 1.05rem; line-height: 1.7; color: rgba(235, 226, 211, .68); }
        .err-quote { margin: 2.25rem 0 0; font-family: Caveat, cursive; font-size: 1.9rem; color: rgba(235, 226, 211, .85); }
        .err-actions { margin-top: 2.5rem; display: flex; flex-wrap: wrap; gap: .75rem; justify-content: center; }
        .err-btn { display: inline-flex; align-items: center; gap: .5rem; padding: .9rem 1.6rem; border-radius: 9999px; font-size: .95rem; font-weight: 600; text-decoration: none; cursor: pointer; border: 0; font-family: inherit; transition: transform .2s, box-shadow .2s, background .2s; }
        .err-btn-primary { color: #fff; background: linear-gradient(90deg, var(--ember), var(--blood)); box-shadow: 0 18px 50px -15px rgba(232, 131, 58, .8); }
        .err-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 22px 60px -12px rgba(232, 131, 58, .95); }
        .err-btn-ghost { color: rgba(235, 226, 211, .75); background: transparent; box-shadow: inset 0 0 0 1px rgba(235, 226, 211, .22); }
        .err-btn-ghost:hover { color: var(--paper); box-shadow: inset 0 0 0 1px rgba(235, 226, 211, .55); }
        .err-meta { margin-top: 2rem; font-size: .75rem; color: rgba(235, 226, 211, .35); }
        @keyframes err-breathe { 0%, 100% { transform: translateX(-50%) scale(1); opacity: .9; } 50% { transform: translateX(-50%) scale(1.08); opacity: 1; } }
        @keyframes err-grain { 0%, 100% { transform: translate(0, 0); } 25% { transform: translate(-4%, 3%); } 50% { transform: translate(3%, -5%); } 75% { transform: translate(-2%, 6%); } }
        @keyframes err-rise { from { opacity: 0; transform: translateY(24px); filter: blur(10px); } to { opacity: 1; transform: none; filter: none; } }
        @media (prefers-reduced-motion: reduce) { .err-sun, .err-grain, .err-card { animation: none; } }
    </style>
</head>
<body class="err">
    <main class="err-scene">
        <div class="err-sun" aria-hidden="true"></div>
        <div class="err-horizon" aria-hidden="true"></div>
        <div class="err-vignette" aria-hidden="true"></div>
        <div class="err-grain" aria-hidden="true"></div>

        <div class="err-card">
            <a href="{{ url('/') }}" class="err-brand notranslate" translate="no">{{ $appName }}</a>
            <p class="err-code" aria-hidden="true">{{ $code }}</p>
            <p class="err-eyebrow">@yield('eyebrow')</p>
            <h1 class="err-title">@yield('heading')</h1>
            <p class="err-text">@yield('message')</p>
            <p class="err-quote">@yield('quote', 'El fondo no era el final.')</p>

            <div class="err-actions">
                @hasSection('actions')
                    @yield('actions')
                @else
                    <a href="{{ url('/') }}" class="err-btn err-btn-primary">Volver al inicio</a>
                    <button type="button" class="err-btn err-btn-ghost" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')">Regresar</button>
                @endif
            </div>

            <p class="err-meta">Error {{ $code }}</p>
        </div>
    </main>
</body>
</html>
