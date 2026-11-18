<?php

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Event::fake([MessageCreated::class, ConversationUpdated::class]);
    $this->nexo = User::factory()->platformAdmin()->create();
});

it('is reachable only by NEXO platform admins', function () {
    ['admin' => $companyAdmin, 'agent' => $agent] = tenant();

    foreach (['/nexo', '/nexo/companies', '/nexo/audit'] as $url) {
        $this->actingAs($this->nexo)->get($url)->assertOk();
        $this->actingAs($companyAdmin)->get($url)->assertForbidden();
        $this->actingAs($agent)->get($url)->assertForbidden();
    }

    auth()->logout();
    $this->get('/nexo')->assertRedirect('/login');
});

it('sends the platform admin to the NEXO panel instead of company screens', function () {
    $this->post('/login', ['email' => $this->nexo->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($this->nexo);

    $this->get('/inbox')->assertRedirect('/nexo');
    $this->get('/dashboard')->assertRedirect('/nexo');
    $this->get('/login')->assertRedirect('/nexo');
});

it('shows platform-wide metrics across all companies', function () {
    ['widget' => $a] = tenant('a.test');
    ['widget' => $b, 'company' => $companyB] = tenant('b.test');
    startConversation(visitorToken($a, 'a.test'));
    startConversation(visitorToken($b, 'b.test'));
    startConversation(visitorToken($b, 'b.test'));

    $this->actingAs($this->nexo)->get('/nexo')->assertInertia(fn (Assert $page) => $page
        ->component('nexo/Overview')
        ->where('metrics.companies.total', 2)
        ->where('metrics.conversations.today', 3)
        ->where('metrics.conversations.active_now', 3)
        ->where('metrics.top_companies.0.id', $companyB->id)
        ->where('metrics.top_companies.0.conversations_30d', 2)
        ->has('metrics.series', 14));
});

it('lists and searches companies', function () {
    tenant('a.test');
    ['company' => $b] = tenant('b.test');
    $b->update(['name' => 'Clínica Sorriso']);

    $this->actingAs($this->nexo)->get('/nexo/companies')
        ->assertInertia(fn (Assert $page) => $page->component('nexo/companies/Index')->has('companies.data', 2));

    $this->actingAs($this->nexo)->get('/nexo/companies?q=sorriso')
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Clínica Sorriso')
            ->where('companies.data.0.users_count', 2)
            ->where('companies.data.0.websites_count', 1));
});

it('creates a company with its first admin', function () {
    $this->actingAs($this->nexo)->post('/nexo/companies', [
        'company_name' => 'Empresa Nova',
        'document' => '12.345.678/0001-90',
        'name' => 'Gestor Novo',
        'email' => 'gestor@nova.test',
        'password' => 'Senha-Forte-123',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $company = Company::where('name', 'Empresa Nova')->firstOrFail();
    $admin = User::where('email', 'gestor@nova.test')->firstOrFail();

    expect($admin->company_id)->toBe($company->id)->and($admin->isCompanyAdmin())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'company.created_by_nexo', 'user_id' => $this->nexo->id]);
});

it('suspends a company: users are logged out, widget stops, then reactivates', function () {
    ['company' => $company, 'agent' => $agent, 'widget' => $widget] = tenant();

    $this->actingAs($this->nexo)->post("/nexo/companies/{$company->id}/suspend", ['reason' => 'Inadimplência'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($company->fresh()->isActive())->toBeFalse()
        ->and($agent->fresh()->availability->value)->toBe('offline');
    $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'company.suspended']);

    // efeitos reais da suspensão
    $this->actingAs($agent)->get('/inbox')->assertRedirect('/login');
    $this->get("/widget/frame/{$widget->public_key}?host=cliente.test")->assertNotFound();
    $this->postJson("/api/widget/{$widget->public_key}/sessions", ['host' => 'cliente.test'])->assertNotFound();

    $this->actingAs($this->nexo)->post("/nexo/companies/{$company->id}/activate")->assertRedirect();
    expect($company->fresh()->isActive())->toBeTrue();
    $this->get("/widget/frame/{$widget->public_key}?host=cliente.test")->assertOk();
});

it('shows company details including users, sites and audit trail', function () {
    ['company' => $company, 'admin' => $admin] = tenant();

    $this->actingAs($this->nexo)->get("/nexo/companies/{$company->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('nexo/companies/Show')
            ->where('company.id', $company->id)
            ->has('users', 2)
            ->where('websites.0.domains.0', 'cliente.test')
            ->where('audit', fn ($audit) => collect($audit)->contains('action', 'website.created')));
});

it('updates company data and filters the audit log', function () {
    ['company' => $company] = tenant();

    $this->actingAs($this->nexo)->put("/nexo/companies/{$company->id}", ['name' => 'Nome Corrigido', 'document' => null])
        ->assertSessionHasNoErrors();
    expect($company->fresh()->name)->toBe('Nome Corrigido');

    $this->actingAs($this->nexo)->get("/nexo/audit?company={$company->id}&action=company.updated")
        ->assertInertia(fn (Assert $page) => $page
            ->component('nexo/Audit')
            ->has('logs.data', 1)
            ->where('logs.data.0.label', 'Dados da empresa alterados')
            ->where('logs.data.0.user_name', $this->nexo->name));
});
