<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const form = useForm({ email: '' });
</script>

<template>
    <AuthLayout title="Recuperar senha" description="Enviaremos um link de redefinição para o seu e-mail.">
        <p v-if="page.props.flash.status" class="rounded-md bg-success/10 p-3 text-sm text-success">{{ page.props.flash.status }}</p>
        <form class="space-y-4" @submit.prevent="form.post('/forgot-password')">
            <div class="space-y-2">
                <Label for="email">E-mail</Label>
                <Input id="email" v-model="form.email" type="email" required autofocus />
                <InputError :message="form.errors.email" />
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">Enviar link</Button>
        </form>
        <p class="text-center text-sm"><Link href="/login" class="text-primary hover:underline">Voltar ao login</Link></p>
    </AuthLayout>
</template>
