<?php

namespace App\Http\Controllers\Agent;

use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Central de atendimento: lista (esquerda), mensagens (centro) e dados do
 * visitante (direita) em uma única página — mínimo de navegação.
 */
class InboxController extends Controller
{
    private const FILTERS = ['waiting', 'mine', 'open', 'closed', 'all'];

    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'mine_and_waiting';
        $search = trim((string) $request->query('q', ''));

        if ($conversation) {
            $this->authorize('view', $conversation);
        }

        $conversations = Conversation::query()
            ->with(['visitor', 'widget', 'assignee', 'latestMessage'])
            ->tap(fn (Builder $q) => $this->applyFilter($q, $filter, $user))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->whereHas('visitor', fn (Builder $v) => $v
                    ->where('name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like));
            })
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 WHEN 'open' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        $counts = Conversation::query()
            ->selectRaw("count(*) filter (where status = 'waiting') as waiting")
            ->selectRaw("count(*) filter (where status in ('open','pending') and assigned_user_id = ?) as mine", [$user->id])
            ->selectRaw("count(*) filter (where status in ('open','pending')) as open")
            ->first();

        $selected = null;
        $messages = [];
        $history = [];

        if ($conversation) {
            $conversation->load(['visitor', 'widget', 'assignee', 'latestMessage']);

            if ($conversation->agent_unread_count > 0) {
                $conversation->messages()
                    ->where('sender_type', MessageSender::Visitor->value)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
                $conversation->forceFill(['agent_unread_count' => 0])->save();
            }

            $selected = (new ConversationResource($conversation))->resolve();
            $messages = MessageResource::collection(
                $conversation->messages()->with(['user', 'conversation.visitor'])->orderBy('id')->limit(500)->get()
            )->resolve();

            $history = Conversation::query()
                ->where('visitor_id', $conversation->visitor_id)
                ->whereKeyNot($conversation->id)
                ->latest('id')
                ->limit(10)
                ->get(['uuid', 'status', 'created_at', 'closed_at'])
                ->map(fn (Conversation $c) => [
                    'id' => $c->uuid,
                    'status' => $c->status->value,
                    'status_label' => $c->status->label(),
                    'created_at' => $c->created_at->toIso8601String(),
                ]);
        }

        return Inertia::render('inbox/Index', [
            'filter' => $filter,
            'search' => $search,
            'conversations' => ConversationResource::collection($conversations)->resolve(),
            'counts' => [
                'waiting' => (int) $counts?->waiting,
                'mine' => (int) $counts?->mine,
                'open' => (int) $counts?->open,
            ],
            'selected' => $selected,
            'messages' => $messages,
            'history' => $history,
            'agents' => User::query()
                ->inCompany($user->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'availability'])
                ->map(fn (User $a) => ['id' => $a->id, 'name' => $a->name, 'availability' => $a->availability->value]),
        ]);
    }

    private function applyFilter(Builder $query, string $filter, User $user): void
    {
        match ($filter) {
            'waiting' => $query->where('status', ConversationStatus::Waiting->value),
            'mine' => $query->where('assigned_user_id', $user->id)->active(),
            'open' => $query->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value]),
            'closed' => $query->where('status', ConversationStatus::Closed->value),
            'all' => null,
            default => $query->where(fn (Builder $q) => $q
                ->where('status', ConversationStatus::Waiting->value)
                ->orWhere(fn (Builder $m) => $m->where('assigned_user_id', $user->id)->active())),
        };
    }
}
