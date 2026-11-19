<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Isolamento multiempresa: escopo global por company_id + preenchimento
 * automático e verificação na criação.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('company_id') === null) {
                if (! $context->has()) {
                    throw new LogicException(static::class.' criado sem empresa no contexto.');
                }
                $model->setAttribute('company_id', $context->id());
            }

            if ($context->has() && ! $context->isBypassed()
                && (int) $model->getAttribute('company_id') !== $context->id()) {
                throw new LogicException('Tentativa de gravar registro para outra empresa.');
            }
        });
    }

    /**
     * Restauração de modelos serializados em jobs (SerializesModels): o
     * registro já foi autorizado ao ser enfileirado, então é buscado pela
     * chave primária sem o escopo — o job define o contexto em seguida.
     */
    public function newQueryForRestoration($ids)
    {
        return parent::newQueryForRestoration($ids)->withoutGlobalScope(CompanyScope::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
