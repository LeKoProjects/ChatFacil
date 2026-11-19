<script setup lang="ts">
defineProps<{ message?: string | null }>();
</script>

<template>
    <p v-if="message" class="text-xs text-destructive" role="alert">{{ message }}</p>
</template>
