<?php

return [
    /*
    | Endereço público do Reverb usado pelos navegadores (widget e central).
    | Em produção, normalmente o mesmo host da aplicação via Nginx (porta 443).
    */
    'reverb_client' => [
        'host' => env('VITE_REVERB_HOST', 'localhost'),
        'port' => (int) env('VITE_REVERB_PORT', 443),
        'scheme' => env('VITE_REVERB_SCHEME', 'https'),
    ],

    'widget' => [
        'session_ttl_days' => (int) env('WIDGET_SESSION_TTL_DAYS', 30),
        'max_message_length' => 4000,
    ],
];
