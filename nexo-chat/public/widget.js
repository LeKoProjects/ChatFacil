/*!
 * NEXO Chat — loader do widget.
 * Uso: <script src="https://SEU_HOST/widget.js" data-widget-id="wgt_..." async></script>
 *
 * Cria um iframe isolado (sem conflitos de CSS/JS com o site) e ajusta seu
 * tamanho conforme o widget abre/fecha. Não contém credenciais.
 */
(function () {
    'use strict';

    if (window.__NEXO_CHAT_LOADED__) return;
    window.__NEXO_CHAT_LOADED__ = true;

    var script = document.currentScript || document.querySelector('script[data-widget-id]');
    if (!script) return;

    var widgetId = script.getAttribute('data-widget-id');
    if (!widgetId || !/^wgt_[a-z0-9]{26}$/.test(widgetId)) {
        console.warn('[NEXO Chat] data-widget-id ausente ou inválido.');
        return;
    }

    var origin = new URL(script.src, window.location.href).origin;
    var CLOSED = { w: 96, h: 96 };
    var OPEN = { w: 400, h: 660 };

    function mount() {
        var iframe = document.createElement('iframe');
        iframe.src = origin + '/widget/frame/' + widgetId +
            '?host=' + encodeURIComponent(window.location.host) +
            '&page=' + encodeURIComponent(window.location.href);
        iframe.title = 'Chat de atendimento';
        iframe.setAttribute('allow', 'autoplay');

        var s = iframe.style;
        s.position = 'fixed';
        s.bottom = '0';
        s.right = '0';
        s.width = CLOSED.w + 'px';
        s.height = CLOSED.h + 'px';
        s.maxHeight = '100%';
        s.maxWidth = '100%';
        s.border = '0';
        s.background = 'transparent';
        s.colorScheme = 'normal';
        s.zIndex = '2147483000';

        window.addEventListener('message', function (event) {
            if (event.origin !== origin || event.source !== iframe.contentWindow) return;
            var data = event.data || {};
            if (data.type !== 'nexo:layout') return;

            if (data.position === 'left') {
                s.left = '0';
                s.right = 'auto';
            }

            var mobile = window.innerWidth < 480;
            if (data.open) {
                s.width = mobile ? '100%' : OPEN.w + 'px';
                s.height = mobile ? '100%' : OPEN.h + 'px';
            } else {
                s.width = CLOSED.w + 'px';
                s.height = CLOSED.h + 'px';
            }
        });

        document.body.appendChild(iframe);
    }

    // Monta após o carregamento completo do site: o widget nunca atrasa o
    // evento "load" da página hospedeira.
    if (document.readyState === 'complete') {
        mount();
    } else {
        window.addEventListener('load', mount, { once: true });
    }
})();
