import { configureEcho, echo, echoIsConfigured } from '@laravel/echo-vue';
import type Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import type { ReverbClientConfig } from '@/types';

/**
 * Conexão Reverb da central de atendimento (autorização de canais via
 * /broadcasting/auth com a sessão do usuário). Reconexão automática é
 * feita pelo pusher-js.
 */
export function ensureEcho(config: ReverbClientConfig): Echo<'reverb'> {
    if (!echoIsConfigured()) {
        (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
        configureEcho({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    return echo<'reverb'>();
}
