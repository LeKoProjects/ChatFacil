<?php

use App\Models\User;
use App\Models\Website;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

it('lets a company admin register a website and get the widget install script', function () {
    ['admin' => $admin, 'company' => $company] = tenant();

    $this->actingAs($admin)->post('/websites', [
        'name' => 'Loja Virtual',
        'url' => 'https://www.loja.test',
        'domains' => "loja.test\n*.loja.test",
    ])->assertRedirect()->assertSessionHasNoErrors();

    $website = app(TenantContext::class)->runAs($company, fn () => Website::where('name', 'Loja Virtual')->with('domains', 'widgets')->firstOrFail());

    expect($website->domains->pluck('host')->sort()->values()->all())->toBe(['*.loja.test', 'loja.test', 'www.loja.test'])
        ->and($website->widgets)->toHaveCount(1);

    $key = $website->widgets->first()->public_key;

    $this->actingAs($admin)->get('/websites')->assertInertia(fn (Assert $page) => $page
        ->component('websites/Index')
        ->where('websites', fn ($sites) => collect($sites)->contains(fn ($s) => str_contains($s['widgets'][0]['snippet'], "data-widget-id=\"{$key}\""))));

    $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'website.created']);
});

it('rejects invalid domains', function () {
    ['admin' => $admin] = tenant();

    $this->actingAs($admin)->post('/websites', ['name' => 'x', 'url' => 'not a domain!!', 'domains' => ''])
        ->assertSessionHasErrors('url');
});

it('lets a company admin create an agent who can then log in', function () {
    ['admin' => $admin, 'company' => $company] = tenant();

    $this->actingAs($admin)->post('/agents', [
        'name' => 'João Atendente',
        'email' => 'joao@empresa.test',
        'password' => 'Senha-Forte-123',
        'role' => 'agent',
        'max_concurrent_chats' => 4,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $agent = User::where('email', 'joao@empresa.test')->firstOrFail();
    expect($agent->company_id)->toBe($company->id)->and($agent->role->value)->toBe('agent');

    auth()->logout();
    $this->post('/login', ['email' => 'joao@empresa.test', 'password' => 'Senha-Forte-123'])->assertRedirect('/inbox');
    $this->assertAuthenticatedAs($agent);
});

it('does not let an admin escalate a user to platform admin', function () {
    ['admin' => $admin] = tenant();

    $this->actingAs($admin)->post('/agents', [
        'name' => 'X', 'email' => 'x@empresa.test', 'password' => 'Senha-Forte-123',
        'role' => 'platform_admin', 'max_concurrent_chats' => 4,
    ])->assertSessionHasErrors('role');
});

it('shows real dashboard numbers for the company only', function () {
    ['admin' => $admin, 'widget' => $widget] = tenant();
    ['widget' => $otherWidget] = tenant('outra.test');
    Illuminate\Support\Facades\Event::fake([App\Events\MessageCreated::class, App\Events\ConversationUpdated::class]);

    startConversation(visitorToken($widget));
    startConversation(visitorToken($otherWidget, 'outra.test'));

    $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/Index')
        ->where('stats.received', 1)
        ->where('stats.waiting_now', 1)
        ->has('series', 14));
});
