<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Globe, Pencil, Plus, Settings2, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import Badge from '@/components/ui/Badge.vue';
import { Button } from '@/components/ui/button';
import Card from '@/components/ui/Card.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Textarea from '@/components/ui/Textarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface WidgetData {
    id: number;
    name: string;
    public_key: string;
    title: string;
    primary_color: string;
    position: 'left' | 'right';
    welcome_message: string | null;
    offline_message: string | null;
    ask_name: boolean;
    ask_email: boolean;
    ask_phone: boolean;
    require_email: boolean;
    sound_enabled: boolean;
    is_active: boolean;
    snippet: string;
}
interface WebsiteData {
    id: number;
    name: string;
    url: string;
    domains: string[];
    widgets: WidgetData[];
}

defineProps<{ websites: WebsiteData[] }>();

// ---- Site (criar/editar) ----
const siteOpen = ref(false);
const editingSite = ref<WebsiteData | null>(null);
const domainsPlaceholder = ['suaempresa.com.br', '*.suaempresa.com.br'].join('\n');
const urlPlaceholder = 'https://www.suaempresa.com.br';
const formSite = useForm({ name: '', url: '', domains: '' });

function openSite(site: WebsiteData | null) {
    editingSite.value = site;
    formSite.clearErrors();
    formSite.name = site?.name ?? '';
    formSite.url = site?.url ?? '';
    formSite.domains = site?.domains.join('\n') ?? '';
    siteOpen.value = true;
}

function saveSite() {
    const opts = { preserveScroll: true, onSuccess: () => (siteOpen.value = false) };
    if (editingSite.value) formSite.put(`/websites/${editingSite.value.id}`, opts);
    else formSite.post('/websites', opts);
}

function removeSite(site: WebsiteData) {
    if (confirm(`Remover o site "${site.name}"? O widget deixará de funcionar.`)) {
        router.delete(`/websites/${site.id}`, { preserveScroll: true });
    }
}

// ---- Widget ----
const widgetOpen = ref(false);
const formWidget = useForm({
    id: 0,
    name: '',
    title: '',
    primary_color: '#1677F0',
    position: 'right' as 'left' | 'right',
    welcome_message: '' as string | null,
    offline_message: '' as string | null,
    ask_name: true,
    ask_email: true,
    ask_phone: false,
    require_email: false,
    sound_enabled: true,
    is_active: true,
});

function openWidget(w: WidgetData) {
    formWidget.clearErrors();
    Object.assign(formWidget, { ...w });
    widgetOpen.value = true;
}

function saveWidget() {
    formWidget.put(`/widgets/${formWidget.id}`, { preserveScroll: true, onSuccess: () => (widgetOpen.value = false) });
}

const copied = ref<string | null>(null);
async function copy(snippet: string, key: string) {
    try {
        await navigator.clipboard.writeText(snippet);
        copied.value = key;
        toast.success('Código copiado');
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        toast.error('Não foi possível copiar');
    }
}
</script>

<template>
    <Head title="Sites e widgets" />
    <AppLayout title="Sites e widgets">
        <div class="mx-auto max-w-5xl space-y-6 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Sites e widgets</h1>
                    <p class="text-sm text-muted-foreground">Cadastre os sites onde o chat será instalado e copie o código de instalação.</p>
                </div>
                <Button @click="openSite(null)"><Plus /> Novo site</Button>
            </div>

            <Card v-if="websites.length === 0">
                <EmptyState :icon="Globe" title="Nenhum site cadastrado" description="Cadastre o primeiro site para gerar o widget e o script de instalação.">
                    <Button @click="openSite(null)"><Plus /> Cadastrar site</Button>
                </EmptyState>
            </Card>

            <Card v-for="site in websites" :key="site.id" class="divide-y">
                <div class="flex flex-wrap items-start gap-4 p-5">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold">{{ site.name }}</h2>
                        <p class="text-sm text-muted-foreground">{{ site.url }}</p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <Badge v-for="d in site.domains" :key="d" variant="secondary">{{ d }}</Badge>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="openSite(site)"><Pencil /> Editar</Button>
                        <Button variant="ghost" size="icon-sm" aria-label="Remover site" @click="removeSite(site)"><Trash2 class="text-destructive" /></Button>
                    </div>
                </div>
                <div v-for="w in site.widgets" :key="w.id" class="space-y-3 p-5">
                    <div class="flex items-center gap-3">
                        <span class="size-4 rounded-full border" :style="{ background: w.primary_color }" />
                        <span class="font-medium">{{ w.name }}</span>
                        <Badge :variant="w.is_active ? 'success' : 'outline'">{{ w.is_active ? 'Ativo' : 'Inativo' }}</Badge>
                        <Button variant="outline" size="sm" class="ml-auto" @click="openWidget(w)"><Settings2 /> Personalizar</Button>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-muted-foreground">Cole antes do fechamento da tag body em todas as páginas do site:</p>
                        <div class="flex items-stretch gap-2">
                            <code class="flex-1 overflow-x-auto rounded-md bg-muted px-3 py-2 text-xs whitespace-nowrap">{{ w.snippet }}</code>
                            <Button variant="outline" size="icon" :aria-label="'Copiar código ' + w.name" @click="copy(w.snippet, w.public_key)">
                                <Check v-if="copied === w.public_key" class="text-success" />
                                <Copy v-else />
                            </Button>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <Dialog v-model:open="siteOpen" :title="editingSite ? 'Editar site' : 'Novo site'" description="Somente os domínios autorizados poderão carregar o widget.">
            <form class="space-y-4" @submit.prevent="saveSite">
                <div class="space-y-2">
                    <Label for="site-name">Nome</Label>
                    <Input id="site-name" v-model="formSite.name" required />
                    <InputError :message="formSite.errors.name" />
                </div>
                <div class="space-y-2">
                    <Label for="site-url">Endereço principal</Label>
                    <Input id="site-url" v-model="formSite.url" :placeholder="urlPlaceholder" required />
                    <InputError :message="formSite.errors.url" />
                </div>
                <div class="space-y-2">
                    <Label for="site-domains">Domínios autorizados (um por linha)</Label>
                    <Textarea id="site-domains" v-model="formSite.domains" rows="3" :placeholder="domainsPlaceholder" />
                    <p class="text-xs text-muted-foreground">O domínio do endereço principal é incluído automaticamente. Use *.dominio para subdomínios.</p>
                    <InputError :message="formSite.errors.domains" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="siteOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="formSite.processing">Salvar</Button>
                </div>
            </form>
        </Dialog>

        <Dialog v-model:open="widgetOpen" title="Personalizar widget">
            <form class="space-y-4" @submit.prevent="saveWidget">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <Label for="w-name">Nome interno</Label>
                        <Input id="w-name" v-model="formWidget.name" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="w-title">Título exibido</Label>
                        <Input id="w-title" v-model="formWidget.title" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="w-color">Cor principal</Label>
                        <div class="flex gap-2">
                            <input id="w-color" v-model="formWidget.primary_color" type="color" class="h-9 w-12 rounded-md border" />
                            <Input v-model="formWidget.primary_color" aria-label="Cor em hexadecimal" />
                        </div>
                        <InputError :message="formWidget.errors.primary_color" />
                    </div>
                    <div class="space-y-2">
                        <Label for="w-pos">Posição</Label>
                        <select id="w-pos" v-model="formWidget.position" class="h-9 w-full rounded-md border bg-card px-2 text-sm">
                            <option value="right">Direita</option>
                            <option value="left">Esquerda</option>
                        </select>
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="w-welcome">Mensagem de boas-vindas</Label>
                    <Textarea id="w-welcome" v-model="formWidget.welcome_message" rows="2" />
                </div>
                <div class="space-y-2">
                    <Label for="w-offline">Mensagem quando não houver atendentes online</Label>
                    <Textarea id="w-offline" v-model="formWidget.offline_message" rows="2" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <Checkbox id="ask_name" v-model="formWidget.ask_name" label="Pedir nome" />
                    <Checkbox id="ask_email" v-model="formWidget.ask_email" label="Pedir e-mail" />
                    <Checkbox id="ask_phone" v-model="formWidget.ask_phone" label="Pedir telefone" />
                    <Checkbox id="require_email" v-model="formWidget.require_email" label="E-mail obrigatório" />
                    <Checkbox id="sound_enabled" v-model="formWidget.sound_enabled" label="Som de notificação" />
                    <Checkbox id="is_active" v-model="formWidget.is_active" label="Widget ativo" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="widgetOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="formWidget.processing">Salvar</Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
