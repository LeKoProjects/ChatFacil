<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import BrandWaves from '@/components/brand/BrandWaves.vue';
import NexoMark from '@/components/brand/NexoMark.vue';
import Badge from '@/components/ui/Badge.vue';
import Card from '@/components/ui/Card.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    metrics: {
        companies: { total: number; active: number; suspended: number; new_30d: number };
        users: { total: number; online: number };
        widgets_active: number;
        conversations: { today: number; last_30d: number; active_now: number };
        messages_30d: number;
        series: { day: string; total: number }[];
        top_companies: { id: number; name: string; status: 'active' | 'suspended'; conversations_30d: number }[];
    };
}>();

const m = computed(() => props.metrics);
const nf = new Intl.NumberFormat('pt-BR');

const kpis = computed(() => [
    { label: 'Empresas ativas', value: m.value.companies.active, hint: `${m.value.companies.suspended} suspensa(s) · ${m.value.companies.new_30d} nova(s) em 30 dias` },
    { label: 'Usuários das empresas', value: m.value.users.total, hint: `${m.value.users.online} atendente(s) online agora` },
    { label: 'Widgets ativos', value: m.value.widgets_active, hint: 'instalados nos sites clientes' },
    { label: 'Conversas hoje', value: m.value.conversations.today, hint: `${m.value.conversations.active_now} ativa(s) agora` },
    { label: 'Conversas (30 dias)', value: m.value.conversations.last_30d, hint: 'todas as empresas' },
    { label: 'Mensagens (30 dias)', value: m.value.messages_30d, hint: 'volume da plataforma' },
]);

const max = computed(() => Math.max(1, ...m.value.series.map((s) => s.total)));
const dayLabel = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
</script>

<template>
    <Head title="Painel NEXO" />
    <AppLayout title="Visão geral da plataforma">
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <section class="relative overflow-hidden rounded-2xl bg-brand-navy px-7 py-6 text-white shadow-lg shadow-brand-navy/20">
                <NexoMark mono class="pointer-events-none absolute -top-6 right-6 h-40 w-auto text-white/[0.07]" />
                <p class="text-sm text-[#b9c8e6]">Painel central · Grupo NEXO</p>
                <h1 class="mt-1 text-2xl font-bold text-white">{{ m.companies.total }} empresas na plataforma</h1>
                <p class="mt-1 text-sm text-[#c7d6f2]">{{ m.conversations.active_now }} conversa(s) em andamento agora em todos os clientes</p>
                <BrandWaves :navy="false" class="pointer-events-none absolute inset-x-0 bottom-0 h-16 w-full" />
                <div class="h-6" />
            </section>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="k in kpis" :key="k.label" class="p-5">
                    <p class="text-sm text-muted-foreground">{{ k.label }}</p>
                    <p class="mt-2 font-display text-3xl font-bold text-heading tabular-nums">{{ nf.format(k.value) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ k.hint }}</p>
                </Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <Card class="p-5">
                    <div class="mb-4 flex items-baseline justify-between">
                        <h2 class="font-semibold">Conversas por dia</h2>
                        <span class="text-xs text-muted-foreground">últimos 14 dias · todas as empresas</span>
                    </div>
                    <div class="flex h-48 items-end gap-2" role="img" aria-label="Gráfico de conversas por dia na plataforma">
                        <div v-for="s in m.series" :key="s.day" class="group flex h-full flex-1 flex-col items-center justify-end gap-1">
                            <span class="text-[11px] text-muted-foreground opacity-0 group-hover:opacity-100">{{ s.total }}</span>
                            <div class="w-full rounded-t-md bg-brand-blue/80 transition-colors group-hover:bg-brand-blue" :style="{ height: `${(s.total / max) * 85}%`, minHeight: s.total ? '4px' : '1px' }" />
                            <span class="text-[10px] text-muted-foreground">{{ dayLabel(s.day) }}</span>
                        </div>
                    </div>
                </Card>

                <Card class="p-5">
                    <div class="mb-3 flex items-baseline justify-between">
                        <h2 class="font-semibold">Empresas mais ativas</h2>
                        <Link href="/nexo/companies" class="text-xs text-primary hover:underline">ver todas</Link>
                    </div>
                    <p v-if="m.top_companies.length === 0" class="text-sm text-muted-foreground">Nenhuma empresa ainda.</p>
                    <ol class="space-y-2">
                        <li v-for="(c, i) in m.top_companies" :key="c.id">
                            <Link :href="`/nexo/companies/${c.id}`" class="flex items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-accent">
                                <span class="w-4 text-xs text-muted-foreground tabular-nums">{{ i + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ c.name }}</span>
                                <Badge v-if="c.status === 'suspended'" variant="destructive">Suspensa</Badge>
                                <span class="text-sm text-muted-foreground tabular-nums">{{ c.conversations_30d }}</span>
                            </Link>
                        </li>
                    </ol>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
