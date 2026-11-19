<?php

use App\Enums\BotNodeType;
use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Models\BotNode;
use App\Models\Chatbot;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\FaqEntry;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\ChatbotDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    Event::fake([MessageCreated::class, ConversationUpdated::class]);
});

/**
 * Liga o chatbot com um menu: Suporte (submenu → Impressora [resposta], Rede [transferir])
 * e Financeiro [transferir], mais duas perguntas frequentes.
 *
 * @return array<string, BotNode>
 */
function setUpBot(Company $company): array
{
    return app(TenantContext::class)->runAs($company, function () {
        Chatbot::create([...Chatbot::DEFAULTS, 'is_enabled' => true]);

        $support = BotNode::create(['type' => BotNodeType::Menu, 'label' => 'Suporte técnico', 'message' => 'Qual o problema?', 'sort_order' => 1]);
        $printer = BotNode::create(['parent_id' => $support->id, 'type' => BotNodeType::Answer, 'label' => 'Impressora', 'message' => 'Desligue e ligue a impressora.', 'sort_order' => 1]);
        $network = BotNode::create(['parent_id' => $support->id, 'type' => BotNodeType::Handoff, 'label' => 'Internet', 'handoff_tag' => 'Suporte', 'sort_order' => 2]);
        $finance = BotNode::create(['type' => BotNodeType::Handoff, 'label' => 'Financeiro', 'sort_order' => 2]);

        FaqEntry::create(['question' => 'Como emitir a segunda via do boleto?', 'answer' => 'Acesse o portal do cliente.', 'keywords' => 'fatura pagamento']);
        FaqEntry::create(['question' => 'Qual o horário de atendimento?', 'answer' => 'De segunda a sexta, das 8h às 18h.', 'keywords' => 'abre fecha funciona']);

        return compact('support', 'printer', 'network', 'finance');
    });
}

function chooseOption(string $token, string $option): array
{
    $response = test()->withToken($token)->postJson('/api/widget/conversation/options', [
        'option' => $option,
        'client_message_id' => (string) Str::uuid(),
    ]);

    app(TenantContext::class)->clear();
    test()->flushHeaders();

    return [$response->status(), $response->json()];
}

function lastOptions(array $payload): array
{
    $bot = collect($payload['messages'])->where('sender_type', 'bot')->last();

    return collect($bot['options'] ?? [])->pluck('label', 'id')->all();
}

it('keeps the current flow when the chatbot is off', function () {
    ['widget' => $widget] = tenant();

    $data = startConversation(visitorToken($widget), 'Olá');

    expect($data['conversation']['status'])->toBe('waiting')
        ->and($data['messages'])->toHaveCount(1);
});

it('greets with the main menu and keeps the conversation out of the queue', function () {
    ['widget' => $widget, 'company' => $company] = tenant();
    setUpBot($company);

    $data = startConversation(visitorToken($widget), 'Olá');

    expect($data['conversation']['status'])->toBe('bot')
        ->and(array_values(lastOptions($data)))->toBe(['Suporte técnico', 'Financeiro', 'Falar com um atendente']);
});

it('suggests FAQ answers for what the visitor typed, ignoring accents', function () {
    ['widget' => $widget, 'company' => $company] = tenant();
    setUpBot($company);

    $data = startConversation(visitorToken($widget), 'preciso da segunda via do boleto');

    expect(array_values(lastOptions($data)))->toContain('Como emitir a segunda via do boleto?');

    $token = visitorToken($widget);
    $this->withToken($token)->postJson('/api/widget/conversation', ['name' => 'X', 'email' => 'x@example.com', 'message' => 'oi', 'client_message_id' => (string) Str::uuid()]);
    app(TenantContext::class)->clear();
    $this->flushHeaders();

    $reply = $this->withToken($token)->postJson('/api/widget/conversation/messages', [
        'body' => 'que horario voces abrem?',
        'client_message_id' => (string) Str::uuid(),
    ])->assertCreated();

    expect(collect($reply->json('replies.0.options'))->pluck('label'))->toContain('Qual o horário de atendimento?');
});

it('walks the menu, answers and closes when the visitor says it was solved', function () {
    ['widget' => $widget, 'company' => $company] = tenant();
    $nodes = setUpBot($company);
    $token = visitorToken($widget);
    startConversation($token, 'Olá');

    [$status, $data] = chooseOption($token, 'node:'.$nodes['support']->uuid);
    expect($status)->toBe(200)
        ->and(array_values(lastOptions($data)))->toBe(['Impressora', 'Internet', 'Voltar ao início', 'Falar com um atendente']);

    [, $data] = chooseOption($token, 'node:'.$nodes['printer']->uuid);
    $bodies = collect($data['messages'])->pluck('body');
    expect($bodies)->toContain('Impressora')->toContain('Desligue e ligue a impressora.');

    [, $data] = chooseOption($token, 'resolved');
    expect($data['conversation']['status'])->toBe('closed');

    $conversation = app(TenantContext::class)->withoutScope(fn () => Conversation::first());
    expect($conversation->bot_trail)->toBe(['Suporte técnico', 'Impressora']);
});

it('hands off to the team with the sector tag and trail', function () {
    ['widget' => $widget, 'company' => $company] = tenant();
    $nodes = setUpBot($company);
    $token = visitorToken($widget);
    startConversation($token, 'Olá');

    chooseOption($token, 'node:'.$nodes['support']->uuid);
    [, $data] = chooseOption($token, 'node:'.$nodes['network']->uuid);

    expect($data['conversation']['status'])->toBe('waiting');

    $conversation = app(TenantContext::class)->withoutScope(fn () => Conversation::first());
    expect($conversation->handoff_tag)->toBe('Suporte')
        ->and($conversation->bot_trail)->toBe(['Suporte técnico', 'Internet']);

    Event::assertDispatched(ConversationUpdated::class, fn ($e) => $e->reason === 'handoff');

    // Depois da transferência o bot não responde mais.
    [$status] = chooseOption($token, 'menu');
    expect($status)->toBe(422);
});

it('transfers by itself after two messages without an answer', function () {
    ['widget' => $widget, 'company' => $company] = tenant();
    setUpBot($company);
    $token = visitorToken($widget);
    startConversation($token, 'Olá');

    foreach (['xyzzy', 'plugh'] as $text) {
        $this->withToken($token)->postJson('/api/widget/conversation/messages', ['body' => $text, 'client_message_id' => (string) Str::uuid()])->assertCreated();
        app(TenantContext::class)->clear();
        $this->flushHeaders();
    }

    expect(app(TenantContext::class)->withoutScope(fn () => Conversation::first()->status->value))->toBe('waiting');
});

it('only accepts options offered in the last bot message', function () {
    ['widget' => $widget, 'company' => $company] = tenant();
    $nodes = setUpBot($company);
    $token = visitorToken($widget);
    startConversation($token, 'Olá');

    // "Impressora" está dentro de Suporte: ainda não foi oferecida.
    [$status] = chooseOption($token, 'node:'.$nodes['printer']->uuid);
    expect($status)->toBe(422);
});

it('stops the bot when an agent takes over', function () {
    ['widget' => $widget, 'company' => $company, 'agent' => $agent] = tenant();
    setUpBot($company);
    $data = startConversation(visitorToken($widget), 'Olá');

    $this->actingAs($agent)->postJson("/conversations/{$data['conversation']['id']}/assign")->assertOk()
        ->assertJsonPath('conversation.status', 'open');
});

it('lists bot conversations in their own inbox filter', function () {
    ['widget' => $widget, 'company' => $company, 'agent' => $agent] = tenant();
    setUpBot($company);
    startConversation(visitorToken($widget), 'Olá');

    $this->actingAs($agent)->get('/inbox')->assertInertia(fn ($page) => $page
        ->where('counts.bot', 1)
        ->has('conversations', 0));

    $this->actingAs($agent)->get('/inbox?filter=bot')->assertInertia(fn ($page) => $page->has('conversations', 1));
});

it('lets the company admin build the menu and FAQ, isolated per company', function () {
    ['admin' => $admin] = tenant();
    ['admin' => $otherAdmin] = tenant('outro.test');

    $this->actingAs($admin)->post('/chatbot/nodes', ['type' => 'menu', 'label' => 'Vendas'])->assertRedirect();
    $menu = app(TenantContext::class)->withoutScope(fn () => BotNode::first());
    $this->actingAs($admin)->post('/chatbot/nodes', ['parent_id' => $menu->id, 'type' => 'answer', 'label' => 'Preços', 'message' => 'Veja a tabela.'])->assertRedirect();
    $this->actingAs($admin)->post('/chatbot/faqs', ['question' => 'Vocês entregam?', 'answer' => 'Sim.'])->assertRedirect();

    $this->actingAs($admin)->get('/chatbot')->assertInertia(fn ($page) => $page
        ->component('chatbot/Index')
        ->has('nodes', 2)
        ->has('faqs', 1));

    // Outra empresa não enxerga nem altera.
    $this->actingAs($otherAdmin)->get('/chatbot')->assertInertia(fn ($page) => $page->has('nodes', 0)->has('faqs', 0));
    $this->actingAs($otherAdmin)->delete("/chatbot/nodes/{$menu->id}")->assertNotFound();
    $this->actingAs($otherAdmin)->post('/chatbot/nodes', ['parent_id' => $menu->id, 'type' => 'answer', 'label' => 'X', 'message' => 'Y'])
        ->assertSessionHasErrors('parent_id');
});

it('forbids agents from editing the chatbot', function () {
    ['agent' => $agent] = tenant();

    $this->actingAs($agent)->get('/chatbot')->assertForbidden();
});

it('seeds a ready-to-use demo chatbot with menu, answers and FAQ', function () {
    $this->seed(DatabaseSeeder::class);

    $company = Company::query()->where('name', 'Empresa Demo')->firstOrFail();

    app(TenantContext::class)->runAs($company, function () {
        expect(Chatbot::query()->value('is_enabled'))->toBeTrue()
            ->and(BotNode::query()->whereNull('parent_id')->orderBy('sort_order')->pluck('label')->all())
            ->toBe(['Suporte técnico', 'Financeiro', 'Comercial / orçamento'])
            ->and(BotNode::query()->where('type', 'answer')->count())->toBeGreaterThanOrEqual(8)
            ->and(BotNode::query()->where('type', 'handoff')->whereNotNull('handoff_tag')->count())->toBeGreaterThanOrEqual(4)
            ->and(FaqEntry::query()->count())->toBe(8);

        // Submenu dentro de submenu (Suporte > Impressora > ...).
        $printer = BotNode::query()->where('label', 'Impressora')->firstOrFail();
        expect($printer->depth())->toBe(2)
            ->and($printer->children()->pluck('label')->all())->toBe(['Não imprime', 'Papel enroscado', 'Instalar impressora nova']);
    });

    // Rodar de novo não duplica.
    $this->seed(ChatbotDemoSeeder::class);
    expect(app(TenantContext::class)->runAs($company, fn () => FaqEntry::query()->count()))->toBe(8);

    // A outra empresa de demonstração começa sem chatbot.
    $other = Company::query()->where('name', 'Outra Empresa')->firstOrFail();
    expect(app(TenantContext::class)->runAs($other, fn () => Chatbot::query()->exists()))->toBeFalse();
});
