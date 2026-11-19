<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, Plus, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import Pagination, { type Paginated } from '@/components/Pagination.vue';
import Badge from '@/components/ui/Badge.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface CompanyRow {
    id: number;
    name: string;
    slug: string;
    document: string | null;
    status: 'active' | 'suspended';
    users_count: number;
    websites_count: number;
    conversations_30d: number;
    created_at: string;
    suspended_at: string | null;
}

const props = defineProps<{ companies: Paginated<CompanyRow>; filters: { q: string; status: string | null } }>();

const q = ref(props.filters.q);
const status = ref(props.filters.status ?? '');

function apply() {
    router.get('/nexo/companies', { ...(q.value ? { q: q.value } : {}), ...(status.value ? { status: status.value } : {}) }, { preserveState: true, replace: true });
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 350);
});
watch(status, apply);

const open = ref(false);
const form = useForm({ company_name: '', document: '', name: '', email: '', password: '' });

function create() {
    form.post('/nexo/companies', { onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Head title="Empresas" />
    <AppLayout title="Empresas clientes">
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold">Empresas</h1>
                    <p class="text-sm text-muted-foreground">Cadastre, acompanhe, suspenda e reative clientes da plataforma.</p>
                </div>
                <Button @click="open = true"><Plus /> Nova empresa</Button>
            </div>

            <div class="flex flex-wrap gap-3">
                <div class="relative min-w-64 flex-1">
                    <Search class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="q" placeholder="Buscar por nome, CNPJ ou e-mail de usuário" class="pl-8" aria-label="Buscar empresas" />
                </div>
                <select v-model="status" class="h-9 rounded-md border bg-card px-2 text-sm" aria-label="Filtrar por situação">
                    <option value="">Todas as situações</option>
                    <option value="active">Ativas</option>
                    <option value="suspended">Suspensas</option>
                </select>
            </div>

            <Card class="overflow-x-auto">
                <EmptyState v-if="companies.data.length === 0" :icon="Building2" title="Nenhuma empresa encontrada" description="Ajuste a busca ou cadastre uma nova empresa." />
                <table v-else class="w-full text-sm">
                    <thead class="border-b text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th class="px-4 py-3 font-medium">Empresa</th>
                            <th class="px-4 py-3 font-medium">Situação</th>
                            <th class="px-4 py-3 text-right font-medium">Usuários</th>
                            <th class="px-4 py-3 text-right font-medium">Sites</th>
                            <th class="px-4 py-3 text-right font-medium">Conversas 30d</th>
                            <th class="px-4 py-3 font-medium">Cliente desde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="c in companies.data" :key="c.id" class="cursor-pointer hover:bg-accent/50" @click="router.visit(`/nexo/companies/${c.id}`)">
                            <td class="px-4 py-3">
                                <Link :href="`/nexo/companies/${c.id}`" class="font-medium text-heading hover:underline" @click.stop>{{ c.name }}</Link>
                                <p class="text-xs text-muted-foreground">{{ c.document ?? c.slug }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="c.status === 'active' ? 'success' : 'destructive'">{{ c.status === 'active' ? 'Ativa' : 'Suspensa' }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ c.users_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ c.websites_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ c.conversations_30d }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ new Date(c.created_at).toLocaleDateString('pt-BR') }}</td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Pagination :page="companies" />
        </div>

        <Dialog v-model:open="open" title="Nova empresa" description="Cria a empresa e o primeiro administrador, que poderá cadastrar sites e atendentes.">
            <form class="space-y-4" @submit.prevent="create">
                <div class="grid grid-cols-[1.4fr_1fr] gap-3">
                    <div class="space-y-2">
                        <Label for="c-name">Nome da empresa</Label>
                        <Input id="c-name" v-model="form.company_name" required />
                        <InputError :message="form.errors.company_name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="c-doc">CNPJ/CPF</Label>
                        <Input id="c-doc" v-model="form.document" />
                        <InputError :message="form.errors.document" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="c-admin">Nome do administrador</Label>
                    <Input id="c-admin" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="space-y-2">
                    <Label for="c-email">E-mail do administrador</Label>
                    <Input id="c-email" v-model="form.email" type="email" required />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="space-y-2">
                    <Label for="c-pass">Senha inicial</Label>
                    <Input id="c-pass" v-model="form.password" type="password" autocomplete="new-password" required />
                    <InputError :message="form.errors.password" />
                </div>
                <p class="text-xs text-muted-foreground">O administrador recebe acesso imediato com o e-mail e a senha acima.</p>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="open = false">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing">Criar empresa</Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
