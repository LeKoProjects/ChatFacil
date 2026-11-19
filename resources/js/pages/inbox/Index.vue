<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Bot, ExternalLink, Inbox, Lock, LockOpen, Search, Send, UserCheck, Wifi, WifiOff } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import NexoMark from '@/components/brand/NexoMark.vue';
import EmptyState from '@/components/EmptyState.vue';
import MessageBubble from '@/components/inbox/MessageBubble.vue';
import StatusBadge from '@/components/inbox/StatusBadge.vue';
import Avatar from '@/components/ui/Avatar.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/Input.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useInboxRealtime } from '@/composables/useInboxRealtime';
import AppLayout from '@/layouts/AppLayout.vue';
import { api, HttpError } from '@/lib/http';
import { cn, formatTime, relativeFrom, uuid } from '@/lib/utils';
import type { AgentOption, ConversationSummary, Message, SharedProps } from '@/types';

const props = defineProps<{
    filter: string;
    search: string;
    conversations: ConversationSummary[];
    counts: { waiting: number; mine: number; open: number; bot: number };
    selected: ConversationSummary | null;
    messages: Message[];
    history: { id: string; status: string; status_label: string; created_at: string }[];
    agents: AgentOption[];
}>();

const page = usePage<SharedProps>();
const me = computed(() => page.props.auth.user!);
const isAdmin = computed(() => me.value.role === 'company_admin');

// ---- Estado local (sincronizado com props e eventos em tempo real) ----
const list = ref<ConversationSummary[]>([...props.conversations]);
const selected = ref<ConversationSummary | null>(props.selected);
const thread = ref<Message[]>([...props.messages]);
const counts = ref({ ...props.counts });
const selectedId = computed(() => selected.value?.id ?? null);

watch(() => props.conversations, (v) => (list.value = [...v]));
watch(() => props.selected, (v) => (selected.value = v));
watch(() => props.messages, (v) => {
    thread.value = mergeMessages([], v);
    scrollToBottom();
});
watch(() => props.counts, (v) => (counts.value = { ...v }));

const filters = computed(() => [
    { key: 'mine_and_waiting', label: 'Minhas + fila' },
    { key: 'waiting', label: `Fila (${counts.value.waiting})` },
    { key: 'mine', label: `Minhas (${counts.value.mine})` },
    { key: 'open', label: `Em atendimento (${counts.value.open})` },
    { key: 'bot', label: `Com o bot (${counts.value.bot})` },
    { key: 'closed', label: 'Finalizadas' },
    { key: 'all', label: 'Todas' },
]);

const searchTerm = ref(props.search);

function visit(path: string, extra: Record<string, string> = {}) {
    const query = new URLSearchParams({ filter: props.filter, ...(searchTerm.value ? { q: searchTerm.value } : {}), ...extra });
    router.visit(`${path}?${query.toString()}`, { preserveState: true, preserveScroll: true });
}

function setFilter(key: string) {
    router.visit(`/inbox${selected.value ? '/' + selected.value.id : ''}?filter=${key}${searchTerm.value ? '&q=' + encodeURIComponent(searchTerm.value) : ''}`, {
        preserveState: true,
    });
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(searchTerm, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(selected.value ? `/inbox/${selected.value.id}` : '/inbox'), 350);
});

function openConversation(c: ConversationSummary) {
    c.agent_unread_count = 0;
    visit(`/inbox/${c.id}`);
}

// ---- Mensagens ----
function mergeMessages(current: Message[], incoming: Message[]): Message[] {
    const map = new Map<string, Message>();
    for (const m of current) map.set(m.client_message_id ?? m.id, m);
    for (const m of incoming) {
        const key = m.client_message_id ?? m.id;
        map.set(key, { ...map.get(key), ...m, pending: false, failed: false });
    }
    // ordenação cronológica pela sequência do servidor (pendentes ao final)
    return [...map.values()].sort((a, b) => (a.seq || Number.MAX_SAFE_INTEGER) - (b.seq || Number.MAX_SAFE_INTEGER));
}

const scroller = ref<HTMLElement | null>(null);
function scrollToBottom() {
    nextTick(() => scroller.value?.scrollTo({ top: scroller.value.scrollHeight }));
}

const draft = ref('');
const canReply = computed(() => {
    const c = selected.value;
    if (!c || c.status === 'closed') return false;
    return !c.assignee || c.assignee.id === me.value.id || isAdmin.value;
});

async function deliver(message: Message) {
    message.pending = true;
    message.failed = false;
    try {
        const res = await api<{ message: Message }>('POST', `/conversations/${message.conversation_id}/messages`, {
            body: message.body,
            client_message_id: message.client_message_id,
        });
        thread.value = mergeMessages(thread.value, [res.message]);
    } catch (e) {
        message.pending = false;
        message.failed = true;
        toast.error(e instanceof HttpError ? e.message : 'Falha ao enviar a mensagem');
    }
}

function send() {
    const body = draft.value.trim();
    if (!body || !selected.value) return;
    const optimistic: Message = {
        id: uuid(),
        seq: 0,
        conversation_id: selected.value.id,
        sender_type: 'agent',
        author_name: me.value.name,
        body,
        client_message_id: uuid(),
        created_at: new Date().toISOString(),
        read_at: null,
        pending: true,
    };
    thread.value = [...thread.value, optimistic];
    draft.value = '';
    stopTyping();
    scrollToBottom();
    deliver(optimistic);
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

// ---- Digitação ----
let typingSent = false;
let typingTimer: ReturnType<typeof setTimeout> | undefined;
function onInput() {
    if (!selected.value) return;
    if (!typingSent) {
        typingSent = true;
        api('POST', `/conversations/${selected.value.id}/typing`, { typing: true }).catch(() => {});
    }
    clearTimeout(typingTimer);
    typingTimer = setTimeout(stopTyping, 2500);
}
function stopTyping() {
    clearTimeout(typingTimer);
    if (typingSent && selected.value) {
        api('POST', `/conversations/${selected.value.id}/typing`, { typing: false }).catch(() => {});
    }
    typingSent = false;
}
const visitorTyping = ref(false);
let visitorTypingTimer: ReturnType<typeof setTimeout> | undefined;

// ---- Ações ----
const busy = ref(false);
async function action(path: 'assign' | 'close' | 'reopen', body?: Record<string, unknown>) {
    if (!selected.value) return;
    busy.value = true;
    try {
        const res = await api<{ conversation: ConversationSummary }>('POST', `/conversations/${selected.value.id}/${path}`, body);
        upsert(res.conversation);
        selected.value = res.conversation;
        toast.success({ assign: 'Conversa atribuída', close: 'Conversa encerrada', reopen: 'Conversa reaberta' }[path]);
        if (path !== 'assign') router.reload({ only: ['messages', 'counts'] });
    } catch (e) {
        toast.error(e instanceof HttpError ? e.message : 'Não foi possível concluir a ação');
    } finally {
        busy.value = false;
    }
}

function assignTo(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    if (value) action('assign', { user_id: Number(value) });
}

// ---- Tempo real ----
/** Conversas com o chatbot só aparecem nos filtros "Com o bot" e "Todas" (ou se estiverem abertas na tela). */
function belongsToFilter(c: ConversationSummary): boolean {
    return c.status !== 'bot' || ['bot', 'all'].includes(props.filter) || selected.value?.id === c.id;
}

function upsert(c: ConversationSummary) {
    if (!belongsToFilter(c) && !list.value.some((x) => x.id === c.id)) return;
    const idx = list.value.findIndex((x) => x.id === c.id);
    if (idx >= 0) list.value.splice(idx, 1, { ...list.value[idx], ...c });
    else list.value.unshift(c);
    list.value.sort((a, b) => {
        const order = { waiting: 0, open: 1, pending: 2, bot: 3, closed: 4 };
        return order[a.status] - order[b.status] || (b.last_message_at ?? '').localeCompare(a.last_message_at ?? '');
    });
}

const { connected } = useInboxRealtime(page.props.reverb, me.value.company!.id, selectedId, {
    onMessage(message, conversation) {
        if (selected.value?.id === conversation.id) {
            thread.value = mergeMessages(thread.value, [message]);
            selected.value = { ...selected.value, ...conversation, agent_unread_count: 0 };
            conversation = { ...conversation, agent_unread_count: 0 };
            if (message.sender_type === 'visitor') visitorTyping.value = false;
            scrollToBottom();
        } else if (message.sender_type === 'visitor' && conversation.status !== 'bot') {
            notify(`${conversation.visitor.name}: ${message.body}`);
        }
        upsert(conversation);
    },
    onConversation(conversation, reason) {
        if (reason === 'created' && conversation.status === 'bot') {
            counts.value.bot++;
        } else if (reason === 'created') {
            counts.value.waiting++;
            notify(`Nova conversa de ${conversation.visitor.name}`);
        }
        if (reason === 'handoff') {
            counts.value.bot = Math.max(0, counts.value.bot - 1);
            counts.value.waiting++;
            notify(`${conversation.visitor.name} pediu atendimento${conversation.handoff_tag ? ` (${conversation.handoff_tag})` : ''}`);
        }
        if (selected.value?.id === conversation.id) {
            // conversa aberta na tela: nada fica "não lido" para o atendente
            conversation = { ...conversation, agent_unread_count: 0 };
            selected.value = { ...selected.value, ...conversation };
        }
        upsert(conversation);
    },
    onTyping(payload) {
        if (payload.sender_type !== 'visitor' || payload.conversation_id !== selected.value?.id) return;
        visitorTyping.value = payload.typing;
        clearTimeout(visitorTypingTimer);
        if (payload.typing) visitorTypingTimer = setTimeout(() => (visitorTyping.value = false), 6000);
    },
    onReconnected() {
        router.reload({ only: ['conversations', 'counts', 'messages', 'selected'] });
    },
});

function notify(text: string) {
    toast.info(text);
    if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        new Notification('NEXO Chat', { body: text });
    }
}

// relógio para tempos de espera
const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    scrollToBottom();
    clock = setInterval(() => (now.value = Date.now()), 15000);
    if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission().catch(() => {});
});
onBeforeUnmount(() => {
    clearInterval(clock);
    stopTyping();
});
</script>

<template>
    <Head title="Atendimento" />
    <AppLayout title="Central de atendimento" full-height>
        <div class="flex h-full">
            <!-- Coluna esquerda: filas e conversas -->
            <section :class="cn('flex w-full shrink-0 flex-col border-r bg-card md:w-80', selected && 'hidden md:flex')">
                <div class="space-y-3 border-b p-3">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchTerm" placeholder="Buscar visitante, e-mail, telefone" class="pl-8" aria-label="Buscar conversas" />
                    </div>
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="f in filters"
                            :key="f.key"
                            type="button"
                            :class="cn('rounded-full px-2.5 py-1 text-xs font-medium transition-colors', filter === f.key ? 'bg-primary text-primary-foreground' : 'bg-secondary text-secondary-foreground hover:bg-accent')"
                            @click="setFilter(f.key)"
                        >
                            {{ f.label }}
                        </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <EmptyState v-if="list.length === 0" :icon="Inbox" title="Nenhuma conversa aqui" description="Novas conversas do widget aparecem automaticamente." />
                    <button
                        v-for="c in list"
                        :key="c.id"
                        type="button"
                        :class="cn('flex w-full gap-3 border-b px-3 py-3 text-left transition-colors hover:bg-accent/60', selected?.id === c.id && 'bg-accent')"
                        @click="openConversation(c)"
                    >
                        <Avatar :name="c.visitor.name" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium">{{ c.visitor.name }}</span>
                                <span class="ml-auto shrink-0 text-[11px] text-muted-foreground">{{ formatTime(c.last_message_at ?? c.created_at) }}</span>
                            </div>
                            <p class="truncate text-xs text-muted-foreground">
                                <span v-if="c.last_message?.sender_type === 'agent'">Você: </span><span v-else-if="c.last_message?.sender_type === 'bot'">Bot: </span>{{ c.last_message?.body ?? '—' }}
                            </p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <StatusBadge :status="c.status" :label="c.status_label" />
                                <span v-if="c.status === 'waiting'" class="text-[11px] text-amber-600 dark:text-warning" :title="'Aguardando desde ' + c.created_at">
                                    espera {{ relativeFrom(c.created_at, now) }}
                                </span>
                                <span v-else-if="c.assignee" class="truncate text-[11px] text-muted-foreground">{{ c.assignee.name }}</span>
                                <span v-if="c.handoff_tag && c.status === 'waiting'" class="truncate text-[11px] text-muted-foreground">· {{ c.handoff_tag }}</span>
                                <span v-if="c.agent_unread_count > 0" class="ml-auto rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-foreground">
                                    {{ c.agent_unread_count }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>
                <div class="flex items-center gap-2 border-t px-3 py-2 text-xs text-muted-foreground">
                    <component :is="connected ? Wifi : WifiOff" :class="cn('size-3.5', connected ? 'text-success' : 'text-destructive')" />
                    {{ connected ? 'Tempo real conectado' : 'Reconectando…' }}
                </div>
            </section>

            <!-- Área central: mensagens -->
            <section :class="cn('min-w-0 flex-1 flex-col', selected ? 'flex' : 'hidden md:flex')">
                <EmptyState v-if="!selected" :icon="NexoMark" title="Selecione uma conversa" description="Escolha uma conversa na lista para visualizar e responder." class="my-auto" />
                <template v-else>
                    <div class="flex h-14 shrink-0 items-center gap-3 border-b bg-card px-4">
                        <button type="button" class="text-sm text-primary md:hidden" @click="router.visit('/inbox?filter=' + filter)">‹ Voltar</button>
                        <Avatar :name="selected.visitor.name" class="size-8" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ selected.visitor.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ visitorTyping ? 'digitando…' : selected.assignee ? `Com ${selected.assignee.name}` : selected.status === 'bot' ? 'Com o chatbot' : 'Sem responsável' }}
                            </p>
                        </div>
                        <div class="ml-auto flex items-center gap-2">
                            <StatusBadge :status="selected.status" :label="selected.status_label" />
                            <Button v-if="selected.status !== 'closed' && selected.assignee?.id !== me.id" size="sm" variant="outline" :disabled="busy" @click="action('assign')">
                                <UserCheck /> Assumir
                            </Button>
                            <Button v-if="selected.status !== 'closed'" size="sm" variant="outline" :disabled="busy" @click="action('close')"><Lock /> Encerrar</Button>
                            <Button v-else size="sm" variant="outline" :disabled="busy" @click="action('reopen')"><LockOpen /> Reabrir</Button>
                        </div>
                    </div>

                    <div ref="scroller" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-background px-4 py-4">
                        <MessageBubble v-for="m in thread" :key="m.client_message_id ?? m.id" :message="m" :mine="m.sender_type === 'agent'" @retry="deliver" />
                        <p v-if="visitorTyping" class="text-xs text-muted-foreground italic">{{ selected.visitor.name }} está digitando…</p>
                    </div>

                    <div class="shrink-0 border-t bg-card p-3">
                        <p v-if="selected.status === 'bot'" class="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Bot class="size-3.5" /> O chatbot está conduzindo esta conversa. Ao responder ou assumir, você passa a atender.
                        </p>
                        <p v-if="selected.status === 'closed'" class="text-center text-sm text-muted-foreground">Conversa finalizada. Reabra para responder.</p>
                        <p v-else-if="!canReply" class="text-center text-sm text-muted-foreground">Esta conversa está com {{ selected.assignee?.name }}.</p>
                        <form v-else class="flex items-end gap-2" @submit.prevent="send">
                            <Textarea
                                v-model="draft"
                                rows="2"
                                class="max-h-40 resize-none"
                                placeholder="Escreva uma resposta… (Enter envia, Shift+Enter quebra linha)"
                                aria-label="Mensagem"
                                @keydown="onKeydown"
                                @input="onInput"
                            />
                            <Button type="submit" size="icon" :disabled="!draft.trim()" aria-label="Enviar"><Send /></Button>
                        </form>
                    </div>
                </template>
            </section>

            <!-- Coluna direita: informações do visitante -->
            <aside v-if="selected" class="hidden w-72 shrink-0 flex-col gap-5 overflow-y-auto border-l bg-card p-4 xl:flex">
                <div class="flex flex-col items-center gap-2 text-center">
                    <Avatar :name="selected.visitor.name" class="size-14 text-base" />
                    <p class="font-semibold">{{ selected.visitor.name }}</p>
                    <StatusBadge :status="selected.status" :label="selected.status_label" />
                </div>

                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">E-mail</dt>
                        <dd class="break-all">{{ selected.visitor.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Telefone</dt>
                        <dd>{{ selected.visitor.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Página de origem</dt>
                        <dd>
                            <a v-if="selected.origin_url" :href="selected.origin_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 break-all text-primary hover:underline">
                                {{ selected.origin_url }} <ExternalLink class="size-3 shrink-0" />
                            </a>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div v-if="selected.handoff_tag">
                        <dt class="text-xs text-muted-foreground">Setor escolhido</dt>
                        <dd>{{ selected.handoff_tag }}</dd>
                    </div>
                    <div v-if="selected.bot_trail?.length">
                        <dt class="text-xs text-muted-foreground">Caminho no chatbot</dt>
                        <dd>
                            <ol class="mt-1 space-y-1">
                                <li v-for="(step, i) in selected.bot_trail" :key="i" class="flex gap-1.5 text-xs">
                                    <span class="text-muted-foreground tabular-nums">{{ i + 1 }}.</span> {{ step }}
                                </li>
                            </ol>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Widget</dt>
                        <dd>{{ selected.widget.name }}</dd>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Iniciada</dt>
                            <dd>{{ formatTime(selected.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">1ª resposta</dt>
                            <dd>{{ selected.first_response_at ? formatTime(selected.first_response_at) : '—' }}</dd>
                        </div>
                    </div>
                </dl>

                <div v-if="isAdmin && selected.status !== 'closed'" class="space-y-1.5">
                    <label for="assignee" class="text-xs text-muted-foreground">Atribuir a</label>
                    <select id="assignee" class="h-9 w-full rounded-md border bg-card px-2 text-sm" :value="selected.assignee?.id ?? ''" :disabled="busy" @change="assignTo">
                        <option value="" disabled>Selecione um atendente</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }} ({{ a.availability }})</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium text-muted-foreground uppercase">Conversas anteriores</p>
                    <p v-if="history.length === 0" class="text-sm text-muted-foreground">Nenhuma.</p>
                    <button v-for="h in history" :key="h.id" type="button" class="flex w-full items-center justify-between rounded-md border px-2 py-1.5 text-left text-xs hover:bg-accent" @click="visit(`/inbox/${h.id}`, { filter: 'all' })">
                        <span>{{ formatTime(h.created_at) }}</span>
                        <span class="text-muted-foreground">{{ h.status_label }}</span>
                    </button>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
