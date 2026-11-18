<script setup lang="ts">
import { AlertCircle, Check, CheckCheck, Clock } from 'lucide-vue-next';

import { cn, formatTime } from '@/lib/utils';
import type { Message } from '@/types';

defineProps<{ message: Message; mine: boolean }>();
defineEmits<{ retry: [message: Message] }>();
</script>

<template>
    <div v-if="message.sender_type === 'system'" class="my-2 text-center text-xs text-muted-foreground">
        — {{ message.body }} · {{ formatTime(message.created_at) }} —
    </div>
    <div v-else :class="cn('flex flex-col gap-1', mine ? 'items-end' : 'items-start')">
        <span class="px-1 text-xs text-muted-foreground">{{ message.author_name }}</span>
        <div
            :class="
                cn(
                    'max-w-[75%] rounded-2xl px-4 py-2 text-sm leading-relaxed whitespace-pre-wrap break-words shadow-xs',
                    mine ? 'rounded-br-sm bg-primary text-primary-foreground' : 'rounded-bl-sm border bg-card',
                    message.pending && 'opacity-70',
                )
            "
        >
            {{ message.body }}
        </div>
        <span class="flex items-center gap-1 px-1 text-[11px] text-muted-foreground">
            {{ formatTime(message.created_at) }}
            <template v-if="mine">
                <button v-if="message.failed" type="button" class="flex items-center gap-1 text-destructive" @click="$emit('retry', message)">
                    <AlertCircle class="size-3" /> Falhou — reenviar
                </button>
                <Clock v-else-if="message.pending" class="size-3" />
                <CheckCheck v-else-if="message.read_at" class="size-3 text-primary" />
                <Check v-else class="size-3" />
            </template>
        </span>
    </div>
</template>
