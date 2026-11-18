<?php

namespace App\Actions\Websites;

use App\Models\Website;
use App\Models\Widget;
use App\Services\AuditLogger;
use App\Support\Widget\DomainAuthorizer;
use Illuminate\Support\Facades\DB;

/**
 * Cria/atualiza um site e sincroniza os domínios autorizados.
 * Um site novo recebe automaticamente um widget padrão.
 */
class SaveWebsite
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, url: string, domains: list<string>}  $data
     */
    public function handle(Website $website, array $data): Website
    {
        return DB::transaction(function () use ($website, $data): Website {
            $isNew = ! $website->exists;

            $website->fill(['name' => $data['name'], 'url' => $data['url']])->save();

            $hosts = collect($data['domains'])
                ->push($data['url'])
                ->map(fn (string $d) => DomainAuthorizer::normalize($d))
                ->filter()
                ->unique()
                ->values();

            $website->domains()->whereNotIn('host', $hosts)->delete();

            foreach ($hosts as $host) {
                $website->domains()->firstOrCreate(
                    ['host' => $host],
                    ['company_id' => $website->company_id],
                );
            }

            if ($isNew) {
                Widget::create([
                    'company_id' => $website->company_id,
                    'website_id' => $website->id,
                    'name' => 'Widget '.$website->name,
                    'welcome_message' => 'Olá! Como podemos ajudar?',
                    'offline_message' => 'No momento não há atendentes online. Deixe sua mensagem que retornaremos em breve.',
                ]);
            }

            $this->audit->log($isNew ? 'website.created' : 'website.updated', $website, [
                'domains' => $hosts->all(),
            ]);

            return $website;
        });
    }
}
