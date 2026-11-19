import type { Message } from '@/types';

import type { ConversationPayload, SessionPayload, WidgetConfig } from './types';

/**
 * Cliente HTTP do widget. Autenticação por token de sessão do visitante
 * (Authorization: Bearer) — sem cookies, sem credenciais administrativas.
 */
export class WidgetApi {
    token: string | null;
    socketId: () => string | undefined = () => undefined;

    constructor(private config: WidgetConfig) {
        this.token = this.readToken();
    }

    private storageKey(): string {
        return `nexo-chat:${this.config.key}:token`;
    }

    private readToken(): string | null {
        try {
            return localStorage.getItem(this.storageKey());
        } catch {
            return null;
        }
    }

    private saveToken(token: string): void {
        this.token = token;
        try {
            localStorage.setItem(this.storageKey(), token);
        } catch {
            /* armazenamento bloqueado: sessão dura apenas enquanto a página estiver aberta */
        }
    }

    private async request<T>(method: 'GET' | 'POST', path: string, body?: unknown): Promise<T> {
        const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'application/json' };
        if (this.token) headers.Authorization = `Bearer ${this.token}`;
        const sid = this.socketId();
        if (sid) headers['X-Socket-ID'] = sid;

        const res = await fetch(this.config.api_base + path, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            credentials: 'omit',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const err = new Error((data as { message?: string }).message ?? `Erro ${res.status}`) as Error & {
                status: number;
                errors?: Record<string, string[]>;
            };
            err.status = res.status;
            err.errors = (data as { errors?: Record<string, string[]> }).errors;
            throw err;
        }
        return data as T;
    }

    async startSession(pageUrl: string | null): Promise<SessionPayload> {
        const data = await this.request<SessionPayload>('POST', `/${this.config.key}/sessions`, {
            host: this.config.host,
            page_url: pageUrl,
            token: this.token,
        });
        this.saveToken(data.token);
        return data;
    }

    conversation(after = 0): Promise<ConversationPayload> {
        return this.request('GET', `/conversation${after ? `?after=${after}` : ''}`);
    }

    startConversation(input: { name?: string; email?: string; phone?: string; message: string; client_message_id: string }): Promise<ConversationPayload> {
        return this.request('POST', '/conversation', input);
    }

    sendMessage(body: string, clientMessageId: string): Promise<{ message: Message }> {
        return this.request('POST', '/conversation/messages', { body, client_message_id: clientMessageId });
    }

    typing(typing: boolean): Promise<unknown> {
        return this.request('POST', '/conversation/typing', { typing });
    }

    markRead(): Promise<unknown> {
        return this.request('POST', '/conversation/read');
    }

    /** Autorização de canal privado para o Reverb (pusher-js). */
    async authorizeChannel(channelName: string, socketId: string): Promise<{ auth: string }> {
        return this.request('POST', '/broadcasting/auth', { channel_name: channelName, socket_id: socketId });
    }
}
