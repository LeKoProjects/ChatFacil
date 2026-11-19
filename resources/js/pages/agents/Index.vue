<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Trash2, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Badge from '@/components/ui/Badge.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Availability, UserRole } from '@/types';

interface Agent {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    availability: Availability;
    max_concurrent_chats: number;
    is_active: boolean;
    is_self: boolean;
}

defineProps<{ agents: Agent[]; roles: { value: UserRole; label: string }[] }>();

const availabilityLabel: Record<Availability, string> = { online: 'Online', busy: 'Ocupado', away: 'Ausente', offline: 'Offline' };
const availabilityVariant = { online: 'success', busy: 'warning', away: 'secondary', offline: 'outline' } as const;

const open = ref(false);
const editing = ref<Agent | null>(null);
const form = useForm({ name: '', email: '', password: '', role: 'agent' as UserRole, max_concurrent_chats: 5, is_active: true });

function edit(agent: Agent | null) {
    editing.value = agent;
    form.clearErrors();
    form.name = agent?.name ?? '';
    form.email = agent?.email ?? '';
    form.password = '';
    form.role = agent?.role ?? 'agent';
    form.max_concurrent_chats = agent?.max_concurrent_chats ?? 5;
    form.is_active = agent?.is_active ?? true;
    open.value = true;
}

function save() {
    const opts = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) form.put(`/agents/${editing.value.id}`, opts);
    else form.post('/agents', opts);
}

function remove(agent: Agent) {
    if (confirm(`Remover ${agent.name}? O acesso será revogado.`)) {
        router.delete(`/agents/${agent.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Atendentes" />
    <AppLayout title="Atendentes">
        <div class="mx-auto max-w-5xl space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Atendentes</h1>
                    <p class="text-sm text-muted-foreground">Gerencie quem atende as conversas da sua empresa.</p>
                </div>
                <Button @click="edit(null)"><UserPlus /> Novo atendente</Button>
            </div>

            <Card class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nome</th>
                            <th class="px-4 py-3 font-medium">Perfil</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Capacidade</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="a in agents" :key="a.id" :class="!a.is_active ? 'opacity-60' : ''">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <Avatar :name="a.name" class="size-8" />
                                    <div>
                                        <p class="font-medium">{{ a.name }} <span v-if="a.is_self" class="text-xs text-muted-foreground">(você)</span></p>
                                        <p class="text-xs text-muted-foreground">{{ a.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ a.role_label }}</td>
                            <td class="px-4 py-3">
                                <Badge v-if="!a.is_active" variant="destructive">Inativo</Badge>
                                <Badge v-else :variant="availabilityVariant[a.availability]">{{ availabilityLabel[a.availability] }}</Badge>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ a.max_concurrent_chats }} simultâneas</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon-sm" :aria-label="'Editar ' + a.name" @click="edit(a)"><Pencil /></Button>
                                    <Button v-if="!a.is_self" variant="ghost" size="icon-sm" :aria-label="'Remover ' + a.name" @click="remove(a)"><Trash2 class="text-destructive" /></Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>
        </div>

        <Dialog v-model:open="open" :title="editing ? 'Editar atendente' : 'Novo atendente'">
            <form class="space-y-4" @submit.prevent="save">
                <div class="space-y-2">
                    <Label for="a-name">Nome</Label>
                    <Input id="a-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="space-y-2">
                    <Label for="a-email">E-mail</Label>
                    <Input id="a-email" v-model="form.email" type="email" required />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="space-y-2">
                    <Label for="a-password">{{ editing ? 'Nova senha (opcional)' : 'Senha inicial' }}</Label>
                    <Input id="a-password" v-model="form.password" type="password" autocomplete="new-password" :required="!editing" />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <Label for="a-role">Perfil</Label>
                        <select id="a-role" v-model="form.role" class="h-9 w-full rounded-md border bg-card px-2 text-sm" :disabled="editing?.is_self">
                            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="a-max">Conversas simultâneas</Label>
                        <Input id="a-max" v-model.number="form.max_concurrent_chats" type="number" min="1" max="50" />
                        <InputError :message="form.errors.max_concurrent_chats" />
                    </div>
                </div>
                <Checkbox v-if="editing && !editing.is_self" id="a-active" v-model="form.is_active" label="Acesso ativo" />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="open = false">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing">Salvar</Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
