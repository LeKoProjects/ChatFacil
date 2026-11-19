<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        // Aplica o tema antes da renderização para evitar "flash".
        (function () {
            try {
                var t = localStorage.getItem('nexo-theme');
                var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    <title inertia>{{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <meta name="theme-color" content="#0E2A63">
    @vite(['resources/js/app.ts'])
    <x-inertia::head />
</head>
<body class="font-sans antialiased">
    <x-inertia::app />
</body>
</html>
