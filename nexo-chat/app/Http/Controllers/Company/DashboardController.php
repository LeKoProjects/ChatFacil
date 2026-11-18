<?php

namespace App\Http\Controllers\Company;

use App\Enums\AgentAvailability;
use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel básico da empresa — indicadores calculados no PostgreSQL
 * (nenhum número estático).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $companyId = $request->user()->company_id;
        $since = now()->subDays(30);

        $stats = Conversation::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('count(*) as received')
            ->selectRaw('count(*) filter (where first_response_at is not null) as answered')
            ->selectRaw("count(*) filter (where status = 'closed' and first_response_at is null) as missed")
            ->selectRaw('avg(extract(epoch from (first_response_at - created_at))) filter (where first_response_at is not null) as avg_first_response')
            ->selectRaw("avg(extract(epoch from (closed_at - created_at))) filter (where status = 'closed') as avg_duration")
            ->first();

        $daily = Conversation::query()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy(DB::raw('date(created_at)'))
            ->orderBy('day')
            ->pluck('total', 'day');

        $series = collect(range(13, 0))->map(function (int $daysAgo) use ($daily) {
            $day = now()->subDays($daysAgo)->toDateString();

            return ['day' => $day, 'total' => (int) ($daily[$day] ?? 0)];
        })->values();

        return Inertia::render('dashboard/Index', [
            'stats' => [
                'received' => (int) $stats?->received,
                'answered' => (int) $stats?->answered,
                'missed' => (int) $stats?->missed,
                'avg_first_response_seconds' => $stats?->avg_first_response !== null ? (int) round((float) $stats->avg_first_response) : null,
                'avg_duration_seconds' => $stats?->avg_duration !== null ? (int) round((float) $stats->avg_duration) : null,
                'waiting_now' => Conversation::query()->where('status', ConversationStatus::Waiting->value)->count(),
                'open_now' => Conversation::query()->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])->count(),
                'agents_online' => User::query()->inCompany($companyId)->where('is_active', true)->where('availability', AgentAvailability::Online->value)->count(),
                'websites' => Website::query()->count(),
            ],
            'series' => $series,
        ]);
    }
}
