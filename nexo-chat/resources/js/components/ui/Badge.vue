<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import type { HTMLAttributes } from 'vue';

import { cn } from '@/lib/utils';

const badge = cva('inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap', {
    variants: {
        variant: {
            default: 'border-transparent bg-primary/12 text-primary',
            secondary: 'border-transparent bg-secondary text-secondary-foreground',
            success: 'border-transparent bg-success/15 text-success',
            warning: 'border-transparent bg-warning/20 text-amber-700 dark:text-warning',
            destructive: 'border-transparent bg-destructive/12 text-destructive',
            outline: 'text-foreground',
        },
    },
    defaultVariants: { variant: 'default' },
});

const props = defineProps<{
    variant?: VariantProps<typeof badge>['variant'];
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span :class="cn(badge({ variant }), props.class)"><slot /></span>
</template>
