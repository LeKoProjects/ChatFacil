<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

it('renders the login and register pages', function () {
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
});

it('registers a new company with its first admin', function () {
    $this->post('/register', [
        'company_name' => 'Acme Ltda',
        'name' => 'Ana Admin',
        'email' => 'ana@acme.test',
        'password' => 'Senha-Forte-123',
        'password_confirmation' => 'Senha-Forte-123',
    ])->assertRedirect('/inbox');

    $user = User::where('email', 'ana@acme.test')->firstOrFail();
    $company = Company::where('name', 'Acme Ltda')->firstOrFail();

    expect($user->company_id)->toBe($company->id)
        ->and($user->role)->toBe(UserRole::CompanyAdmin);

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'company.registered']);
});

it('logs in an active user', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/inbox');

    $this->assertAuthenticatedAs($user);
});

it('rejects login for suspended companies and inactive users', function () {
    $suspended = User::factory()->create(['company_id' => Company::factory()->suspended()]);
    $inactive = User::factory()->create(['is_active' => false]);

    foreach ([$suspended, $inactive] as $user) {
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
});

it('kicks out an authenticated user when the company is suspended', function () {
    $user = User::factory()->create();
    $user->company->update(['status' => 'suspended']);

    $this->actingAs($user)->get('/inbox')->assertRedirect('/login');
    $this->assertGuest();
});

it('sends an already authenticated agent from the login page to the inbox', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->get('/login')->assertRedirect('/inbox');
});

it('protects panel routes from guests and agents', function () {
    $this->get('/inbox')->assertRedirect('/login');

    $agent = User::factory()->create();
    $this->actingAs($agent)->get('/websites')->assertForbidden();
    $this->actingAs($agent)->get('/agents')->assertForbidden();
    $this->actingAs($agent)->get('/inbox')->assertOk();
});
