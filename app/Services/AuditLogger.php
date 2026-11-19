<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?Model $subject = null, array $metadata = [], ?int $companyId = null): AuditLog
    {
        $user = Auth::guard('web')->user();

        return AuditLog::create([
            'company_id' => $companyId ?? $this->tenant->id(),
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => Request::ip(),
        ]);
    }
}
