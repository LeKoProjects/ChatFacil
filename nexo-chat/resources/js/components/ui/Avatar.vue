<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue';

import { cn, initials } from '@/lib/utils';

const props = defineProps<{ name: string; class?: HTMLAttributes['class'] }>();

// Cor estável derivada do nome (identificação visual rápida na fila).
const hue = computed(() => [...props.name].reduce((acc, c) => acc + c.charCodeAt(0), 0) % 360);
</script>

<template>
    <span
        :class="cn('inline-flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold', props.class)"
        :style="{ background: `oklch(0.9 0.05 ${hue})`, color: `oklch(0.35 0.1 ${hue})` }"
        :aria-label="name"
    >
        {{ initials(name) || '?' }}
    </span>
</template>
