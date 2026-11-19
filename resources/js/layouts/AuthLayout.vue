<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MessagesSquare, ShieldCheck, Zap } from 'lucide-vue-next';

import BrandWaves from '@/components/brand/BrandWaves.vue';
import NexoLogo from '@/components/brand/NexoLogo.vue';
import NexoMark from '@/components/brand/NexoMark.vue';

defineProps<{ title: string; description?: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="grid min-h-dvh bg-card lg:grid-cols-[1.05fr_1fr]">
        <!-- Painel institucional (inspirado na pasta da papelaria) -->
        <aside class="relative hidden overflow-hidden bg-brand-navy text-white lg:flex lg:flex-col">
            <NexoMark mono class="pointer-events-none absolute -right-16 bottom-28 h-80 w-auto text-white/[0.06]" />
            <div class="relative z-10 flex flex-1 flex-col justify-center px-14 pb-40">
                <NexoLogo on="dark" size="xl" />
                <p class="mt-8 border-l-2 border-brand-sky pl-5 font-display text-xl leading-snug font-medium text-white/90">
                    Atendimento online<br />para o seu negócio
                </p>
                <ul class="mt-12 max-w-md space-y-4 text-sm text-[#c7d6f2]">
                    <li class="flex gap-3"><Zap class="size-5 shrink-0 text-brand-sky" /> Mensagens instantâneas entre visitantes e sua equipe.</li>
                    <li class="flex gap-3"><MessagesSquare class="size-5 shrink-0 text-brand-sky" /> Filas, atribuição e histórico completo de conversas.</li>
                    <li class="flex gap-3"><ShieldCheck class="size-5 shrink-0 text-brand-sky" /> Dados isolados por empresa, com controle de acesso.</li>
                </ul>
            </div>
            <BrandWaves :navy="false" class="absolute inset-x-0 bottom-0 h-44 w-full" />
            <p class="absolute bottom-4 left-14 z-10 text-xs text-white/80">© {{ new Date().getFullYear() }} Grupo NEXO · chat.nexo.com.br</p>
        </aside>

        <!-- Formulário -->
        <main class="relative flex flex-col items-center justify-center overflow-hidden px-6 py-12">
            <div class="w-full max-w-sm space-y-7">
                <NexoLogo size="lg" class="lg:hidden" />
                <div class="space-y-1.5">
                    <h1 class="text-2xl font-bold">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
                </div>
                <slot />
            </div>
            <BrandWaves class="pointer-events-none absolute inset-x-0 bottom-0 h-20 w-full opacity-90 lg:hidden" />
        </main>
    </div>
</template>
