<?php

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Website;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    Event::fake([MessageCreated::class, ConversationUpdated::class]);
    $this->a = tenant('empresa-a.test');
    $this->b = tenant('empresa-b.test');
    $this->convB = startConversation(visitorToken($this->b['widget'], 'empresa-b.test'))['conversation']['id'];
});

it('does not list or open conversations from another company', function () {
    $this->actingAs($this->a['admin'])->get('/inbox?filter=all')
        ->assertInertia(fn ($page) => $page->has('conversations', 0));

    $this->actingAs($this->a['admin'])->get('/inbox/'.$this->convB)->assertNotFound();
});

it('cannot act on another company conversation', function () {
    $admin = $this->a['admin'];

    foreach (['assign', 'close', 'reopen'] as $action) {
        $this->actingAs($admin)->postJson("/conversations/{$this->convB}/{$action}")->assertNotFound();
    }

    $this->actingAs($admin)->postJson("/conversations/{$this->convB}/messages", [
        'body' => 'invasão', 'client_message_id' => (string) Str::uuid(),
    ])->assertNotFound();

    $this->assertDatabaseMissing('messages', ['body' => 'invasão']);
});

it('cannot assign a conversation to a user of another company', function () {
    $convA = startConversation(visitorToken($this->a['widget'], 'empresa-a.test'))['conversation']['id'];

    $this->actingAs($this->a['admin'])
        ->postJson("/conversations/{$convA}/assign", ['user_id' => $this->b['agent']->id])
        ->assertNotFound();
});

it('cannot manage websites, widgets or agents of another company', function () {
    $admin = $this->a['admin'];
    $websiteB = app(TenantContext::class)->runAs($this->b['company'], fn () => Website::firstOrFail());

    $this->actingAs($admin)->put("/websites/{$websiteB->id}", ['name' => 'x', 'url' => 'x.test'])->assertNotFound();
    $this->actingAs($admin)->delete("/websites/{$websiteB->id}")->assertNotFound();
    $this->actingAs($admin)->put("/widgets/{$this->b['widget']->id}", ['name' => 'x'])->assertNotFound();
    $this->actingAs($admin)->put("/agents/{$this->b['agent']->id}", [
        'name' => 'x', 'email' => 'x@x.test', 'role' => 'agent', 'max_concurrent_chats' => 3,
    ])->assertForbidden();
    $this->actingAs($admin)->delete("/agents/{$this->b['agent']->id}")->assertForbidden();

    $this->actingAs($admin)->get('/websites')
        ->assertInertia(fn ($page) => $page->has('websites', 1)->where('websites.0.domains.0', 'empresa-a.test'));
});

it('does not let a visitor token of one company read another company widget data', function () {
    $tokenA = visitorToken($this->a['widget'], 'empresa-a.test');

    $this->withToken($tokenA)->getJson('/api/widget/conversation')
        ->assertOk()
        ->assertJsonPath('conversation', null);
});

it('fails closed when no tenant is in context and blocks cross-tenant writes', function () {
    $context = app(TenantContext::class);
    $context->clear();

    expect(Conversation::count())->toBe(0);

    $context->set($this->a['company']);
    expect(Conversation::count())->toBe(0);

    expect(fn () => Website::create(['company_id' => $this->b['company']->id, 'name' => 'x', 'url' => 'x.test']))
        ->toThrow(LogicException::class);
});
