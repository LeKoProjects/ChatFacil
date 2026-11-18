<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <AuthLayout title="Entrar" description="Acesse a central de atendimento.">
        <p v-if="page.props.flash.status" class="rounded-md bg-success/10 p-3 text-sm text-success">{{ page.props.flash.status }}</p>
        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="email">E-mail</Label>
                <Input id="email" v-model="form.email" type="email" autocomplete="username" required autofocus />
                <InputError :message="form.errors.email" />
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Senha</Label>
                    <Link href="/forgot-password" class="text-xs text-primary hover:underline">Esqueceu a senha?</Link>
                </div>
                <Input id="password" v-model="form.password" type="password" autocomplete="current-password" required />
                <InputError :message="form.errors.password" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.remember" type="checkbox" class="size-4 accent-[var(--primary)]" /> Manter conectado
            </label>
            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" /> Entrar
            </Button>
        </form>
        <p class="text-center text-sm text-muted-foreground">
            Ainda não usa o NEXO Chat? <Link href="/register" class="text-primary hover:underline">Cadastre sua empresa</Link>
        </p>
    </AuthLayout>
</template>
