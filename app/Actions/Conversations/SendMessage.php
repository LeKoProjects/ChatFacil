<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persiste uma mensagem e agenda a entrega em tempo real.
 *
 * - Transação + lock na conversa: ordem e contadores consistentes.
 * - Idempotência: (conversation_id, client_message_id) único. Um reenvio do
 *   mesmo client_message_id retorna a mensagem já gravada, sem duplicar.
 * - O evento só é despachado após o commit (ShouldDispatchAfterCommit).
 */
class SendMessage
{
    public function __construct(private readonly AssignConversation $assign) {}

    public function handle(
        Conversation $conversation,
        MessageSender $sender,
        string $body,
        ?string $clientMessageId = null,
        ?User $agent = null,
    ): Message {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'A mensagem não pode ser vazia.']);
        }

        return DB::transaction(function () use ($conversation, $sender, $body, $clientMessageId, $agent): Message {
            /** @var Conversation $locked */
            $locked = Conversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();

            if ($clientMessageId !== null) {
                $existing = Message::query()
                    ->where('conversation_id', $locked->id)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            if ($locked->isClosed()) {
                throw ValidationException::withMessages(['body' => 'Esta conversa foi encerrada.']);
            }

            // Atendente respondendo conversa sem responsável assume o atendimento.
            if ($sender === MessageSender::Agent && $agent && $locked->assigned_user_id === null) {
                $this->assign->handle($locked, $agent, $agent);
                $locked->refresh();
            }

            $message = Message::create([
                'company_id' => $locked->company_id,
                'conversation_id' => $locked->id,
                'sender_type' => $sender,
                'user_id' => $sender === MessageSender::Agent ? $agent?->id : null,
                'client_message_id' => $clientMessageId,
                'body' => $body,
                'delivered_at' => now(),
            ]);

            $updates = ['last_message_at' => $message->created_at];

            if ($sender === MessageSender::Visitor) {
                $updates['agent_unread_count'] = $locked->agent_unread_count + 1;
                if ($locked->status === ConversationStatus::Pending) {
                    $updates['status'] = ConversationStatus::Open;
                }
            }

            if ($sender === MessageSender::Agent) {
                $updates['visitor_unread_count'] = $locked->visitor_unread_count + 1;
                $updates['first_response_at'] = $locked->first_response_at ?? $message->created_at;
            }

            $locked->forceFill($updates)->save();

            $message->setRelation('conversation', $locked);
            MessageCreated::dispatch($message);

            return $message;
        });
    }
}
