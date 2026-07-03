<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Servicargo') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>window.APP_BASE_PATH = @json(rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?: '', '/'));</script>
    <script>
        // Aplica el tema guardado (persona + modo + accesibilidad) antes de pintar,
        // para que no haya parpadeo y también funcione en login / landing.
        (function () {
            try {
                var d = document.documentElement;
                var persona = localStorage.getItem('sc_persona') || 'navy';
                var modoPref = localStorage.getItem('sc_modo_pref') || 'auto';
                var h = new Date().getHours();
                var modo = modoPref === 'auto' ? ((h >= 6 && h < 18) ? 'light' : 'dark') : modoPref;
                d.setAttribute('data-persona', persona);
                d.setAttribute('data-mode', modo);
                d.setAttribute('data-font', localStorage.getItem('sc_fuente') || 'md');
                d.setAttribute('data-contrast', localStorage.getItem('sc_contraste') || 'normal');
            } catch (e) { /* si localStorage no está disponible, se usa el tema por defecto */ }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
