import { echo, echoIsConfigured } from '@laravel/echo-vue';

/**
 * Cliente JSON para ações da central (fora do ciclo de visitas do Inertia).
 * Envia o token CSRF (cookie XSRF-TOKEN) e o X-Socket-ID para que o
 * broadcast `toOthers()` não devolva o evento ao próprio remetente.
 */
export class HttpError extends Error {
    constructor(
        public status: number,
        public data: { message?: string; errors?: Record<string, string[]> },
    ) {
        super(data.message ?? `Erro ${status}`);
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function api<T>(method: 'GET' | 'POST' | 'PUT' | 'DELETE', url: string, body?: unknown): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
    };

    const sid = echoIsConfigured() ? echo().socketId() : undefined;
    if (sid) headers['X-Socket-ID'] = sid;

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const data = response.status === 204 ? {} : await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new HttpError(response.status, data);
    }

    return data as T;
}
