<?php

namespace App\Support\Widget;

use App\Models\Visitor;
use App\Models\VisitorSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Driver do guard `visitor`: autentica o visitante pelo token de sessão
 * (Authorization: Bearer <token>) e define o contexto da empresa.
 */
class VisitorTokenResolver
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function __invoke(Request $request): ?Visitor
    {
        $token = $request->bearerToken();

        if (! $token || strlen($token) !== 64) {
            return null;
        }

        /** @var VisitorSession|null $session */
        $session = $this->tenant->withoutScope(fn () => VisitorSession::query()
            ->with(['visitor', 'widget.company'])
            ->where('token_hash', VisitorSession::hashToken($token))
            ->where('expires_at', '>', now())
            ->first());

        if (! $session || ! $session->visitor || ! $session->widget?->is_active
            || ! $session->widget->company?->isActive()) {
            return null;
        }

        $this->tenant->set($session->company_id);

        if ($session->last_activity_at === null || $session->last_activity_at->lt(now()->subMinute())) {
            $session->forceFill(['last_activity_at' => now()])->saveQuietly();
            $session->visitor->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $visitor = $session->visitor;
        $visitor->currentSession = $session;

        return $visitor;
    }
}
