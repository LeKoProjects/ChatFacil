<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Globe, LayoutDashboard, LogOut, MessagesSquare, Moon, ScrollText, Sun, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast, Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';

import BrandWaves from '@/components/brand/BrandWaves.vue';
import NexoLogo from '@/components/brand/NexoLogo.vue';
import NexoMark from '@/components/brand/NexoMark.vue';
import Avatar from '@/components/ui/Avatar.vue';
import { useTheme } from '@/composables/useTheme';
import { api } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { Availability, SharedProps } from '@/types';

defineProps<{ title?: string; fullHeight?: boolean }>();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user!);
const isAdmin = computed(() => user.value.role === 'company_admin');
const isPlatform = computed(() => user.value.role === 'platform_admin');
const home = computed(() => (isPlatform.value ? '/nexo' : '/inbox'));
const roleLabel = computed(() => (isPlatform.value ? 'Administração NEXO' : isAdmin.value ? 'Administrador' : 'Atendente'));
const { toggle } = useTheme();

const nav = computed(() =>
    isPlatform.value
        ? [
              { href: '/nexo', label: 'Visão geral', icon: LayoutDashboard, show: true, exact: true },
              { href: '/nexo/companies', label: 'Empresas', icon: Building2, show: true },
              { href: '/nexo/audit', label: 'Auditoria', icon: ScrollText, show: true },
          ]
        : [
              { href: '/inbox', label: 'Atendimento', icon: MessagesSquare, show: true },
              { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, show: isAdmin.value },
              { href: '/websites', label: 'Sites e widgets', icon: Globe, show: isAdmin.value },
              { href: '/agents', label: 'Atendentes', icon: Users, show: isAdmin.value },
          ],
);

const isActive = (href: string, exact = false) =>
    page.url === href || page.url.startsWith(href + '?') || (!exact && page.url.startsWith(href + '/'));

const availabilityOptions: { value: Availability; label: string; dot: string }[] = [
    { value: 'online', label: 'Online', dot: 'bg-success' },
    { value: 'busy', label: 'Ocupado', dot: 'bg-warning' },
    { value: 'away', label: 'Ausente', dot: 'bg-muted-foreground' },
    { value: 'offline', label: 'Offline', dot: 'bg-destructive' },
];

const availability = ref<Availability>(user.value.availability);
const currentDot = computed(() => availabilityOptions.find((o) => o.value === availability.value)?.dot ?? '');

async function changeAvailability(value: Availability) {
    const previous = availability.value;
    availability.value = value;
    try {
        await api('PUT', '/me/availability', { availability: value });
        toast.success('Disponibilidade atualizada');
    } catch {
        availability.value = previous;
        toast.error('Não foi possível alterar a disponibilidade');
    }
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { immediate: true, deep: true },
);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex h-dvh overflow-hidden bg-background">
        <!-- Barra lateral compacta -->
        <aside class="relative flex w-[72px] shrink-0 flex-col items-center gap-2 overflow-hidden bg-sidebar py-4 text-sidebar-foreground">
            <Link :href="home" class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-white/[0.06] ring-1 ring-white/10" aria-label="NEXO Chat — início">
                <NexoMark class="h-7 w-auto" />
            </Link>
            <template v-for="item in nav" :key="item.href">
                <Link
                    v-if="item.show"
                    :href="item.href"
                    :title="item.label"
                    :aria-label="item.label"
                    :class="
                        cn(
                            'relative flex size-11 items-center justify-center rounded-xl transition-colors hover:bg-sidebar-active hover:text-white',
                            isActive(item.href, 'exact' in item && item.exact) && 'bg-brand-blue text-white shadow-lg shadow-brand-blue/30 hover:bg-brand-blue',
                        )
                    "
                >
                    <component :is="item.icon" class="size-5" />
                </Link>
            </template>
            <BrandWaves :navy="false" class="pointer-events-none absolute inset-x-0 bottom-0 h-24 w-full opacity-25" />
            <div class="relative z-10 mt-auto flex flex-col items-center gap-2">
                <button type="button" class="flex size-10 items-center justify-center rounded-lg hover:bg-sidebar-active hover:text-white" title="Alternar tema" aria-label="Alternar tema" @click="toggle">
                    <Sun class="hidden size-5 dark:block" />
                    <Moon class="size-5 dark:hidden" />
                </button>
                <button type="button" class="flex size-10 items-center justify-center rounded-lg hover:bg-sidebar-active hover:text-white" title="Sair" aria-label="Sair" @click="logout">
                    <LogOut class="size-5" />
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Barra superior: empresa, disponibilidade, perfil -->
            <header class="flex h-16 shrink-0 items-center gap-5 border-b bg-card px-5">
                <NexoLogo size="sm" class="hidden sm:inline-flex" />
                <span class="hidden h-8 w-px bg-border sm:block" />
                <div class="min-w-0">
                    <p class="truncate font-display text-sm font-bold text-heading">{{ title }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ isPlatform ? 'Painel central · Grupo NEXO' : user.company?.name }}</p>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <label v-if="!isPlatform" class="flex items-center gap-2 rounded-md border px-2 py-1 text-sm">
                        <span :class="cn('size-2 rounded-full', currentDot)" />
                        <select
                            :value="availability"
                            class="bg-transparent text-sm outline-none"
                            aria-label="Disponibilidade"
                            @change="changeAvailability(($event.target as HTMLSelectElement).value as Availability)"
                        >
                            <option v-for="o in availabilityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </label>
                    <div class="flex items-center gap-2">
                        <Avatar :name="user.name" class="size-8" />
                        <div class="hidden text-right leading-tight md:block">
                            <p class="text-sm font-medium">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ roleLabel }}</p>
                        </div>
                    </div>
                </div>
            </header>

            <main :class="cn('min-h-0 flex-1', fullHeight ? 'overflow-hidden' : 'overflow-y-auto')">
                <slot />
            </main>
        </div>
        <Toaster rich-colors position="top-right" />
    </div>
</template>
