<?php

namespace App\Http\Controllers\Company;

use App\Enums\AgentAvailability;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AgentRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestão de atendentes da empresa (somente administradores).
 */
class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('agents/Index', [
            'agents' => User::query()
                ->inCompany($request->user()->company_id)
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role->value,
                    'role_label' => $u->role->label(),
                    'availability' => $u->availability->value,
                    'max_concurrent_chats' => $u->max_concurrent_chats,
                    'is_active' => $u->is_active,
                    'is_self' => $u->id === $request->user()->id,
                ]),
            'roles' => self::roleOptions(),
        ]);
    }

    public function store(AgentRequest $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', User::class);

        $agent = User::create([
            ...$request->safe()->only(['name', 'email', 'password', 'role', 'max_concurrent_chats']),
            'company_id' => $request->user()->company_id,
            'availability' => AgentAvailability::Offline,
            'is_active' => true,
        ]);

        $audit->log('agent.created', $agent, ['role' => $agent->role->value]);

        return back()->with('success', 'Atendente criado.');
    }

    public function update(AgentRequest $request, User $agent, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $agent);

        $data = $request->safe()->only(['name', 'email', 'role', 'max_concurrent_chats', 'is_active']);

        if ($agent->id === $request->user()->id) {
            unset($data['role'], $data['is_active']); // evita o admin remover o próprio acesso
        }

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $agent->update($data);
        $audit->log('agent.updated', $agent, ['fields' => array_keys($data)]);

        return back()->with('success', 'Atendente atualizado.');
    }

    public function destroy(Request $request, User $agent, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $agent);

        $agent->forceFill(['is_active' => false, 'availability' => AgentAvailability::Offline])->save();
        $agent->delete();
        $audit->log('agent.deleted', $agent);

        return back()->with('success', 'Atendente removido.');
    }

    /** @return list<array{value: string, label: string}> */
    public static function roleOptions(): array
    {
        return array_map(
            fn (UserRole $r) => ['value' => $r->value, 'label' => $r->label()],
            [UserRole::Agent, UserRole::CompanyAdmin],
        );
    }
}
