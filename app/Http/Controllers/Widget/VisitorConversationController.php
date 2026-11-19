<?php

namespace App\Http\Controllers\Widget;

use App\Actions\Conversations\SendMessage;
use App\Actions\Conversations\StartConversation;
use App\Enums\MessageSender;
use App\Events\TypingChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\Widget\StartConversationRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Visitor;
use App\Services\Chatbot\BotEngine;
use App\Support\Widget\WidgetConversationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * API do visitante (guard `visitor`). Todas as consultas ficam restritas à
 * empresa do widget (escopo global) e ao próprio visitante.
 */
class VisitorConversationController extends Controller
{
    /** Histórico atual; ?after=<seq> recupera mensagens perdidas após reconexão. */
    public function show(Request $request, WidgetConversationPayload $payload): JsonResponse
    {
        $conversation = $this->currentConversation($this->visitor($request), includeClosed: true);

        return response()->json($payload->make($conversation, (int) $request->query('after', 0)));
    }

    public function store(StartConversationRequest $request, StartConversation $start, WidgetConversationPayload $payload): JsonResponse
    {
        $visitor = $this->visitor($request);

        $conversation = $start->handle(
            $visitor,
            $visitor->currentSession,
            $request->safe()->only(['name', 'email', 'phone']),
            $request->validated('message'),
            $request->validated('client_message_id'),
        );

        return response()->json($payload->make($conversation), 201);
    }

    public function sendMessage(SendMessageRequest $request, SendMessage $send, BotEngine $bot): JsonResponse
    {
        $conversation = $this->currentConversation($this->visitor($request));

        abort_if(! $conversation, 404, 'Nenhuma conversa ativa.');

        $message = $send->handle(
            $conversation,
            MessageSender::Visitor,
            $request->validated('body'),
            $request->validated('client_message_id'),
        );

        // Enquanto está com o chatbot, a mensagem digitada é respondida pela FAQ.
        $replies = $message->wasRecentlyCreated ? $bot->handleText($conversation->refresh(), $message->body) : [];

        $message->load(['conversation.visitor', 'user']);

        return response()->json([
            'message' => (new MessageResource($message))->resolve(),
            'replies' => $this->resources($replies),
        ], 201);
    }

    /** O visitante clicou em uma opção do chatbot. Devolve as mensagens novas. */
    public function selectOption(Request $request, BotEngine $bot, WidgetConversationPayload $payload): JsonResponse
    {
        $data = $request->validate([
            'option' => ['required', 'string', 'max:80'],
            'client_message_id' => ['required', 'uuid'],
        ]);

        $conversation = $this->currentConversation($this->visitor($request));

        abort_if(! $conversation, 404, 'Nenhuma conversa ativa.');

        $before = (int) $conversation->messages()->max('id');
        $bot->select($conversation, $data['option'], $data['client_message_id']);

        return response()->json($payload->make($conversation->refresh(), $before));
    }

    /**
     * @param  list<Message>  $messages
     * @return list<array<string, mixed>>
     */
    private function resources(array $messages): array
    {
        foreach ($messages as $m) {
            $m->load(['conversation.visitor', 'user']);
        }

        return MessageResource::collection($messages)->resolve();
    }

    public function typing(Request $request): JsonResponse
    {
        $request->validate(['typing' => ['required', 'boolean']]);
        $visitor = $this->visitor($request);
        $conversation = $this->currentConversation($visitor);

        if ($conversation) {
            broadcast(new TypingChanged($conversation, 'visitor', $visitor->displayName(), $request->boolean('typing')))->toOthers();
        }

        return response()->json(['ok' => true]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $conversation = $this->currentConversation($this->visitor($request), includeClosed: true);

        if ($conversation) {
            $conversation->messages()
                ->where('sender_type', MessageSender::Agent->value)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $conversation->forceFill(['visitor_unread_count' => 0])->save();
        }

        return response()->json(['ok' => true]);
    }

    /** Autorização de canais privados do Reverb para o visitante. */
    public function broadcastingAuth(Request $request): mixed
    {
        return Broadcast::auth($request);
    }

    private function visitor(Request $request): Visitor
    {
        /** @var Visitor $visitor */
        $visitor = $request->user('visitor');

        return $visitor;
    }

    private function currentConversation(Visitor $visitor, bool $includeClosed = false): ?Conversation
    {
        return Conversation::query()
            ->where('visitor_id', $visitor->id)
            ->when(! $includeClosed, fn ($q) => $q->active())
            ->orderByRaw("CASE WHEN status <> 'closed' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();
    }
}
