<?php

namespace App\Actions\Companies;

use App\Enums\AgentAvailability;
use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Suspensão / reativação de uma empresa cliente pela administração NEXO.
 *
 * Efeitos da suspensão (já garantidos pelo restante do sistema):
 *  - usuários da empresa são desconectados na próxima requisição e não fazem login;
 *  - o widget deixa de carregar e novas sessões de visitante são recusadas.
 */
class ChangeCompanyStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function suspend(Company $company, ?string $reason = null): Company
    {
        if ($company->status === CompanyStatus::Suspended) {
            return $company;
        }

        return DB::transaction(function () use ($company, $reason): Company {
            $company->forceFill(['status' => CompanyStatus::Suspended, 'suspended_at' => now()])->save();

            User::query()->inCompany($company->id)->update(['availability' => AgentAvailability::Offline->value]);

            $this->audit->log('company.suspended', $company, array_filter(['reason' => $reason]), $company->id);

            return $company;
        });
    }

    public function activate(Company $company): Company
    {
        if ($company->status === CompanyStatus::Active) {
            return $company;
        }

        $company->forceFill(['status' => CompanyStatus::Active, 'suspended_at' => null])->save();
        $this->audit->log('company.activated', $company, [], $company->id);

        return $company;
    }
}
