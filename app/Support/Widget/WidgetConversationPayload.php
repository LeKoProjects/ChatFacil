<?php

namespace App\Support\Widget;

use App\Http\Resources\MessageResource;
use App\Models\Conversation;

/**
 * Payload de conversa entregue ao visitante (histórico + estado).
 */
class WidgetConversationPayload
{
    /** @return array{conversation: array<string, mixed>|null, messages: list<array<string, mixed>>} */
    public function make(?Conversation $conversation, int $afterSeq = 0): array
    {
        if (! $conversation) {
            return ['conversation' => null, 'messages' => []];
        }

        $messages = $conversation->messages()
            ->with(['user', 'conversation.visitor'])
            ->when($afterSeq > 0, fn ($q) => $q->where('id', '>', $afterSeq))
            ->orderBy('id')
            ->limit(500)
            ->get();

        return [
            'conversation' => [
                'id' => $conversation->uuid,
                'status' => $conversation->status->value,
                'channel' => $conversation->channelName(),
                'agent' => $conversation->assignee?->name,
                'unread' => $conversation->visitor_unread_count,
            ],
            'messages' => MessageResource::collection($messages)->resolve(),
        ];
    }
}
