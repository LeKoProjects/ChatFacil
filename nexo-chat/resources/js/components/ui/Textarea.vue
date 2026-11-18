<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
const model = defineModel<string | null>();
</script>

<template>
    <textarea
        v-model="model"
        :class="
            cn(
                'flex min-h-16 w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40',
                props.class,
            )
        "
    />
</template>
