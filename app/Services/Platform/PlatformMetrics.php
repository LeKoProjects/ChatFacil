<?php

namespace App\Services\Platform;

use App\Enums\AgentAvailability;
use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Widget;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores da plataforma inteira (todas as empresas). Uso exclusivo da
 * administração NEXO — por isso consulta explicitamente sem o escopo de empresa.
 */
class PlatformMetrics
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        return $this->tenant->withoutScope(function (): array {
            $since = now()->subDays(30);

            $daily = Conversation::query()
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw('date(created_at) as day, count(*) as total')
                ->groupBy(DB::raw('date(created_at)'))
                ->pluck('total', 'day');

            return [
                'companies' => [
                    'total' => Company::count(),
                    'active' => Company::where('status', CompanyStatus::Active->value)->count(),
                    'suspended' => Company::where('status', CompanyStatus::Suspended->value)->count(),
                    'new_30d' => Company::where('created_at', '>=', $since)->count(),
                ],
                'users' => [
                    'total' => User::where('role', '!=', UserRole::PlatformAdmin->value)->count(),
                    'online' => User::where('role', '!=', UserRole::PlatformAdmin->value)
                        ->where('is_active', true)
                        ->where('availability', AgentAvailability::Online->value)
                        ->count(),
                ],
                'widgets_active' => Widget::where('is_active', true)->count(),
                'conversations' => [
                    'today' => Conversation::where('created_at', '>=', now()->startOfDay())->count(),
                    'last_30d' => Conversation::where('created_at', '>=', $since)->count(),
                    'active_now' => Conversation::active()->count(),
                ],
                'messages_30d' => Message::where('created_at', '>=', $since)->count(),
                'series' => collect(range(13, 0))->map(function (int $daysAgo) use ($daily) {
                    $day = now()->subDays($daysAgo)->toDateString();

                    return ['day' => $day, 'total' => (int) ($daily[$day] ?? 0)];
                })->values(),
                'top_companies' => Company::query()
                    ->withCount(['conversations as conversations_30d' => fn ($q) => $q->where('created_at', '>=', $since)])
                    ->orderByDesc('conversations_30d')
                    ->limit(5)
                    ->get(['id', 'name', 'status'])
                    ->map(fn (Company $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'status' => $c->status->value,
                        'conversations_30d' => (int) $c->conversations_30d,
                    ]),
            ];
        });
    }
}
