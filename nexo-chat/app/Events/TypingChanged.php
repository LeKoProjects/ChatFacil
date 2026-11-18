<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Indicador de digitação — efêmero, não persistido, entregue imediatamente.
 */
class TypingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Conversation $conversation,
        public string $senderType,
        public string $name,
        public bool $typing,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->conversation->channelName());
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->uuid,
            'sender_type' => $this->senderType,
            'name' => $this->name,
            'typing' => $this->typing,
        ];
    }
}
