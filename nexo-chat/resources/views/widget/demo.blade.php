<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página de teste do widget — NEXO Chat</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 48px auto; padding: 0 16px; color: #222; line-height: 1.5 }
        code, input { font-family: ui-monospace, monospace; }
        input { width: 100%; padding: 8px; box-sizing: border-box; }
        button { margin-top: 8px; padding: 8px 14px; }
    </style>
</head>
<body>
    <h1>Página de teste do widget</h1>
    <p>Simula um site cliente. Informe o <code>data-widget-id</code> do widget (Painel → Sites).
       O domínio <code>{{ request()->getHttpHost() }}</code> precisa estar entre os domínios autorizados do site.</p>
    <input id="wid" placeholder="wgt_..." value="{{ request('widget') }}">
    <button onclick="location.search='?widget='+encodeURIComponent(document.getElementById('wid').value)">Carregar widget</button>

    @if (request('widget'))
        <script src="{{ url('/widget.js') }}" data-widget-id="{{ request('widget') }}" async></script>
    @endif
</body>
</html>
