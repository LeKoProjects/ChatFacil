<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Visitor;
use App\Models\VisitorSession;
use App\Services\Chatbot\BotEngine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Inicia (ou retoma) a conversa ativa do visitante e grava a primeira mensagem.
 * O índice único parcial garante uma única conversa ativa por visitante.
 * Com o chatbot ligado, a conversa começa com o bot (status "bot") e só
 * entra na fila da equipe quando o visitante pedir ou o bot transferir.
 */
class StartConversation
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly BotEngine $bot,
    ) {}

    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}  $identity
     */
    public function handle(
        Visitor $visitor,
        VisitorSession $session,
        array $identity,
        string $firstMessage,
        ?string $clientMessageId = null,
    ): Conversation {
        $withBot = $this->bot->isActive();

        $conversation = DB::transaction(function () use ($visitor, $session, $identity, $withBot): Conversation {
            $visitor->fill(array_filter([
                'name' => $identity['name'] ?? null,
                'email' => $identity['email'] ?? null,
                'phone' => $identity['phone'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
            $visitor->last_seen_at = now();
            $visitor->save();

            $active = Conversation::query()->where('visitor_id', $visitor->id)->active()->first();

            if ($active) {
                return $active;
            }

            try {
                return DB::transaction(fn () => Conversation::create([
                    'company_id' => $visitor->company_id,
                    'widget_id' => $session->widget_id,
                    'visitor_id' => $visitor->id,
                    'origin_url' => $session->page_url,
                    'status' => $withBot ? ConversationStatus::Bot : ConversationStatus::Waiting,
                ]));
            } catch (UniqueConstraintViolationException) {
                return Conversation::query()->where('visitor_id', $visitor->id)->active()->firstOrFail();
            }
        });

        $isNew = $conversation->wasRecentlyCreated;

        $first = $this->sendMessage->handle($conversation, MessageSender::Visitor, $firstMessage, $clientMessageId);

        if ($isNew) {
            ConversationUpdated::dispatch($conversation->refresh(), 'created');
        }

        if ($first->wasRecentlyCreated && $conversation->refresh()->isWithBot()) {
            $isNew
                ? $this->bot->greet($conversation, $firstMessage)
                : $this->bot->handleText($conversation, $firstMessage);
        }

        return $conversation->refresh();
    }
}
