<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CircleCheck, Globe, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Textarea from '@/components/ui/Textarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { relativeFrom } from '@/lib/utils';

interface AuditEntry {
    id: number;
    action: string;
    label: string;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
}

const props = defineProps<{
    company: { id: number; uuid: string; name: string; slug: string; document: string | null; status: 'active' | 'suspended'; created_at: string; suspended_at: string | null };
    users: { id: number; name: string; email: string; role_label: string; availability: string; is_active: boolean; last_seen_at: string | null }[];
    websites: { id: number; name: string; url: string; domains: string[]; widgets: { name: string; public_key: string; is_active: boolean }[] }[];
    stats: { conversations_total: number; conversations_30d: number; active_now: number };
    audit: AuditEntry[];
}>();

const dt = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

// editar dados
const editOpen = ref(false);
const editForm = useForm({ name: props.company.name, document: props.company.document ?? '' });
function openEdit() {
    editForm.name = props.company.name;
    editForm.document = props.company.document ?? '';
    editForm.clearErrors();
    editOpen.value = true;
}
function saveEdit() {
    editForm.put(`/nexo/companies/${props.company.id}`, { preserveScroll: true, onSuccess: () => (editOpen.value = false) });
}

// suspender / reativar
const suspendOpen = ref(false);
const suspendForm = useForm({ reason: '' });
function suspend() {
    suspendForm.post(`/nexo/companies/${props.company.id}/suspend`, { preserveScroll: true, onSuccess: () => (suspendOpen.value = false) });
}
function activate() {
    if (confirm(`Reativar ${props.company.name}? Os usuários voltam a acessar e o widget volta a funcionar.`)) {
        router.post(`/nexo/companies/${props.company.id}/activate`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="company.name" />
    <AppLayout :title="company.name">
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <Link href="/nexo/companies" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft class="size-4" /> Empresas</Link>

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold">{{ company.name }}</h1>
                        <Badge :variant="company.status === 'active' ? 'success' : 'destructive'">{{ company.status === 'active' ? 'Ativa' : 'Suspensa' }}</Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ company.document ?? 'Sem CNPJ/CPF' }} · cliente desde {{ new Date(company.created_at).toLocaleDateString('pt-BR') }}
                        <template v-if="company.suspended_at"> · suspensa em {{ dt(company.suspended_at) }}</template>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="openEdit"><Pencil /> Editar</Button>
                    <Button v-if="company.status === 'active'" variant="destructive" @click="suspendOpen = true"><Ban /> Suspender</Button>
                    <Button v-else @click="activate"><CircleCheck /> Reativar</Button>
                </div>
            </div>

            <div v-if="company.status === 'suspended'" class="rounded-xl border border-destructive/30 bg-destructive/8 p-4 text-sm">
                <strong>Empresa suspensa.</strong> Os usuários não conseguem entrar e o widget não carrega nos sites desta empresa.
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card class="p-5">
                    <p class="text-sm text-muted-foreground">Conversas (total)</p>
                    <p class="mt-2 font-display text-3xl font-bold text-heading tabular-nums">{{ stats.conversations_total }}</p>
                </Card>
                <Card class="p-5">
                    <p class="text-sm text-muted-foreground">Conversas (30 dias)</p>
                    <p class="mt-2 font-display text-3xl font-bold text-heading tabular-nums">{{ stats.conversations_30d }}</p>
                </Card>
                <Card class="p-5">
                    <p class="text-sm text-muted-foreground">Ativas agora</p>
                    <p class="mt-2 font-display text-3xl font-bold text-heading tabular-nums">{{ stats.active_now }}</p>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <h2 class="border-b px-5 py-4 font-semibold">Usuários ({{ users.length }})</h2>
                    <ul class="divide-y">
                        <li v-for="u in users" :key="u.id" :class="['flex items-center gap-3 px-5 py-3', !u.is_active && 'opacity-50']">
                            <Avatar :name="u.name" class="size-8" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ u.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ u.email }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs">{{ u.role_label }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ !u.is_active ? 'inativo' : u.last_seen_at ? `visto há ${relativeFrom(u.last_seen_at)}` : u.availability }}</p>
                            </div>
                        </li>
                    </ul>
                </Card>

                <Card>
                    <h2 class="border-b px-5 py-4 font-semibold">Sites e widgets ({{ websites.length }})</h2>
                    <p v-if="websites.length === 0" class="px-5 py-6 text-sm text-muted-foreground">Nenhum site cadastrado ainda.</p>
                    <ul class="divide-y">
                        <li v-for="w in websites" :key="w.id" class="space-y-2 px-5 py-3">
                            <div class="flex items-center gap-2">
                                <Globe class="size-4 text-brand-blue" />
                                <span class="text-sm font-medium">{{ w.name }}</span>
                                <span class="truncate text-xs text-muted-foreground">{{ w.url }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="d in w.domains" :key="d" variant="secondary">{{ d }}</Badge>
                            </div>
                            <p v-for="wg in w.widgets" :key="wg.public_key" class="text-xs text-muted-foreground">
                                {{ wg.name }} · <code>{{ wg.public_key }}</code> · {{ wg.is_active ? 'ativo' : 'inativo' }}
                            </p>
                        </li>
                    </ul>
                </Card>
            </div>

            <Card>
                <div class="flex items-center justify-between border-b px-5 py-4">
                    <h2 class="font-semibold">Auditoria recente</h2>
                    <Link :href="`/nexo/audit?company=${company.id}`" class="text-xs text-primary hover:underline">ver tudo</Link>
                </div>
                <p v-if="audit.length === 0" class="px-5 py-6 text-sm text-muted-foreground">Nenhum registro.</p>
                <ul class="divide-y">
                    <li v-for="a in audit" :key="a.id" class="flex items-center gap-4 px-5 py-2.5 text-sm">
                        <span class="w-32 shrink-0 text-xs text-muted-foreground tabular-nums">{{ dt(a.created_at) }}</span>
                        <span class="flex-1">{{ a.label }}</span>
                        <span v-if="a.metadata?.reason" class="truncate text-xs text-muted-foreground">Motivo: {{ a.metadata.reason }}</span>
                    </li>
                </ul>
            </Card>
        </div>

        <Dialog v-model:open="editOpen" title="Editar empresa">
            <form class="space-y-4" @submit.prevent="saveEdit">
                <div class="space-y-2">
                    <Label for="e-name">Nome</Label>
                    <Input id="e-name" v-model="editForm.name" required />
                    <InputError :message="editForm.errors.name" />
                </div>
                <div class="space-y-2">
                    <Label for="e-doc">CNPJ/CPF</Label>
                    <Input id="e-doc" v-model="editForm.document" />
                    <InputError :message="editForm.errors.document" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="editOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="editForm.processing">Salvar</Button>
                </div>
            </form>
        </Dialog>

        <Dialog v-model:open="suspendOpen" :title="`Suspender ${company.name}?`" description="Os usuários serão desconectados e o widget deixará de carregar nos sites desta empresa. A ação pode ser desfeita reativando a empresa.">
            <form class="space-y-4" @submit.prevent="suspend">
                <div class="space-y-2">
                    <Label for="s-reason">Motivo (registrado na auditoria)</Label>
                    <Textarea id="s-reason" v-model="suspendForm.reason" rows="3" placeholder="Ex.: inadimplência, pedido do cliente…" />
                    <InputError :message="suspendForm.errors.reason" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="suspendOpen = false">Cancelar</Button>
                    <Button type="submit" variant="destructive" :disabled="suspendForm.processing"><Ban /> Suspender empresa</Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
