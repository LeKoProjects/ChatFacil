<?php

namespace App\Actions\Conversations;

use App\Enums\MessageSender;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Visitor;
use App\Models\VisitorSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Inicia (ou retoma) a conversa ativa do visitante e grava a primeira mensagem.
 * O índice único parcial garante uma única conversa ativa por visitante.
 */
class StartConversation
{
    public function __construct(private readonly SendMessage $sendMessage) {}

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
        $conversation = DB::transaction(function () use ($visitor, $session, $identity): Conversation {
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
                ]));
            } catch (UniqueConstraintViolationException) {
                return Conversation::query()->where('visitor_id', $visitor->id)->active()->firstOrFail();
            }
        });

        $isNew = $conversation->wasRecentlyCreated;

        $this->sendMessage->handle($conversation, MessageSender::Visitor, $firstMessage, $clientMessageId);

        if ($isNew) {
            ConversationUpdated::dispatch($conversation->refresh(), 'created');
        }

        return $conversation->refresh();
    }
}
