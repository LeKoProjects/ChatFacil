<script setup lang="ts">
defineProps<{ label: string; id: string }>();
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <label :for="id" class="flex cursor-pointer items-center gap-2 text-sm select-none">
        <input :id="id" v-model="model" type="checkbox" class="size-4 rounded border-input accent-[var(--primary)]" />
        {{ label }}
    </label>
</template>
