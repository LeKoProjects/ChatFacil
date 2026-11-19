<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Bot, CornerDownRight, FolderTree, MessageSquareText, Pencil, Plus, Search, Trash2, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import Badge from '@/components/ui/Badge.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Textarea from '@/components/ui/Textarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/http';

type NodeType = 'menu' | 'answer' | 'handoff';

interface BotNode {
    id: number;
    parent_id: number | null;
    type: NodeType;
    label: string;
    message: string | null;
    handoff_tag: string | null;
}

interface Faq {
    id: number;
    question: string;
    answer: string;
    keywords: string | null;
    is_active: boolean;
    hits: number;
}

interface Settings {
    is_enabled: boolean;
    greeting: string;
    fallback_message: string;
    handoff_message: string;
    offline_handoff_message: string;
    resolved_message: string;
}

const props = defineProps<{
    settings: Settings;
    nodes: BotNode[];
    faqs: Faq[];
    types: { value: NodeType; label: string }[];
    limits: { depth: number; nodes: number; faqs: number };
}>();

// ---------- Configurações ----------
const settingsForm = useForm<Settings>({ ...props.settings });
function saveSettings() {
    settingsForm.put('/chatbot', { preserveScroll: true });
}

const messageFields: { key: keyof Omit<Settings, 'is_enabled'>; label: string; hint: string }[] = [
    { key: 'greeting', label: 'Saudação', hint: 'Primeira mensagem do bot, acima do menu principal.' },
    { key: 'fallback_message', label: 'Quando não entender', hint: 'Usada quando nada da FAQ combina com o que o visitante digitou.' },
    { key: 'handoff_message', label: 'Ao transferir (equipe online)', hint: 'Antes de passar a conversa para um atendente.' },
    { key: 'offline_handoff_message', label: 'Ao transferir (equipe offline)', hint: 'Quando não há ninguém online para atender.' },
    { key: 'resolved_message', label: 'Quando resolver', hint: 'Depois de "Isso resolveu"; a conversa é encerrada.' },
];

// ---------- Menu em árvore ----------
const typeIcon = { menu: FolderTree, answer: MessageSquareText, handoff: UserRound } as const;
const typeLabel = computed(() => Object.fromEntries(props.types.map((t) => [t.value, t.label])) as Record<NodeType, string>);

/** Árvore achatada na ordem de exibição, com a profundidade de cada opção. */
const tree = computed(() => {
    const byParent = new Map<number | null, BotNode[]>();
    for (const n of props.nodes) byParent.set(n.parent_id, [...(byParent.get(n.parent_id) ?? []), n]);
    const rows: { node: BotNode; depth: number; index: number; siblings: number }[] = [];
    const walk = (parent: number | null, depth: number) => {
        const children = byParent.get(parent) ?? [];
        children.forEach((node, index) => {
            rows.push({ node, depth, index, siblings: children.length });
            walk(node.id, depth + 1);
        });
    };
    walk(null, 1);
    return rows;
});
const rootNodes = computed(() => props.nodes.filter((n) => n.parent_id === null));

const nodeOpen = ref(false);
const editingNode = ref<BotNode | null>(null);
const parentLabel = ref<string | null>(null);
const nodeForm = useForm({ parent_id: null as number | null, type: 'answer' as NodeType, label: '', message: '', handoff_tag: '' });

function openNode(node: BotNode | null, parent: BotNode | null = null) {
    editingNode.value = node;
    parentLabel.value = parent?.label ?? null;
    nodeForm.clearErrors();
    nodeForm.parent_id = node?.parent_id ?? parent?.id ?? null;
    nodeForm.type = node?.type ?? 'answer';
    nodeForm.label = node?.label ?? '';
    nodeForm.message = node?.message ?? '';
    nodeForm.handoff_tag = node?.handoff_tag ?? '';
    nodeOpen.value = true;
}

function saveNode() {
    const opts = { preserveScroll: true, onSuccess: () => (nodeOpen.value = false) };
    if (editingNode.value) nodeForm.put(`/chatbot/nodes/${editingNode.value.id}`, opts);
    else nodeForm.post('/chatbot/nodes', opts);
}

function moveNode(node: BotNode, direction: 'up' | 'down') {
    router.post(`/chatbot/nodes/${node.id}/move`, { direction }, { preserveScroll: true });
}

function removeNode(node: BotNode) {
    const hasChildren = props.nodes.some((n) => n.parent_id === node.id);
    if (confirm(hasChildren ? `Remover "${node.label}" e todas as opções dentro dele?` : `Remover "${node.label}"?`)) {
        router.delete(`/chatbot/nodes/${node.id}`, { preserveScroll: true });
    }
}

// ---------- FAQ ----------
const faqOpen = ref(false);
const editingFaq = ref<Faq | null>(null);
const faqForm = useForm({ question: '', answer: '', keywords: '', is_active: true });

function openFaq(faq: Faq | null) {
    editingFaq.value = faq;
    faqForm.clearErrors();
    faqForm.question = faq?.question ?? '';
    faqForm.answer = faq?.answer ?? '';
    faqForm.keywords = faq?.keywords ?? '';
    faqForm.is_active = faq?.is_active ?? true;
    faqOpen.value = true;
}

function saveFaq() {
    const opts = { preserveScroll: true, onSuccess: () => (faqOpen.value = false) };
    if (editingFaq.value) faqForm.put(`/chatbot/faqs/${editingFaq.value.id}`, opts);
    else faqForm.post('/chatbot/faqs', opts);
}

function removeFaq(faq: Faq) {
    if (confirm(`Remover a pergunta "${faq.question}"?`)) router.delete(`/chatbot/faqs/${faq.id}`, { preserveScroll: true });
}

const testQuery = ref('');
const testResults = ref<{ id: number; question: string }[] | null>(null);
async function testSearch() {
    if (!testQuery.value.trim()) return;
    const data = await api<{ results: { id: number; question: string }[] }>('GET', `/chatbot/faqs/search?q=${encodeURIComponent(testQuery.value)}`);
    testResults.value = data.results;
}
</script>

<template>
    <Head title="Chatbot" />
    <AppLayout title="Chatbot">
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-semibold">
                        Chatbot
                        <Badge :variant="settings.is_enabled ? 'success' : 'secondary'">{{ settings.is_enabled ? 'Ligado' : 'Desligado' }}</Badge>
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Atendimento automático por menu e perguntas frequentes, antes da equipe. Sem IA e sem custo por mensagem.
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <div class="min-w-0 space-y-6">
                    <!-- Menu -->
                    <Card class="p-5">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="font-semibold">Menu de opções</h2>
                                <p class="text-sm text-muted-foreground">
                                    Botões que o visitante clica. Submenus abrem outras opções (até
                                    {{ limits.depth }} níveis).
                                </p>
                            </div>
                            <Button size="sm" @click="openNode(null)"><Plus /> Opção no menu principal</Button>
                        </div>

                        <p v-if="!tree.length" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Nenhuma opção ainda. Comece por algo como "Suporte técnico", "Financeiro" e "Comercial".
                        </p>

                        <ul v-else class="divide-y rounded-md border">
                            <li
                                v-for="row in tree"
                                :key="row.node.id"
                                class="flex items-center gap-2 py-2 pr-2"
                                :style="{ paddingLeft: `${(row.depth - 1) * 24 + 12}px` }"
                            >
                                <CornerDownRight v-if="row.depth > 1" class="size-3.5 shrink-0 text-muted-foreground" />
                                <component :is="typeIcon[row.node.type]" class="size-4 shrink-0 text-primary" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ row.node.label }}
                                    </p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ typeLabel[row.node.type]
                                        }}<template v-if="row.node.handoff_tag">
                                            · setor
                                            {{ row.node.handoff_tag }}</template
                                        ><template v-if="row.node.message"> · {{ row.node.message }}</template>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center">
                                    <Button
                                        v-if="row.node.type === 'menu' && row.depth < limits.depth"
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="'Adicionar opção em ' + row.node.label"
                                        title="Adicionar opção dentro"
                                        @click="openNode(null, row.node)"
                                        ><Plus
                                    /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Subir"
                                        :disabled="row.index === 0"
                                        @click="moveNode(row.node, 'up')"
                                        ><ArrowUp
                                    /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Descer"
                                        :disabled="row.index === row.siblings - 1"
                                        @click="moveNode(row.node, 'down')"
                                        ><ArrowDown
                                    /></Button>
                                    <Button variant="ghost" size="icon-sm" :aria-label="'Editar ' + row.node.label" @click="openNode(row.node)"
                                        ><Pencil
                                    /></Button>
                                    <Button variant="ghost" size="icon-sm" :aria-label="'Remover ' + row.node.label" @click="removeNode(row.node)"
                                        ><Trash2 class="text-destructive"
                                    /></Button>
                                </div>
                            </li>
                        </ul>
                        <p class="mt-3 text-xs text-muted-foreground">O botão "Falar com um atendente" aparece sempre, no fim de cada menu.</p>
                    </Card>

                    <!-- FAQ -->
                    <Card class="p-5">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="font-semibold">Perguntas frequentes</h2>
                                <p class="text-sm text-muted-foreground">Quando o visitante digita, o bot sugere as perguntas mais parecidas.</p>
                            </div>
                            <Button size="sm" @click="openFaq(null)"><Plus /> Nova pergunta</Button>
                        </div>

                        <form class="mb-4 flex gap-2" @submit.prevent="testSearch">
                            <Input
                                v-model="testQuery"
                                placeholder="Testar: escreva como um visitante, ex.: qual a hora que vcs abrem"
                                aria-label="Testar busca"
                            />
                            <Button type="submit" variant="outline"><Search /> Testar</Button>
                        </form>
                        <div v-if="testResults" class="mb-4 rounded-md bg-muted/50 p-3 text-sm">
                            <p v-if="!testResults.length" class="text-muted-foreground">
                                Nada encontrado: o bot mostraria a mensagem "Quando não entender".
                            </p>
                            <template v-else>
                                <p class="mb-1 text-muted-foreground">O bot sugeriria:</p>
                                <ol class="list-decimal pl-5">
                                    <li v-for="r in testResults" :key="r.id">
                                        {{ r.question }}
                                    </li>
                                </ol>
                            </template>
                        </div>

                        <p v-if="!faqs.length" class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Nenhuma pergunta cadastrada.
                        </p>
                        <div v-else class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="border-b text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">Pergunta</th>
                                        <th class="px-3 py-2 font-medium">Usos</th>
                                        <th class="px-3 py-2" />
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="f in faqs" :key="f.id" :class="!f.is_active ? 'opacity-60' : ''">
                                        <td class="px-3 py-2">
                                            <p class="font-medium">
                                                {{ f.question }}
                                                <Badge v-if="!f.is_active" variant="outline">Inativa</Badge>
                                            </p>
                                            <p class="line-clamp-1 text-xs text-muted-foreground">
                                                {{ f.answer }}
                                            </p>
                                        </td>
                                        <td class="px-3 py-2 tabular-nums">
                                            {{ f.hits }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex justify-end">
                                                <Button variant="ghost" size="icon-sm" :aria-label="'Editar ' + f.question" @click="openFaq(f)"
                                                    ><Pencil
                                                /></Button>
                                                <Button variant="ghost" size="icon-sm" :aria-label="'Remover ' + f.question" @click="removeFaq(f)"
                                                    ><Trash2 class="text-destructive"
                                                /></Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <div class="space-y-6">
                    <!-- Configurações -->
                    <Card class="p-5">
                        <form class="space-y-4" @submit.prevent="saveSettings">
                            <h2 class="font-semibold">Configurações</h2>
                            <Checkbox id="bot-enabled" v-model="settingsForm.is_enabled" label="Chatbot ligado" />
                            <p class="-mt-2 text-xs text-muted-foreground">
                                Ligado, toda conversa nova começa com o bot. Ele só age se houver menu ou FAQ.
                            </p>
                            <div v-for="f in messageFields" :key="f.key" class="space-y-1.5">
                                <Label :for="'bot-' + f.key">{{ f.label }}</Label>
                                <Textarea :id="'bot-' + f.key" v-model="settingsForm[f.key]" rows="2" maxlength="1000" />
                                <p class="text-xs text-muted-foreground">
                                    {{ f.hint }}
                                </p>
                                <InputError :message="settingsForm.errors[f.key]" />
                            </div>
                            <Button type="submit" class="w-full" :disabled="settingsForm.processing">Salvar configurações</Button>
                        </form>
                    </Card>

                    <!-- Prévia -->
                    <Card class="p-5">
                        <h2 class="mb-3 flex items-center gap-2 font-semibold"><Bot class="size-4" /> Prévia</h2>
                        <div class="space-y-2 rounded-lg bg-muted/50 p-3 text-sm">
                            <p class="rounded-lg border bg-card px-3 py-2 whitespace-pre-line">
                                {{ settingsForm.greeting }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="n in rootNodes"
                                    :key="n.id"
                                    class="rounded-full border border-primary/40 px-3 py-1 text-xs text-primary"
                                    >{{ n.label }}</span
                                >
                                <span class="rounded-full border border-primary/40 px-3 py-1 text-xs text-primary">Falar com um atendente</span>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>

        <Dialog
            v-model:open="nodeOpen"
            :title="editingNode ? 'Editar opção' : parentLabel ? `Nova opção em “${parentLabel}”` : 'Nova opção no menu principal'"
        >
            <form class="space-y-4" @submit.prevent="saveNode">
                <div class="space-y-2">
                    <Label for="n-label">Texto do botão</Label>
                    <Input id="n-label" v-model="nodeForm.label" maxlength="60" placeholder="Ex.: Segunda via de boleto" required />
                    <InputError :message="nodeForm.errors.label" />
                </div>
                <div class="space-y-2">
                    <Label for="n-type">O que acontece ao clicar</Label>
                    <select id="n-type" v-model="nodeForm.type" class="h-9 w-full rounded-md border bg-card px-2 text-sm">
                        <option v-for="t in types" :key="t.value" :value="t.value">
                            {{ t.label }}
                        </option>
                    </select>
                    <InputError :message="nodeForm.errors.type || nodeForm.errors.parent_id" />
                </div>
                <div class="space-y-2">
                    <Label for="n-message">
                        {{
                            nodeForm.type === 'answer'
                                ? 'Resposta'
                                : nodeForm.type === 'menu'
                                  ? 'Pergunta do submenu (opcional)'
                                  : 'Mensagem antes de transferir (opcional)'
                        }}
                    </Label>
                    <Textarea
                        id="n-message"
                        v-model="nodeForm.message"
                        rows="4"
                        maxlength="2000"
                        :placeholder="nodeForm.type === 'menu' ? 'Escolha uma opção:' : ''"
                    />
                    <InputError :message="nodeForm.errors.message" />
                </div>
                <div v-if="nodeForm.type === 'handoff'" class="space-y-2">
                    <Label for="n-tag">Setor (opcional)</Label>
                    <Input id="n-tag" v-model="nodeForm.handoff_tag" maxlength="60" placeholder="Ex.: Financeiro" />
                    <p class="text-xs text-muted-foreground">Aparece para o atendente na conversa transferida. Se vazio, usa o texto do botão.</p>
                    <InputError :message="nodeForm.errors.handoff_tag" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="nodeOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="nodeForm.processing">Salvar</Button>
                </div>
            </form>
        </Dialog>

        <Dialog v-model:open="faqOpen" :title="editingFaq ? 'Editar pergunta' : 'Nova pergunta'">
            <form class="space-y-4" @submit.prevent="saveFaq">
                <div class="space-y-2">
                    <Label for="f-question">Pergunta</Label>
                    <Input
                        id="f-question"
                        v-model="faqForm.question"
                        maxlength="200"
                        placeholder="Ex.: Como emitir a segunda via do boleto?"
                        required
                    />
                    <InputError :message="faqForm.errors.question" />
                </div>
                <div class="space-y-2">
                    <Label for="f-answer">Resposta</Label>
                    <Textarea id="f-answer" v-model="faqForm.answer" rows="5" maxlength="4000" />
                    <InputError :message="faqForm.errors.answer" />
                </div>
                <div class="space-y-2">
                    <Label for="f-keywords">Palavras-chave (opcional)</Label>
                    <Input id="f-keywords" v-model="faqForm.keywords" maxlength="300" placeholder="Ex.: fatura, pagamento, cobrança" />
                    <p class="text-xs text-muted-foreground">Outras palavras que o visitante pode usar para essa dúvida.</p>
                    <InputError :message="faqForm.errors.keywords" />
                </div>
                <Checkbox id="f-active" v-model="faqForm.is_active" label="Ativa" />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="faqOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="faqForm.processing">Salvar</Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
