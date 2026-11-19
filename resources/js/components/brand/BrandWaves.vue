<script setup lang="ts">
/** Ondas da identidade visual (camadas azul-marinho, azul e azul claro). */
withDefaults(defineProps<{ navy?: boolean }>(), { navy: true });
</script>

<template>
    <svg viewBox="0 0 1440 320" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M0 210C260 150 520 260 800 220c280-40 420-170 640-150v250H0Z" fill="#3BA0FF" opacity=".55" />
        <path d="M0 250C300 200 560 290 860 250c260-35 400-140 580-130v200H0Z" fill="#1677F0" />
        <path v-if="navy" d="M0 285c320-30 600 25 900 0 230-20 380-70 540-80v115H0Z" fill="#0E2A63" />
    </svg>
</template>
