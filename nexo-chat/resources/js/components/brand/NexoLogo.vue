<script setup lang="ts">
import { computed } from 'vue';

import NexoMark from './NexoMark.vue';

/**
 * Logotipo NEXO Chat: símbolo + marca empilhada ("NEXO" / "Chat").
 *  - on="light": NEXO em azul-marinho e Chat em azul (fundos claros)
 *  - on="dark":  NEXO e Chat em branco (fundos azul-marinho)
 */
const props = withDefaults(defineProps<{ on?: 'light' | 'dark'; size?: 'sm' | 'md' | 'lg' | 'xl' }>(), { on: 'light', size: 'md' });

const sizes = {
    sm: { mark: 'h-7', text: 'text-[15px]' },
    md: { mark: 'h-9', text: 'text-xl' },
    lg: { mark: 'h-12', text: 'text-[28px]' },
    xl: { mark: 'h-16', text: 'text-4xl' },
} as const;

const s = computed(() => sizes[props.size]);
</script>

<template>
    <span class="inline-flex items-center gap-2.5" aria-label="NEXO Chat">
        <NexoMark :class="[s.mark, 'w-auto shrink-0']" />
        <span :class="['font-display leading-[0.92] font-extrabold tracking-tight', s.text]">
            <span :class="['block', on === 'dark' ? 'text-white' : 'text-brand-navy dark:text-white']">NEXO</span>
            <span :class="['block', on === 'dark' ? 'text-white' : 'text-brand-blue']">Chat</span>
        </span>
    </span>
</template>
