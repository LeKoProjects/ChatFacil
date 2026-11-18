<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import BrandWaves from '@/components/brand/BrandWaves.vue';
import NexoMark from '@/components/brand/NexoMark.vue';
import Card from '@/components/ui/Card.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDuration } from '@/lib/utils';

const props = defineProps<{
    stats: {
        received: number;
        answered: number;
        missed: number;
        avg_first_response_seconds: number | null;
        avg_duration_seconds: number | null;
        waiting_now: number;
        open_now: number;
        agents_online: number;
        websites: number;
    };
    series: { day: string; total: number }[];
}>();

const kpis = computed(() => [
    { label: 'Na fila agora', value: props.stats.waiting_now, hint: 'aguardando atendimento' },
    { label: 'Em atendimento', value: props.stats.open_now, hint: 'conversas ativas' },
    { label: 'Atendentes online', value: props.stats.agents_online, hint: 'disponíveis agora' },
    { label: 'Recebidas (30 dias)', value: props.stats.received, hint: `${props.stats.answered} atendidas · ${props.stats.missed} perdidas` },
    { label: '1ª resposta média', value: formatDuration(props.stats.avg_first_response_seconds), hint: 'últimos 30 dias' },
    { label: 'Duração média', value: formatDuration(props.stats.avg_duration_seconds), hint: 'conversas finalizadas' },
]);

const max = computed(() => Math.max(1, ...props.series.map((s) => s.total)));
const dayLabel = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard">
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <section class="relative overflow-hidden rounded-2xl bg-brand-navy px-7 py-6 text-white shadow-lg shadow-brand-navy/20">
                <NexoMark mono class="pointer-events-none absolute -top-6 right-6 h-40 w-auto text-white/[0.07]" />
                <p class="text-sm text-[#b9c8e6]">Visão geral do atendimento</p>
                <h1 class="mt-1 text-2xl font-bold text-white">{{ stats.waiting_now }} na fila · {{ stats.open_now }} em atendimento</h1>
                <p class="mt-1 text-sm text-[#c7d6f2]">{{ stats.agents_online }} atendente(s) online agora</p>
                <BrandWaves :navy="false" class="pointer-events-none absolute inset-x-0 bottom-0 h-16 w-full" />
                <div class="h-6" />
            </section>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="k in kpis" :key="k.label" class="p-5">
                    <p class="text-sm text-muted-foreground">{{ k.label }}</p>
                    <p class="mt-2 font-display text-3xl font-bold text-heading tabular-nums">{{ k.value }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ k.hint }}</p>
                </Card>
            </div>

            <Card class="p-5">
                <div class="mb-4 flex items-baseline justify-between">
                    <h2 class="font-semibold">Conversas por dia</h2>
                    <span class="text-xs text-muted-foreground">últimos 14 dias</span>
                </div>
                <div class="flex h-48 items-end gap-2" role="img" aria-label="Gráfico de conversas por dia">
                    <div v-for="s in series" :key="s.day" class="group flex flex-1 flex-col items-center gap-1">
                        <span class="text-[11px] text-muted-foreground opacity-0 group-hover:opacity-100">{{ s.total }}</span>
                        <div class="w-full rounded-t-md bg-primary/80 transition-colors group-hover:bg-primary" :style="{ height: `${(s.total / max) * 100}%`, minHeight: s.total ? '4px' : '1px' }" />
                        <span class="text-[10px] text-muted-foreground">{{ dayLabel(s.day) }}</span>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
