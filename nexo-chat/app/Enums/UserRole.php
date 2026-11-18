<?php

namespace App\Enums;

enum UserRole: string
{
    case PlatformAdmin = 'platform_admin';
    case CompanyAdmin = 'company_admin';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Administrador NEXO',
            self::CompanyAdmin => 'Administrador',
            self::Agent => 'Atendente',
        };
    }
}
