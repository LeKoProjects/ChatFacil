<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumo da conversa para a central de atendimento (lista + painel lateral).
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(?Request $request = null): array
    {
        $latest = $this->relationLoaded('latestMessage') ? $this->latestMessage : null;

        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority,
            'origin_url' => $this->origin_url,
            'agent_unread_count' => $this->agent_unread_count,
            'created_at' => $this->created_at->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'bot_trail' => $this->bot_trail ?? [],
            'handoff_tag' => $this->handoff_tag,
            'visitor' => [
                'id' => $this->visitor->uuid,
                'name' => $this->visitor->displayName(),
                'email' => $this->visitor->email,
                'phone' => $this->visitor->phone,
                'last_seen_at' => $this->visitor->last_seen_at?->toIso8601String(),
            ],
            'widget' => [
                'name' => $this->widget->name,
            ],
            'assignee' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null,
            'last_message' => $latest ? [
                'body' => mb_strimwidth($latest->body, 0, 120, '…'),
                'sender_type' => $latest->sender_type->value,
                'created_at' => $latest->created_at->toIso8601String(),
            ] : null,
        ];
    }
}
