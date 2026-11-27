<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Support\Tenancy\TenantContext;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Entrega da mensagem ao widget (canal da conversa, assinado pelo visitante).
 * Só leva a mensagem: dados internos da conversa ficam no canal da central.
 * Disparado pelo listener de MessageCreated (já após o commit).
 */
class VisitorMessageCreated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        $conversation = app(TenantContext::class)->runAs(
            $this->message->company_id,
            fn () => $this->message->load('conversation')->conversation,
        );

        return new PrivateChannel($conversation->channelName());
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return app(TenantContext::class)->runAs($this->message->company_id, function (): array {
            $this->message->load(['conversation.visitor', 'user']);

            return ['message' => (new MessageResource($this->message))->resolve()];
        });
    }
}
