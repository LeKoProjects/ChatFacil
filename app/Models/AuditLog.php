<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Logs de auditoria. Sem escopo global: leitura apenas por consultas
 * explícitas filtradas por empresa ou pela administração NEXO.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['company_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'metadata', 'ip_address'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
