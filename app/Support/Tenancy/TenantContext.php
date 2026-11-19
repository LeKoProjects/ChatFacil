<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Closure;

/**
 * Empresa (tenant) do contexto atual da requisição.
 *
 * Definida pelo middleware SetTenantContext (usuário autenticado) ou pela
 * camada do widget (empresa dona do widget). Os modelos com BelongsToCompany
 * aplicam um escopo global com este valor, de modo que nenhuma consulta
 * depende de filtros vindos do frontend.
 */
class TenantContext
{
    private ?int $companyId = null;

    private bool $bypass = false;

    public function set(Company|int|null $company): void
    {
        $this->companyId = $company instanceof Company ? $company->getKey() : $company;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    public function clear(): void
    {
        $this->companyId = null;
    }

    public function isBypassed(): bool
    {
        return $this->bypass;
    }

    /**
     * Executa um callback sem o escopo de empresa — apenas para rotinas da
     * administração central NEXO e jobs de sistema. Uso deve ser explícito.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutScope(Closure $callback): mixed
    {
        $previous = $this->bypass;
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $previous;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Company|int $company, Closure $callback): mixed
    {
        $previous = $this->companyId;
        $this->set($company);

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
