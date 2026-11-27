<?php

namespace App\Events;

use App\Models\Conversation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Mudança de estado enviada ao widget: só status e nome do atendente.
 * Disparado pelo listener de ConversationUpdated (já após o commit).
 */
class VisitorConversationUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Conversation $conversation, public string $reason) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->conversation->channelName());
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return app(TenantContext::class)->runAs($this->conversation->company_id, function (): array {
            $this->conversation->load('assignee');

            return [
                'reason' => $this->reason,
                'conversation' => [
                    'id' => $this->conversation->uuid,
                    'status' => $this->conversation->status->value,
                    'assignee' => $this->conversation->assignee ? ['name' => $this->conversation->assignee->name] : null,
                ],
            ];
        });
    }
}
