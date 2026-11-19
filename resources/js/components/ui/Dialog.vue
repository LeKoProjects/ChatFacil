<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';

defineProps<{ title: string; description?: string }>();
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
            <DialogContent
                class="fixed top-1/2 left-1/2 z-50 grid max-h-[90vh] w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 overflow-y-auto rounded-xl border bg-card p-6 shadow-lg data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
            >
                <div class="space-y-1">
                    <DialogTitle class="text-lg font-semibold">{{ title }}</DialogTitle>
                    <DialogDescription v-if="description" class="text-sm text-muted-foreground">{{ description }}</DialogDescription>
                </div>
                <slot />
                <DialogClose class="absolute top-4 right-4 rounded-sm opacity-70 hover:opacity-100" aria-label="Fechar">
                    <X class="size-4" />
                </DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
