<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Mudança de estado da conversa (nova, atribuída, encerrada, reaberta).
 * Atualiza filas da central e o widget do visitante.
 */
class ConversationUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation, public string $reason) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->conversation->channelName()),
            new PrivateChannel('company.'.$this->conversation->company_id.'.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return app(TenantContext::class)->runAs($this->conversation->company_id, function (): array {
            $this->conversation->load(['visitor', 'widget', 'assignee', 'latestMessage']);

            return [
                'reason' => $this->reason,
                'conversation' => (new ConversationResource($this->conversation))->resolve(),
            ];
        });
    }
}
