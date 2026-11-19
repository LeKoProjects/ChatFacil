<?php

namespace App\Actions\Companies;

use App\Enums\AgentAvailability;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria uma empresa (tenant) e seu primeiro administrador em uma transação.
 */
class RegisterCompany
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{company_name: string, name: string, email: string, password: string, document?: string|null}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => $this->uniqueSlug($data['company_name']),
                'document' => $data['document'] ?? null,
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::CompanyAdmin,
                'availability' => AgentAvailability::Offline,
            ]);

            $this->tenant->runAs($company, fn () => $this->audit->log('company.registered', $company, [
                'admin_user_id' => $user->id,
            ]));

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'empresa';
        $slug = $base;
        $i = 2;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
