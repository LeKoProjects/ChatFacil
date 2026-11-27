<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Support\Tenancy\TenantContext;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado somente após o commit da transação: a mensagem já está
 * persistida antes de qualquer tentativa de entrega via WebSocket.
 * A entrega é enfileirada (Redis) — se o Reverb estiver indisponível,
 * a gravação não é afetada e o cliente recupera pelo histórico.
 */
class MessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        // Recarrega dentro do contexto da empresa: relações serializadas no job
        // são restauradas pelo worker sem contexto e podem vir vazias.
        $conversation = app(TenantContext::class)->runAs(
            $this->message->company_id,
            fn () => $this->message->load('conversation')->conversation,
        );

        return [
            new PrivateChannel('company.'.$conversation->company_id.'.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return app(TenantContext::class)->runAs($this->message->company_id, function (): array {
            $this->message->load(['conversation.visitor', 'conversation.widget', 'conversation.assignee', 'user']);
            $conversation = $this->message->conversation->setRelation('latestMessage', $this->message);

            return [
                'message' => (new MessageResource($this->message))->resolve(),
                'conversation' => (new ConversationResource($conversation))->resolve(),
            ];
        });
    }
}
