<script setup lang="ts">
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import NexoMark from '@/components/brand/NexoMark.vue';
import { formatTime, uuid } from '@/lib/utils';
import type { BotOption, Message } from '@/types';

import { WidgetApi } from './api';
import type { WidgetConfig, WidgetConversation } from './types';

const props = defineProps<{ config: WidgetConfig }>();
const cfg = props.config;
const api = new WidgetApi(cfg);

const open = ref(false);
const loading = ref(true);
const fatal = ref<string | null>(null);
const conversation = ref<WidgetConversation | null>(null);
const messages = ref<Message[]>([]);
const unread = ref(0);
const agentTyping = ref<string | null>(null);
const connected = ref(false);
const draft = ref('');
const sending = ref(false);
const formError = ref<string | null>(null);
const form = reactive({ name: '', email: '', phone: '', message: '' });

const isClosed = computed(() => conversation.value?.status === 'closed');
const showForm = computed(() => !conversation.value || (isClosed.value && startingNew.value));
const startingNew = ref(false);
const lastSeq = computed(() => messages.value.reduce((max, m) => Math.max(max, m.seq || 0), 0));
const withBot = computed(() => conversation.value?.status === 'bot');
const hasBotMessages = computed(() => messages.value.some((m) => m.sender_type === 'bot'));
/** Só a última mensagem do bot mostra botões, e só enquanto a conversa está com ele. */
const optionsMessageId = computed(() => {
    if (!withBot.value) return null;
    const last = [...messages.value].reverse().find((m) => m.sender_type !== 'system');
    return last?.sender_type === 'bot' && last.options?.length ? last.id : null;
});
const choosing = ref(false);
const chooseError = ref<string | null>(null);

// ---------- Layout no site hospedeiro ----------
function postLayout() {
    window.parent.postMessage({ type: 'nexo:layout', open: open.value, position: cfg.position }, '*');
}
watch(open, (v) => {
    postLayout();
    if (v) {
        unread.value = 0;
        if (conversation.value) api.markRead().catch(() => {});
        scrollToBottom();
    }
});

// ---------- Mensagens ----------
function merge(incoming: Message[]) {
    const map = new Map<string, Message>();
    for (const m of messages.value) map.set(m.client_message_id ?? m.id, m);
    for (const m of incoming) {
        const key = m.client_message_id ?? m.id;
        map.set(key, { ...map.get(key), ...m, pending: false, failed: false });
    }
    messages.value = [...map.values()].sort((a, b) => (a.seq || Number.MAX_SAFE_INTEGER) - (b.seq || Number.MAX_SAFE_INTEGER));
}

const scroller = ref<HTMLElement | null>(null);
function scrollToBottom() {
    nextTick(() => scroller.value?.scrollTo({ top: scroller.value.scrollHeight }));
}

function beep() {
    if (!cfg.sound_enabled) return;
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch {
        /* áudio indisponível */
    }
}

function onIncoming(m: Message) {
    merge([m]);
    if (m.sender_type === 'agent' || m.sender_type === 'bot') {
        agentTyping.value = null;
        if (!open.value || document.hidden) {
            unread.value++;
            beep();
        } else {
            api.markRead().catch(() => {});
        }
    }
    scrollToBottom();
}

// ---------- Tempo real (Reverb) ----------
let echo: Echo<'reverb'> | null = null;
let subscribed: string | null = null;
let wasDisconnected = false;

function connect() {
    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
    echo = new Echo({
        broadcaster: 'reverb',
        key: cfg.reverb.key,
        wsHost: cfg.reverb.host,
        wsPort: cfg.reverb.port,
        wssPort: cfg.reverb.port,
        forceTLS: cfg.reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel: { name: string }) => ({
            authorize: (socketId: string, callback: (error: Error | null, data: { auth: string } | null) => void) => {
                api.authorizeChannel(channel.name, socketId)
                    .then((data) => callback(null, data))
                    .catch((e: Error) => callback(e, null));
            },
        }),
    });
    api.socketId = () => echo?.socketId();

    const pusher = (echo.connector as unknown as { pusher: { connection: { bind: (e: string, cb: (s: { current: string }) => void) => void } } }).pusher;
    pusher.connection.bind('state_change', (s) => {
        connected.value = s.current === 'connected';
        if (s.current === 'connected' && wasDisconnected) {
            wasDisconnected = false;
            recover();
        } else if (['unavailable', 'connecting', 'disconnected'].includes(s.current)) {
            wasDisconnected = true;
        }
    });
}

function subscribe(conv: WidgetConversation | null) {
    if (!echo) return;
    if (subscribed && subscribed !== conv?.id) {
        echo.leave(`conversation.${subscribed}`);
        subscribed = null;
    }
    if (!conv || subscribed === conv.id) return;
    subscribed = conv.id;
    echo.private(`conversation.${conv.id}`)
        .listen('.message.created', (e: { message: Message }) => onIncoming(e.message))
        .listen('.conversation.updated', (e: { conversation: { id: string; status: WidgetConversation['status']; assignee: { name: string } | null } }) => {
            if (conversation.value && e.conversation.id === conversation.value.id) {
                conversation.value = { ...conversation.value, status: e.conversation.status, agent: e.conversation.assignee?.name ?? null };
            }
        })
        .listen('.typing', (e: { sender_type: string; name: string; typing: boolean }) => {
            if (e.sender_type === 'agent') agentTyping.value = e.typing ? e.name : null;
        });
}

/** Busca mensagens perdidas durante uma desconexão. */
async function recover() {
    if (!conversation.value) return;
    try {
        const data = await api.conversation(lastSeq.value);
        if (data.conversation) conversation.value = data.conversation;
        merge(data.messages);
        scrollToBottom();
    } catch {
        /* nova tentativa na próxima reconexão */
    }
}

// ---------- Envio ----------
async function deliver(m: Message) {
    m.pending = true;
    m.failed = false;
    try {
        const res = await api.sendMessage(m.body, m.client_message_id!);
        merge([res.message, ...(res.replies ?? [])]);
        scrollToBottom();
    } catch {
        m.pending = false;
        m.failed = true;
    }
}

function send() {
    const body = draft.value.trim();
    if (!body || !conversation.value || isClosed.value) return;
    const m: Message = {
        id: uuid(),
        seq: 0,
        conversation_id: conversation.value.id,
        sender_type: 'visitor',
        author_name: 'Você',
        body,
        client_message_id: uuid(),
        created_at: new Date().toISOString(),
        read_at: null,
        pending: true,
    };
    messages.value.push(m);
    draft.value = '';
    stopTyping();
    scrollToBottom();
    deliver(m);
}

async function start() {
    formError.value = null;
    if (!form.message.trim()) {
        formError.value = 'Escreva sua mensagem.';
        return;
    }
    sending.value = true;
    try {
        const data = await api.startConversation({
            name: form.name || undefined,
            email: form.email || undefined,
            phone: form.phone || undefined,
            message: form.message,
            client_message_id: uuid(),
        });
        conversation.value = data.conversation;
        messages.value = [];
        merge(data.messages);
        startingNew.value = false;
        form.message = '';
        subscribe(data.conversation);
        scrollToBottom();
    } catch (e) {
        const err = e as Error & { errors?: Record<string, string[]> };
        formError.value = err.errors ? Object.values(err.errors)[0]?.[0] ?? err.message : err.message;
    } finally {
        sending.value = false;
    }
}

async function choose(option: BotOption) {
    if (choosing.value) return;
    choosing.value = true;
    chooseError.value = null;
    try {
        const data = await api.selectOption(option.id, uuid());
        if (data.conversation) conversation.value = data.conversation;
        merge(data.messages);
        scrollToBottom();
    } catch (e) {
        chooseError.value = (e as Error).message;
        recover();
    } finally {
        choosing.value = false;
    }
}

function newConversation() {
    startingNew.value = true;
    messages.value = [];
}

// ---------- Digitação ----------
let typingSent = false;
let typingTimer: ReturnType<typeof setTimeout> | undefined;
function onInput() {
    if (!conversation.value || isClosed.value) return;
    if (!typingSent) {
        typingSent = true;
        api.typing(true).catch(() => {});
    }
    clearTimeout(typingTimer);
    typingTimer = setTimeout(stopTyping, 2500);
}
function stopTyping() {
    clearTimeout(typingTimer);
    if (typingSent) api.typing(false).catch(() => {});
    typingSent = false;
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

// ---------- Inicialização ----------
onMounted(async () => {
    document.documentElement.style.setProperty('--nx-primary', cfg.primary_color);
    postLayout();
    try {
        const page = new URLSearchParams(window.location.search).get('page');
        const data = await api.startSession(page);
        form.name = data.visitor.name ?? '';
        form.email = data.visitor.email ?? '';
        conversation.value = data.conversation;
        merge(data.messages);
        unread.value = data.conversation?.unread ?? 0;
        connect();
        subscribe(data.conversation);
    } catch (e) {
        fatal.value = (e as Error).message || 'Não foi possível iniciar o atendimento.';
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    stopTyping();
    echo?.disconnect();
});
</script>

<template>
    <div :class="['nx', cfg.position === 'left' ? 'nx-left' : 'nx-right']">
        <section v-if="open" class="nx-panel" role="dialog" :aria-label="cfg.title">
            <header class="nx-header">
                <svg class="nx-waves" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true">
                    <path d="M0 38C80 22 150 52 240 40s120-30 160-24v44H0Z" fill="#fff" opacity=".10" />
                    <path d="M0 48c90-12 170 14 260 4 60-7 100-20 140-22v30H0Z" fill="#fff" opacity=".14" />
                </svg>
                <span class="nx-avatar"><NexoMark mono class="nx-mark" /></span>
                <div class="nx-head-text">
                    <strong>{{ cfg.title }}</strong>
                    <span class="nx-status">
                        <i :class="cfg.online ? 'on' : 'off'" />
                        {{ conversation?.agent ? `${conversation.agent} está atendendo` : withBot ? 'Assistente virtual' : cfg.online ? 'Online' : 'Offline — deixe sua mensagem' }}
                    </span>
                </div>
                <button class="nx-icon" aria-label="Minimizar" @click="open = false">&#8211;</button>
            </header>

            <div v-if="loading" class="nx-center">Carregando…</div>
            <div v-else-if="fatal" class="nx-center nx-error">{{ fatal }}</div>

            <form v-else-if="showForm" class="nx-form" @submit.prevent="start">
                <p class="nx-welcome">{{ cfg.online ? cfg.welcome_message : cfg.offline_message || cfg.welcome_message }}</p>
                <input v-if="cfg.form.ask_name" v-model="form.name" placeholder="Seu nome" autocomplete="name" maxlength="120" />
                <input v-if="cfg.form.ask_email" v-model="form.email" type="email" :placeholder="cfg.form.require_email ? 'Seu e-mail' : 'Seu e-mail (opcional)'" autocomplete="email" maxlength="180" />
                <input v-if="cfg.form.ask_phone" v-model="form.phone" type="tel" placeholder="Telefone (opcional)" autocomplete="tel" maxlength="32" />
                <textarea v-model="form.message" rows="3" placeholder="Como podemos ajudar?" maxlength="4000" />
                <p v-if="formError" class="nx-error">{{ formError }}</p>
                <button type="submit" class="nx-primary" :disabled="sending">{{ sending ? 'Enviando…' : 'Iniciar conversa' }}</button>
                <p class="nx-legal">Ao iniciar, você concorda com o uso dos dados informados para este atendimento.</p>
            </form>

            <template v-else>
                <div ref="scroller" class="nx-messages" aria-live="polite">
                    <p v-if="cfg.welcome_message && !hasBotMessages" class="nx-bubble nx-them">{{ cfg.welcome_message }}</p>
                    <template v-for="m in messages" :key="m.client_message_id ?? m.id">
                        <p v-if="m.sender_type === 'system'" class="nx-system">{{ m.body }}</p>
                        <div v-else :class="['nx-msg', m.sender_type === 'visitor' ? 'nx-me' : 'nx-them']">
                            <span v-if="m.sender_type === 'agent' || m.sender_type === 'bot'" class="nx-author">{{ m.author_name }}</span>
                            <p :class="['nx-bubble', m.pending && 'nx-pending']">{{ m.body }}</p>
                            <div v-if="m.id === optionsMessageId" class="nx-options" role="group" aria-label="Opções">
                                <button v-for="o in m.options" :key="o.id" type="button" class="nx-option" :disabled="choosing" @click="choose(o)">{{ o.label }}</button>
                            </div>
                            <p v-if="m.id === optionsMessageId && chooseError" class="nx-error">{{ chooseError }}</p>
                            <span class="nx-time">
                                {{ formatTime(m.created_at) }}
                                <button v-if="m.failed" class="nx-retry" @click="deliver(m)">não enviada — tentar de novo</button>
                            </span>
                        </div>
                    </template>
                    <p v-if="agentTyping" class="nx-typing">{{ agentTyping }} está digitando…</p>
                </div>
                <div v-if="isClosed" class="nx-closed">
                    Atendimento encerrado.
                    <button class="nx-primary" @click="newConversation">Nova conversa</button>
                </div>
                <form v-else class="nx-composer" @submit.prevent="send">
                    <textarea v-model="draft" rows="1" placeholder="Digite sua mensagem…" aria-label="Mensagem" maxlength="4000" @keydown="onKeydown" @input="onInput" />
                    <button type="submit" class="nx-send" :disabled="!draft.trim()" aria-label="Enviar">&#10148;</button>
                </form>
                <p v-if="!connected" class="nx-offline-net">Reconectando…</p>
            </template>
            <a class="nx-powered" href="https://chat.nexo.com.br" target="_blank" rel="noopener noreferrer">
                <NexoMark class="nx-powered-mark" /> Atendimento por <b>NEXO</b>&nbsp;Chat
            </a>
        </section>

        <button class="nx-launcher" :aria-label="open ? 'Fechar chat' : 'Abrir chat'" @click="open = !open">
            <NexoMark v-if="!open" mono class="nx-launcher-mark" />
            <svg v-else viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
            <span v-if="unread > 0 && !open" class="nx-unread">{{ unread }}</span>
        </button>
    </div>
</template>

<style>
:root {
    /* cor do widget (configurável por empresa); padrão = azul NEXO */
    --nx-primary: #1677f0;
    --nx-navy: #0e2a63;
}
* {
    box-sizing: border-box;
}
html,
body {
    margin: 0;
    background: transparent;
    font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    color: #0e1f3d;
    height: 100%;
    overflow: hidden;
}
.nx {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 16px;
    gap: 12px;
}
.nx-right {
    align-items: flex-end;
}
.nx-left {
    align-items: flex-start;
}
.nx-launcher {
    position: relative;
    width: 60px;
    height: 60px;
    flex-shrink: 0;
    border-radius: 50%;
    border: 0;
    cursor: pointer;
    background: var(--nx-primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px rgb(14 42 99 / 0.35);
    transition: transform 0.15s;
}
.nx-launcher:hover {
    transform: scale(1.05);
}
.nx-launcher-mark {
    width: 32px;
    height: auto;
    color: #fff;
}
.nx-unread {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 10px;
    background: #e11d48;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    line-height: 20px;
}
.nx-panel {
    width: 100%;
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgb(0 0 0 / 0.18);
    animation: nx-in 0.18s ease-out;
}
@keyframes nx-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
}
.nx-header {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 16px 22px;
    /* azul-marinho da marca para a cor do widget, como na papelaria */
    background: linear-gradient(120deg, var(--nx-navy) 0%, var(--nx-primary) 130%);
    color: #fff;
}
.nx-waves {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 34px;
    pointer-events: none;
}
.nx-avatar {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border-radius: 12px;
    background: rgb(255 255 255 / 0.12);
}
.nx-mark {
    width: 24px;
    height: auto;
    color: #fff;
}
.nx-head-text {
    position: relative;
    flex: 1;
    min-width: 0;
}
.nx-header .nx-icon {
    position: relative;
}
.nx-header strong {
    display: block;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -0.01em;
}
.nx-powered {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px;
    font-size: 11px;
    color: #8a96ad;
    text-decoration: none;
    border-top: 1px solid #eef1f7;
}
.nx-powered b {
    color: var(--nx-navy);
}
.nx-powered-mark {
    width: 14px;
    height: auto;
}
.nx-status {
    font-size: 12px;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 6px;
}
.nx-status i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.nx-status i.on {
    background: #4ade80;
}
.nx-status i.off {
    background: #cbd5e1;
}
.nx-icon {
    background: transparent;
    border: 0;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
}
.nx-center {
    margin: auto;
    padding: 24px;
    text-align: center;
    color: #6b7280;
}
.nx-error {
    color: #be123c;
    font-size: 13px;
    margin: 0;
}
.nx-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
    overflow-y: auto;
}
.nx-welcome {
    margin: 0 0 4px;
    color: #374151;
    line-height: 1.5;
}
.nx-form input,
.nx-form textarea,
.nx-composer textarea {
    width: 100%;
    font: inherit;
    padding: 10px 12px;
    border: 1px solid #d8dbe5;
    border-radius: 10px;
    outline: none;
    resize: none;
}
.nx-form input:focus,
.nx-form textarea:focus,
.nx-composer textarea:focus {
    border-color: var(--nx-primary);
}
.nx-primary {
    background: var(--nx-primary);
    color: #fff;
    border: 0;
    border-radius: 10px;
    padding: 10px 14px;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}
.nx-primary:disabled {
    opacity: 0.6;
}
.nx-legal {
    margin: 0;
    font-size: 11px;
    color: #9ca3af;
}
.nx-messages {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f7f8fb;
}
.nx-msg {
    display: flex;
    flex-direction: column;
    max-width: 80%;
}
.nx-me {
    align-self: flex-end;
    align-items: flex-end;
}
.nx-them {
    align-self: flex-start;
    align-items: flex-start;
}
.nx-bubble {
    margin: 0;
    padding: 9px 13px;
    border-radius: 14px;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
}
.nx-me .nx-bubble {
    background: var(--nx-primary);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.nx-them .nx-bubble,
p.nx-bubble.nx-them {
    background: #fff;
    border: 1px solid #e6e8ef;
    border-bottom-left-radius: 4px;
    max-width: 80%;
}
.nx-options {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.nx-option {
    font: inherit;
    font-size: 13px;
    padding: 7px 12px;
    border-radius: 999px;
    border: 1px solid var(--nx-primary);
    background: #fff;
    color: var(--nx-primary);
    cursor: pointer;
    text-align: left;
    transition:
        background 0.12s,
        color 0.12s;
}
.nx-option:hover:not(:disabled) {
    background: var(--nx-primary);
    color: #fff;
}
.nx-option:disabled {
    opacity: 0.5;
    cursor: default;
}
.nx-pending {
    opacity: 0.65;
}
.nx-author,
.nx-time {
    font-size: 11px;
    color: #8a90a2;
    margin: 2px 4px;
}
.nx-retry {
    border: 0;
    background: none;
    color: #be123c;
    cursor: pointer;
    font-size: 11px;
    padding: 0;
}
.nx-system {
    align-self: center;
    font-size: 12px;
    color: #8a90a2;
    margin: 4px 0;
}
.nx-typing {
    font-size: 12px;
    color: #8a90a2;
    font-style: italic;
    margin: 0;
}
.nx-composer {
    display: flex;
    gap: 8px;
    padding: 10px;
    border-top: 1px solid #eceef4;
    align-items: flex-end;
}
.nx-send {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border: 0;
    border-radius: 10px;
    background: var(--nx-primary);
    color: #fff;
    cursor: pointer;
    font-size: 16px;
}
.nx-send:disabled {
    opacity: 0.4;
}
.nx-closed {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 12px;
    border-top: 1px solid #eceef4;
    color: #6b7280;
}
.nx-offline-net {
    margin: 0;
    padding: 4px;
    text-align: center;
    font-size: 11px;
    background: #fff7ed;
    color: #9a3412;
}
</style>
