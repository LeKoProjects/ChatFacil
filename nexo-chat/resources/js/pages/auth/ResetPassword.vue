<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps<{ token: string; email: string | null }>();
const form = useForm({ token: props.token, email: props.email ?? '', password: '', password_confirmation: '' });
</script>

<template>
    <AuthLayout title="Definir nova senha">
        <form class="space-y-4" @submit.prevent="form.post('/reset-password')">
            <div class="space-y-2">
                <Label for="email">E-mail</Label>
                <Input id="email" v-model="form.email" type="email" required />
                <InputError :message="form.errors.email" />
            </div>
            <div class="space-y-2">
                <Label for="password">Nova senha</Label>
                <Input id="password" v-model="form.password" type="password" autocomplete="new-password" required />
                <InputError :message="form.errors.password" />
            </div>
            <div class="space-y-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">Salvar senha</Button>
        </form>
    </AuthLayout>
</template>
