<?php

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    Event::fake([MessageCreated::class, ConversationUpdated::class]);
});

it('serves the widget frame only for authorized domains with a restrictive CSP', function () {
    ['widget' => $widget] = tenant();

    $this->get("/widget/frame/{$widget->public_key}?host=cliente.test")
        ->assertOk()
        ->assertHeader('Content-Security-Policy', 'frame-ancestors https://cliente.test http://cliente.test');

    $this->get("/widget/frame/{$widget->public_key}?host=intruso.test")->assertForbidden();
    $this->get('/widget/frame/wgt_00000000000000000000000000?host=cliente.test')->assertNotFound();
});

it('does not issue visitor sessions to unauthorized domains', function () {
    ['widget' => $widget] = tenant();

    $this->postJson("/api/widget/{$widget->public_key}/sessions", ['host' => 'intruso.test'])
        ->assertForbidden();

    expect(app(TenantContext::class)->withoutScope(fn () => App\Models\Visitor::count()))->toBe(0);
});

it('lets a visitor start a conversation and persists it', function () {
    ['widget' => $widget, 'company' => $company] = tenant();
    $token = visitorToken($widget);

    $data = startConversation($token, 'Quero um orçamento');

    expect($data['conversation']['status'])->toBe('waiting')
        ->and($data['messages'])->toHaveCount(1)
        ->and($data['messages'][0]['body'])->toBe('Quero um orçamento');

    $this->assertDatabaseHas('conversations', ['company_id' => $company->id, 'status' => 'waiting']);
    $this->assertDatabaseHas('messages', ['company_id' => $company->id, 'body' => 'Quero um orçamento', 'sender_type' => 'visitor']);
    $this->assertDatabaseHas('visitors', ['company_id' => $company->id, 'email' => 'maria@example.com']);

    Event::assertDispatched(MessageCreated::class);
    Event::assertDispatched(ConversationUpdated::class, fn ($e) => $e->reason === 'created');
});

it('is idempotent for the same client_message_id', function () {
    ['widget' => $widget] = tenant();
    $token = visitorToken($widget);
    startConversation($token);

    $clientId = (string) Str::uuid();

    $first = $this->withToken($token)->postJson('/api/widget/conversation/messages', ['body' => 'Repetida', 'client_message_id' => $clientId])->assertCreated();
    $second = $this->withToken($token)->postJson('/api/widget/conversation/messages', ['body' => 'Repetida', 'client_message_id' => $clientId])->assertCreated();

    expect($first->json('message.id'))->toBe($second->json('message.id'));
    expect(app(TenantContext::class)->withoutScope(fn () => Message::where('body', 'Repetida')->count()))->toBe(1);
});

it('keeps history across page reloads and recovers messages after a given sequence', function () {
    ['widget' => $widget] = tenant();
    $token = visitorToken($widget);
    $start = startConversation($token, 'primeira');

    $this->withToken($token)->postJson('/api/widget/conversation/messages', ['body' => 'segunda', 'client_message_id' => (string) Str::uuid()]);
    $this->flushHeaders();

    // "Recarregar a página": o navegador reenvia o token salvo e recebe o histórico.
    $reload = $this->postJson("/api/widget/{$widget->public_key}/sessions", ['host' => 'cliente.test', 'token' => $token])->assertOk();

    expect($reload->json('token'))->toBe($token)
        ->and(collect($reload->json('messages'))->pluck('body')->all())->toBe(['primeira', 'segunda']);

    $after = $start['messages'][0]['seq'];
    $this->withToken($token)->getJson('/api/widget/conversation?after='.$after)
        ->assertOk()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.body', 'segunda');
});

it('rejects requests without a valid visitor token', function () {
    $this->postJson('/api/widget/conversation/messages', ['body' => 'x', 'client_message_id' => (string) Str::uuid()])->assertUnauthorized();
    $this->withToken(str_repeat('a', 64))->getJson('/api/widget/conversation')->assertUnauthorized();
});

it('keeps only one active conversation per visitor', function () {
    ['widget' => $widget] = tenant();
    $token = visitorToken($widget);

    startConversation($token, 'um');
    startConversation($token, 'dois');

    expect(app(TenantContext::class)->withoutScope(fn () => Conversation::count()))->toBe(1);
});
