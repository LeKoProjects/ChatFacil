<?php

namespace App\Support\Widget;

use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * RequestGuard que descarta o usuário em cache quando o token da requisição
 * muda (processos longos: Octane, testes, workers).
 */
class VisitorGuard extends RequestGuard
{
    private ?string $resolvedForToken = null;

    public function user(): ?Authenticatable
    {
        $token = $this->request->bearerToken();

        if ($token !== $this->resolvedForToken) {
            $this->user = null;
            $this->resolvedForToken = $token;
        }

        return parent::user();
    }
}
