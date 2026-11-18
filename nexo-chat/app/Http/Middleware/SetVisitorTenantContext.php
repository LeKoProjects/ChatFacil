<?php

namespace App\Http\Middleware;

use App\Models\Visitor;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Após `auth:visitor`, fixa a empresa do contexto a partir do visitante
 * autenticado (independe de cache do guard entre requisições).
 */
class SetVisitorTenantContext
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $visitor = $request->user('visitor');

        abort_unless($visitor instanceof Visitor, 401);

        $this->tenant->set($visitor->company_id);

        return $next($request);
    }
}
