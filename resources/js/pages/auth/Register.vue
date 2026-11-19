<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const form = useForm({
    company_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AuthLayout title="Cadastre sua empresa" description="Crie o ambiente da sua empresa e o primeiro administrador.">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-2">
                <Label for="company_name">Nome da empresa</Label>
                <Input id="company_name" v-model="form.company_name" required autofocus />
                <InputError :message="form.errors.company_name" />
            </div>
            <div class="space-y-2">
                <Label for="name">Seu nome</Label>
                <Input id="name" v-model="form.name" autocomplete="name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="space-y-2">
                <Label for="email">E-mail</Label>
                <Input id="email" v-model="form.email" type="email" autocomplete="username" required />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-2">
                    <Label for="password">Senha</Label>
                    <Input id="password" v-model="form.password" type="password" autocomplete="new-password" required />
                </div>
                <div class="space-y-2">
                    <Label for="password_confirmation">Confirmar</Label>
                    <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
                </div>
            </div>
            <InputError :message="form.errors.password" />
            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" /> Criar conta
            </Button>
        </form>
        <p class="text-center text-sm text-muted-foreground">
            Já tem conta? <Link href="/login" class="text-primary hover:underline">Entrar</Link>
        </p>
    </AuthLayout>
</template>
