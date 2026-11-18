<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a empresa do contexto a partir do usuário autenticado e bloqueia
 * acesso de usuários inativos ou de empresas suspensas.
 */
class SetTenantContext
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A administração NEXO não pertence a nenhuma empresa: as telas de
        // atendimento/painel da empresa não se aplicam a ela.
        if ($user?->isPlatformAdmin()) {
            return $request->expectsJson() ? abort(403) : redirect()->route('nexo.overview');
        }

        if ($user) {
            if (! $user->is_active || ! $user->company || ! $user->company->isActive()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Acesso indisponível: usuário inativo ou empresa suspensa.',
                ]);
            }

            $this->tenant->set($user->company_id);
        }

        return $next($request);
    }
}
