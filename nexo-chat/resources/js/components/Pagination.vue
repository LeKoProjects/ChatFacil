<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { cn } from '@/lib/utils';

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{ page: Paginated<unknown> }>();
</script>

<template>
    <nav v-if="page.last_page > 1 || page.total > 0" class="flex items-center justify-between gap-4 text-sm" aria-label="Paginação">
        <p class="text-muted-foreground">{{ page.from ?? 0 }}–{{ page.to ?? 0 }} de {{ page.total }}</p>
        <div v-if="page.last_page > 1" class="flex items-center gap-2">
            <component
                :is="page.prev_page_url ? Link : 'span'"
                :href="page.prev_page_url ?? undefined"
                preserve-scroll
                :class="cn('rounded-md border px-3 py-1.5', page.prev_page_url ? 'hover:bg-accent' : 'opacity-40')"
            >
                Anterior
            </component>
            <span class="text-muted-foreground">{{ page.current_page }} / {{ page.last_page }}</span>
            <component
                :is="page.next_page_url ? Link : 'span'"
                :href="page.next_page_url ?? undefined"
                preserve-scroll
                :class="cn('rounded-md border px-3 py-1.5', page.next_page_url ? 'hover:bg-accent' : 'opacity-40')"
            >
                Próxima
            </component>
        </div>
    </nav>
</template>
