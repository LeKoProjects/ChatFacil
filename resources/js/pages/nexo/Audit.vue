<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';
import { ref, watch } from 'vue';

import EmptyState from '@/components/EmptyState.vue';
import Pagination, { type Paginated } from '@/components/Pagination.vue';
import Card from '@/components/ui/Card.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface LogRow {
    id: number;
    action: string;
    label: string;
    company_id: number | null;
    company_name: string | null;
    user_name: string | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
}

const props = defineProps<{
    logs: Paginated<LogRow>;
    filters: { company: number | null; action: string | null };
    companies: { id: number; name: string }[];
    actions: { value: string; label: string }[];
}>();

const company = ref(props.filters.company ? String(props.filters.company) : '');
const action = ref(props.filters.action ?? '');

watch([company, action], () => {
    router.get('/nexo/audit', { ...(company.value ? { company: company.value } : {}), ...(action.value ? { action: action.value } : {}) }, { preserveState: true, replace: true });
});

const dt = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' });

function details(meta: Record<string, unknown> | null): string {
    if (!meta) return '';
    return Object.entries(meta)
        .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : String(v)}`)
        .join(' · ');
}
</script>

<template>
    <Head title="Auditoria" />
    <AppLayout title="Auditoria da plataforma">
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <div>
                <h1 class="text-xl font-bold">Auditoria</h1>
                <p class="text-sm text-muted-foreground">Ações administrativas registradas em todas as empresas.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <select v-model="company" class="h-9 min-w-56 rounded-md border bg-card px-2 text-sm" aria-label="Filtrar por empresa">
                    <option value="">Todas as empresas</option>
                    <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>
                <select v-model="action" class="h-9 min-w-56 rounded-md border bg-card px-2 text-sm" aria-label="Filtrar por ação">
                    <option value="">Todas as ações</option>
                    <option v-for="a in actions" :key="a.value" :value="a.value">{{ a.label }}</option>
                </select>
            </div>

            <Card class="overflow-x-auto">
                <EmptyState v-if="logs.data.length === 0" :icon="ScrollText" title="Nenhum registro" description="Não há ações para os filtros selecionados." />
                <table v-else class="w-full text-sm">
                    <thead class="border-b text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th class="px-4 py-3 font-medium">Quando</th>
                            <th class="px-4 py-3 font-medium">Ação</th>
                            <th class="px-4 py-3 font-medium">Empresa</th>
                            <th class="px-4 py-3 font-medium">Usuário</th>
                            <th class="px-4 py-3 font-medium">Detalhes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="l in logs.data" :key="l.id">
                            <td class="px-4 py-2.5 whitespace-nowrap text-muted-foreground tabular-nums">{{ dt(l.created_at) }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ l.label }}</td>
                            <td class="px-4 py-2.5">
                                <Link v-if="l.company_id" :href="`/nexo/companies/${l.company_id}`" class="text-primary hover:underline">{{ l.company_name ?? `#${l.company_id}` }}</Link>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-2.5">{{ l.user_name ?? 'Sistema' }}</td>
                            <td class="max-w-80 truncate px-4 py-2.5 text-xs text-muted-foreground" :title="details(l.metadata)">
                                {{ details(l.metadata) }}<template v-if="l.ip_address"> · IP {{ l.ip_address }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Pagination :page="logs" />
        </div>
    </AppLayout>
</template>
