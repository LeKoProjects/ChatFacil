<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Companies\ChangeCompanyStatus;
use App\Actions\Companies\RegisterCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreCompanyRequest;
use App\Http\Requests\Platform\UpdateCompanyRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Models\Website;
use App\Models\Widget;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestão de empresas clientes pela administração NEXO.
 */
class CompanyController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['active', 'suspended'], true) ? $request->query('status') : null;

        $companies = $this->tenant->withoutScope(fn () => Company::query()
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn (Builder $w) => $w
                    ->where('name', 'ilike', $like)
                    ->orWhere('slug', 'ilike', $like)
                    ->orWhere('document', 'ilike', $like)
                    ->orWhereHas('users', fn (Builder $u) => $u->where('email', 'ilike', $like)));
            })
            ->when($status, fn (Builder $q) => $q->where('status', $status))
            ->withCount([
                'users',
                'websites',
                'conversations as conversations_30d' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30)),
            ])
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'document' => $c->document,
                'status' => $c->status->value,
                'users_count' => $c->users_count,
                'websites_count' => $c->websites_count,
                'conversations_30d' => $c->conversations_30d,
                'created_at' => $c->created_at->toIso8601String(),
                'suspended_at' => $c->suspended_at?->toIso8601String(),
            ]));

        return Inertia::render('nexo/companies/Index', [
            'companies' => $companies,
            'filters' => ['q' => $search, 'status' => $status],
        ]);
    }

    public function store(StoreCompanyRequest $request, RegisterCompany $register, AuditLogger $audit): RedirectResponse
    {
        $admin = $register->handle($request->validated());

        $audit->log('company.created_by_nexo', $admin->company, ['admin_email' => $admin->email], $admin->company_id);

        return redirect()->route('nexo.companies.show', $admin->company_id)->with('success', 'Empresa criada.');
    }

    public function show(Company $company): Response
    {
        $data = $this->tenant->withoutScope(function () use ($company): array {
            $since = now()->subDays(30);

            return [
                'users' => User::query()->inCompany($company->id)->withTrashed()->orderBy('name')->get()->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role_label' => $u->role->label(),
                    'availability' => $u->availability->value,
                    'is_active' => $u->is_active && ! $u->trashed(),
                    'last_seen_at' => $u->last_seen_at?->toIso8601String(),
                ]),
                'websites' => Website::query()->where('company_id', $company->id)->with(['domains', 'widgets'])->get()->map(fn (Website $w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'url' => $w->url,
                    'domains' => $w->domains->pluck('host'),
                    'widgets' => $w->widgets->map(fn (Widget $wg) => [
                        'name' => $wg->name,
                        'public_key' => $wg->public_key,
                        'is_active' => $wg->is_active,
                    ]),
                ]),
                'stats' => [
                    'conversations_total' => $company->conversations()->count(),
                    'conversations_30d' => $company->conversations()->where('created_at', '>=', $since)->count(),
                    'active_now' => $company->conversations()->active()->count(),
                ],
                'audit' => AuditLog::query()
                    ->where('company_id', $company->id)
                    ->latest('id')
                    ->limit(20)
                    ->get()
                    ->map(fn (AuditLog $a) => AuditLogPresenter::present($a)),
            ];
        });

        return Inertia::render('nexo/companies/Show', [
            'company' => [
                'id' => $company->id,
                'uuid' => $company->uuid,
                'name' => $company->name,
                'slug' => $company->slug,
                'document' => $company->document,
                'status' => $company->status->value,
                'created_at' => $company->created_at->toIso8601String(),
                'suspended_at' => $company->suspended_at?->toIso8601String(),
            ],
            ...$data,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        $company->update($request->validated());
        $audit->log('company.updated', $company, ['fields' => array_keys($request->validated())], $company->id);

        return back()->with('success', 'Dados da empresa atualizados.');
    }

    public function suspend(Request $request, Company $company, ChangeCompanyStatus $change): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $change->suspend($company, $data['reason'] ?? null);

        return back()->with('success', "Empresa {$company->name} suspensa.");
    }

    public function activate(Company $company, ChangeCompanyStatus $change): RedirectResponse
    {
        $change->activate($company);

        return back()->with('success', "Empresa {$company->name} reativada.");
    }
}
