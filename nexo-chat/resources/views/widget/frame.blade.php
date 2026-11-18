<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $config['title'] }}</title>
    <script>window.NEXO_WIDGET = @json($config);</script>
    @vite(['resources/js/widget/main.ts'])
</head>
<body>
    <div id="nexo-widget"></div>
</body>
</html>
