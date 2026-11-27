<?php

use App\Events\VisitorConversationUpdated;
use App\Events\VisitorMessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/*
| Correções da auditoria de segurança de 27/11/2026.
*/

it('discards a non-http page url sent by the visitor', function () {
    ['widget' => $widget] = tenant();

    $token = $this->postJson("/api/widget/{$widget->public_key}/sessions", [
        'host' => 'cliente.test',
        'page_url' => 'javascript:alert(document.cookie)',
    ])->assertOk()->json('token');

    startConversation($token);

    $conversation = app(TenantContext::class)->withoutScope(fn () => Conversation::query()->firstOrFail());
    expect($conversation->origin_url)->toBeNull();
});

it('keeps a normal https page url', function () {
    ['widget' => $widget] = tenant();
    startConversation(visitorToken($widget));

    $conversation = app(TenantContext::class)->withoutScope(fn () => Conversation::query()->firstOrFail());
    expect($conversation->origin_url)->toBe('https://cliente.test/produtos');
});

it('sends anti-clickjacking headers on panel pages but not on the widget frame', function () {
    ['widget' => $widget] = tenant();

    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");

    $frame = $this->get("/widget/frame/{$widget->public_key}?host=cliente.test")->assertOk();
    expect($frame->headers->get('Content-Security-Policy'))->toContain('cliente.test')
        ->and($frame->headers->has('X-Frame-Options'))->toBeFalse();
});

it('gives the visitor channel only what the visitor needs', function () {
    Event::fake([VisitorMessageCreated::class, VisitorConversationUpdated::class]);
    ['widget' => $widget] = tenant();
    $id = startConversation(visitorToken($widget), 'oi')['conversation']['id'];

    Event::assertDispatched(VisitorConversationUpdated::class, function (VisitorConversationUpdated $e) use ($id) {
        $payload = $e->broadcastWith();

        return $e->broadcastOn()->name === "private-conversation.{$id}"
            && array_keys($payload['conversation']) === ['id', 'status', 'assignee'];
    });

    Event::assertDispatched(VisitorMessageCreated::class, function (VisitorMessageCreated $e) use ($id) {
        return $e->broadcastOn()->name === "private-conversation.{$id}"
            && array_keys($e->broadcastWith()) === ['message'];
    });
});

it('returns a validation error instead of crashing when there is no bot message to pick from', function () {
    ['widget' => $widget] = tenant();
    $token = visitorToken($widget);
    startConversation($token);

    $conversation = app(TenantContext::class)->withoutScope(fn () => Conversation::query()->firstOrFail());
    $conversation->forceFill(['status' => 'bot'])->save();
    app(TenantContext::class)->withoutScope(fn () => Message::query()->where('sender_type', 'bot')->delete());

    $this->withToken($token)->postJson('/api/widget/conversation/options', [
        'option' => 'node:x',
        'client_message_id' => (string) Str::uuid(),
    ])->assertStatus(422);
});

it('does not seed demo users in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
});
