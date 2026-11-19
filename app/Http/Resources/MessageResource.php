<?php

namespace App\Http\Resources;

use App\Enums\MessageSender;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representação pública de uma mensagem — usada pelo widget, pela central e
 * nos eventos de broadcast. Nunca expõe IDs internos nem e-mails de atendentes.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(?Request $request = null): array
    {
        return [
            'id' => $this->uuid,
            'seq' => $this->id,
            'conversation_id' => $this->conversation->uuid,
            'sender_type' => $this->sender_type->value,
            'author_name' => match ($this->sender_type) {
                MessageSender::Agent => $this->user?->name ?? 'Atendente',
                MessageSender::System => 'Sistema',
                MessageSender::Bot => 'Assistente virtual',
                MessageSender::Visitor => $this->conversation->visitor->displayName(),
            },
            'body' => $this->body,
            'options' => $this->meta['options'] ?? [],
            'client_message_id' => $this->client_message_id,
            'created_at' => $this->created_at->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
        ];
    }
}
