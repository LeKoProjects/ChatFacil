<?php

namespace App\Support\Widget;

use App\Models\Widget;
use App\Models\WebsiteDomain;

/**
 * Valida se um host pode usar o widget. O identificador público do widget
 * não é credencial: a emissão de sessão e o carregamento do iframe exigem
 * que o host de origem esteja entre os domínios autorizados do site.
 *
 * Formatos aceitos em website_domains.host:
 *  - "exemplo.com.br"      => somente esse host
 *  - "*.exemplo.com.br"    => qualquer subdomínio (não inclui o domínio raiz)
 *  - "localhost:8000"      => host com porta (útil em desenvolvimento)
 */
class DomainAuthorizer
{
    public static function normalize(string $value): ?string
    {
        $value = trim(mb_strtolower($value));

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '*.')) {
            $rest = self::normalize(substr($value, 2));

            return $rest ? '*.'.$rest : null;
        }

        if (! str_contains($value, '://')) {
            $value = 'http://'.$value;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = rtrim($parts['host'], '.');

        if (! preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        return isset($parts['port']) ? $host.':'.$parts['port'] : $host;
    }

    /** @return list<string> */
    public function allowedHosts(Widget $widget): array
    {
        return WebsiteDomain::query()
            ->where('company_id', $widget->company_id)
            ->where('website_id', $widget->website_id)
            ->pluck('host')
            ->all();
    }

    public function isAllowed(Widget $widget, ?string $origin): bool
    {
        if ($origin === null || $origin === '') {
            return false;
        }

        $candidate = self::normalize($origin);

        if ($candidate === null) {
            return false;
        }

        $candidateHostOnly = explode(':', $candidate)[0];

        foreach ($this->allowedHosts($widget) as $allowed) {
            if ($allowed === $candidate || $allowed === $candidateHostOnly) {
                return true;
            }

            if (str_starts_with($allowed, '*.')) {
                $suffix = substr($allowed, 1); // ".exemplo.com"
                if (str_ends_with($candidateHostOnly, $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Valor para o cabeçalho CSP frame-ancestors (aplicado pelo navegador).
     */
    public function frameAncestors(Widget $widget): string
    {
        $sources = [];

        foreach ($this->allowedHosts($widget) as $host) {
            $sources[] = 'https://'.$host;
            $sources[] = 'http://'.$host;
        }

        return $sources === [] ? "'none'" : implode(' ', array_unique($sources));
    }
}
