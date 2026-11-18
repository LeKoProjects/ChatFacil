<script setup lang="ts">
/**
 * Símbolo NEXO Chat: balão de conversa com três pontos.
 * `tone="light"` para fundos claros/coloridos; `mono` para uso em uma cor (currentColor).
 */
import { useId } from 'vue';

withDefaults(defineProps<{ mono?: boolean; dots?: string }>(), { mono: false, dots: 'currentColor' });

const gid = `nexo-mark-${useId()}`;
</script>

<template>
    <svg viewBox="0 0 64 56" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs v-if="!mono">
            <linearGradient :id="gid" x1="4" y1="4" x2="60" y2="52" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#1677F0" />
                <stop offset="1" stop-color="#3BA0FF" />
            </linearGradient>
        </defs>
        <path
            d="M19 5h26c7.7 0 14 6.3 14 14v6c0 7.7-6.3 14-14 14H25.5L13 50.5V38.2C8 36 5 31 5 25v-6C5 11.3 11.3 5 19 5Z"
            :stroke="mono ? 'currentColor' : `url(#${gid})`"
            stroke-width="5.5"
            stroke-linejoin="round"
        />
        <g :fill="mono ? 'currentColor' : dots === 'currentColor' ? `url(#${gid})` : dots">
            <circle cx="21" cy="22" r="3.6" />
            <circle cx="32" cy="22" r="3.6" />
            <circle cx="43" cy="22" r="3.6" />
        </g>
    </svg>
</template>
