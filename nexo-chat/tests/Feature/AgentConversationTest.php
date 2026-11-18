<?php

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Event::fake([MessageCreated::class, ConversationUpdated::class]);
});

it('shows waiting conversations in the inbox', function () {
    ['widget' => $widget, 'agent' => $agent] = tenant();
    $data = startConversation(visitorToken($widget));

    $this->actingAs($agent)->get('/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inbox/Index')
            ->has('conversations', 1)
            ->where('conversations.0.id', $data['conversation']['id'])
            ->where('counts.waiting', 1));

    $this->actingAs($agent)->get('/inbox/'.$data['conversation']['id'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $data['conversation']['id'])
            ->has('messages', 1));
});

it('lets an agent reply, auto-assigning the conversation', function () {
    ['widget' => $widget, 'agent' => $agent] = tenant();
    $token = visitorToken($widget);
    $id = startConversation($token)['conversation']['id'];

    $this->actingAs($agent)->postJson("/conversations/{$id}/messages", [
        'body' => 'Olá! Sou o atendente.',
        'client_message_id' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('message.sender_type', 'agent');

    $this->assertDatabaseHas('conversations', ['uuid' => $id, 'assigned_user_id' => $agent->id, 'status' => 'open']);
    $this->assertDatabaseHas('conversation_assignments', ['user_id' => $agent->id]);

    // o visitante recebe a resposta no histórico
    $this->withToken($token)->getJson('/api/widget/conversation')
        ->assertJsonPath('messages.1.body', 'Olá! Sou o atendente.')
        ->assertJsonPath('conversation.status', 'open');
});

it('only admins can assign conversations to someone else', function () {
    ['widget' => $widget, 'agent' => $agent, 'admin' => $admin, 'company' => $company] = tenant();
    $other = User::factory()->create(['company_id' => $company->id]);
    $id = startConversation(visitorToken($widget))['conversation']['id'];

    $this->actingAs($agent)->postJson("/conversations/{$id}/assign", ['user_id' => $other->id])->assertForbidden();
    $this->actingAs($agent)->postJson("/conversations/{$id}/assign")->assertOk()->assertJsonPath('conversation.assignee.id', $agent->id);
    $this->actingAs($admin)->postJson("/conversations/{$id}/assign", ['user_id' => $other->id])->assertOk()->assertJsonPath('conversation.assignee.id', $other->id);

    Event::assertDispatched(ConversationUpdated::class, fn ($e) => $e->reason === 'assigned');
});

it('closes a conversation, blocks new messages and allows reopening', function () {
    ['widget' => $widget, 'agent' => $agent] = tenant();
    $token = visitorToken($widget);
    $id = startConversation($token)['conversation']['id'];

    $this->actingAs($agent)->postJson("/conversations/{$id}/close")->assertOk()->assertJsonPath('conversation.status', 'closed');
    $this->assertDatabaseHas('messages', ['sender_type' => 'system', 'body' => 'Atendimento encerrado.']);

    $this->withToken($token)->postJson('/api/widget/conversation/messages', [
        'body' => 'ainda aí?', 'client_message_id' => (string) Str::uuid(),
    ])->assertNotFound();
    $this->flushHeaders();

    $this->actingAs($agent)->postJson("/conversations/{$id}/messages", [
        'body' => 'x', 'client_message_id' => (string) Str::uuid(),
    ])->assertForbidden();

    // sem responsável, a conversa reaberta volta para a fila
    $this->actingAs($agent)->postJson("/conversations/{$id}/reopen")->assertOk()->assertJsonPath('conversation.status', 'waiting');
});

it('updates agent availability', function () {
    ['agent' => $agent] = tenant();

    $this->actingAs($agent)->putJson('/me/availability', ['availability' => 'away'])->assertOk();
    expect($agent->fresh()->availability->value)->toBe('away');

    $this->actingAs($agent)->putJson('/me/availability', ['availability' => 'invalid'])->assertUnprocessable();
});
