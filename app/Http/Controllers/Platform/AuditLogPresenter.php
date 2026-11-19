<?php

namespace App\Http\Controllers\Platform;

use App\Models\AuditLog;

/**
 * Formatação dos registros de auditoria para as telas da administração NEXO.
 */
class AuditLogPresenter
{
    public const LABELS = [
        'company.registered' => 'Empresa cadastrada',
        'company.created_by_nexo' => 'Empresa criada pela NEXO',
        'company.updated' => 'Dados da empresa alterados',
        'company.suspended' => 'Empresa suspensa',
        'company.activated' => 'Empresa reativada',
        'website.created' => 'Site cadastrado',
        'website.updated' => 'Site alterado',
        'website.deleted' => 'Site removido',
        'widget.updated' => 'Widget personalizado',
        'agent.created' => 'Atendente criado',
        'agent.updated' => 'Atendente alterado',
        'agent.deleted' => 'Atendente removido',
        'conversation.assigned' => 'Conversa atribuída',
        'conversation.closed' => 'Conversa encerrada',
        'conversation.reopened' => 'Conversa reaberta',
    ];

    /** @return array<string, mixed> */
    public static function present(AuditLog $log, ?string $companyName = null, ?string $userName = null): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'label' => self::LABELS[$log->action] ?? $log->action,
            'company_id' => $log->company_id,
            'company_name' => $companyName,
            'user_name' => $userName,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
