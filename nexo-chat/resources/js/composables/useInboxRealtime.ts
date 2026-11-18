import { onBeforeUnmount, ref, watch, type Ref } from 'vue';

import { ensureEcho } from '@/lib/echo';
import type { ConversationSummary, Message, ReverbClientConfig } from '@/types';

interface Handlers {
    onMessage: (message: Message, conversation: ConversationSummary) => void;
    onConversation: (conversation: ConversationSummary, reason: string) => void;
    onTyping: (payload: { conversation_id: string; sender_type: string; name: string; typing: boolean }) => void;
    onReconnected: () => void;
}

/**
 * Assinaturas Reverb da central:
 *  - company.{id}.inbox  → novas mensagens e mudanças de estado (todas as filas)
 *  - conversation.{uuid} → indicador de digitação da conversa aberta
 */
export function useInboxRealtime(config: ReverbClientConfig, companyId: number, selectedId: Ref<string | null>, handlers: Handlers) {
    const echo = ensureEcho(config);
    const connected = ref(false);
    let wasDisconnected = false;

    const inbox = echo.private(`company.${companyId}.inbox`);
    inbox.listen('.message.created', (e: { message: Message; conversation: ConversationSummary }) => handlers.onMessage(e.message, e.conversation));
    inbox.listen('.conversation.updated', (e: { conversation: ConversationSummary; reason: string }) => handlers.onConversation(e.conversation, e.reason));

    const pusher = (echo.connector as unknown as { pusher: { connection: { bind: (ev: string, cb: (s: { current: string }) => void) => void; state: string } } }).pusher;
    connected.value = pusher.connection.state === 'connected';
    pusher.connection.bind('state_change', (states) => {
        connected.value = states.current === 'connected';
        if (states.current === 'connected' && wasDisconnected) {
            wasDisconnected = false;
            handlers.onReconnected(); // recupera o que chegou durante a queda
        }
        if (states.current === 'unavailable' || states.current === 'connecting' || states.current === 'disconnected') {
            wasDisconnected = true;
        }
    });

    let current: string | null = null;
    watch(
        selectedId,
        (id) => {
            if (current) echo.leave(`conversation.${current}`);
            current = id;
            if (id) {
                echo.private(`conversation.${id}`).listen('.typing', handlers.onTyping);
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        echo.leave(`company.${companyId}.inbox`);
        if (current) echo.leave(`conversation.${current}`);
    });

    return { connected };
}
