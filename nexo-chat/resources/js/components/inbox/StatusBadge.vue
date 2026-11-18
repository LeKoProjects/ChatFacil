<script setup lang="ts">
import Badge from '@/components/ui/Badge.vue';
import type { ConversationStatus } from '@/types';

defineProps<{ status: ConversationStatus; label: string }>();

const variants = { waiting: 'warning', open: 'success', pending: 'secondary', closed: 'outline' } as const;
</script>

<template>
    <Badge :variant="variants[status]">{{ label }}</Badge>
</template>
