<?php

namespace Database\Seeders;

use App\Actions\Companies\RegisterCompany;
use App\Actions\Websites\SaveWebsite;
use App\Enums\AgentAvailability;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Website;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Dados iniciais: administrador NEXO, duas empresas com admin, atendente,
 * site/widget e o chatbot de demonstração. Pode rodar de novo sem duplicar.
 * Senha de todos os usuários: "password" (troque após o primeiro acesso).
 * Não roda em produção: lá as empresas e usuários são criados pelo painel
 * /nexo, de acordo com cada cliente.
 */
class DatabaseSeeder extends Seeder
{
    public function run(RegisterCompany $registerCompany, SaveWebsite $saveWebsite, TenantContext $tenant): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Seeder ignorado em produção: crie empresas e usuários pelo painel /nexo.');

            return;
        }

        User::query()->firstOrCreate(['email' => 'admin@nexo.local'], [
            'name' => 'Administração NEXO',
            'password' => 'password',
            'role' => UserRole::PlatformAdmin,
            'company_id' => null,
        ]);

        // Chaves fixas de demonstração: os links de teste continuam válidos
        // mesmo após recriar o banco (migrate:fresh --seed).
        $demoKeys = [
            'demo' => 'wgt_demo0000000000000000000000',
            'outra' => 'wgt_outra000000000000000000000',
        ];

        foreach ([['Empresa Demo', 'demo'], ['Outra Empresa', 'outra']] as [$companyName, $slug]) {
            if (User::where('email', "admin@{$slug}.local")->exists()) {
                continue;
            }

            $admin = $registerCompany->handle([
                'company_name' => $companyName,
                'name' => "Admin {$companyName}",
                'email' => "admin@{$slug}.local",
                'password' => 'password',
            ]);

            User::create([
                'company_id' => $admin->company_id,
                'name' => "Atendente {$companyName}",
                'email' => "atendente@{$slug}.local",
                'password' => 'password',
                'role' => UserRole::Agent,
                'availability' => AgentAvailability::Online,
            ]);

            $tenant->runAs($admin->company_id, function () use ($saveWebsite, $companyName, $demoKeys, $slug): void {
                $website = $saveWebsite->handle(new Website, [
                    'name' => "Site {$companyName}",
                    'url' => 'http://localhost:8080',
                    'domains' => ['localhost:8080', '127.0.0.1:8080'],
                ]);

                $website->widgets()->firstOrFail()->forceFill(['public_key' => $demoKeys[$slug]])->save();
            });
        }

        $this->call(ChatbotDemoSeeder::class);
    }
}
