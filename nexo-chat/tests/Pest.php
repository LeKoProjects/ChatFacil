<?php

use App\Actions\Websites\SaveWebsite;
use App\Models\Company;
use App\Models\User;
use App\Models\Widget;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\TestCase;

// RefreshDatabase (com trava de segurança do banco) é aplicado em Tests\TestCase.
pest()->extend(TestCase::class)
    ->in('Feature');

/**
 * Cria uma empresa completa: admin, atendente, site (domínio cliente.test) e widget.
 *
 * @return array{company: Company, admin: User, agent: User, widget: Widget}
 */
function tenant(string $domain = 'cliente.test'): array
{
    $company = Company::factory()->create();
    $admin = User::factory()->admin()->online()->create(['company_id' => $company->id]);
    $agent = User::factory()->online()->create(['company_id' => $company->id]);

    $widget = app(TenantContext::class)->runAs($company, function () use ($domain) {
        $website = app(SaveWebsite::class)->handle(new App\Models\Website, [
            'name' => 'Site '.$domain,
            'url' => 'https://'.$domain,
            'domains' => [$domain],
        ]);

        return $website->widgets()->firstOrFail();
    });

    app(TenantContext::class)->clear();

    return compact('company', 'admin', 'agent', 'widget');
}

/**
 * Abre uma sessão de visitante pela API pública e retorna o token.
 */
function visitorToken(Widget $widget, string $host = 'cliente.test'): string
{
    $response = test()->postJson("/api/widget/{$widget->public_key}/sessions", [
        'host' => $host,
        'page_url' => "https://{$host}/produtos",
    ])->assertOk();

    app(TenantContext::class)->clear();

    return $response->json('token');
}

function startConversation(string $token, string $message = 'Olá, preciso de ajuda'): array
{
    $response = test()->withToken($token)->postJson('/api/widget/conversation', [
        'name' => 'Maria Visitante',
        'email' => 'maria@example.com',
        'message' => $message,
        'client_message_id' => (string) Str::uuid(),
    ])->assertCreated();

    app(TenantContext::class)->clear();
    test()->flushHeaders();

    return $response->json();
}
