<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Auditoria da plataforma inteira (todas as empresas).
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $companyId = $request->integer('company') ?: null;
        $action = array_key_exists((string) $request->query('action'), AuditLogPresenter::LABELS) ? (string) $request->query('action') : null;

        $logs = AuditLog::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $companies = Company::withTrashed()->whereIn('id', $logs->pluck('company_id')->filter()->unique())->pluck('name', 'id');
        $users = User::withTrashed()->whereIn('id', $logs->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return Inertia::render('nexo/Audit', [
            'logs' => $logs->through(fn (AuditLog $log) => AuditLogPresenter::present(
                $log,
                $companies[$log->company_id] ?? null,
                $users[$log->user_id] ?? null,
            )),
            'filters' => ['company' => $companyId, 'action' => $action],
            'companies' => Company::withTrashed()->orderBy('name')->get(['id', 'name'])->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name]),
            'actions' => collect(AuditLogPresenter::LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }
}
