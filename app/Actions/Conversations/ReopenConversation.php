<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenConversation
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Conversation $conversation, User $by): Conversation
    {
        if (! $conversation->isClosed()) {
            return $conversation;
        }

        $hasActive = Conversation::query()
            ->where('visitor_id', $conversation->visitor_id)
            ->active()
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages([
                'conversation' => 'O visitante já possui outra conversa ativa.',
            ]);
        }

        return DB::transaction(function () use ($conversation, $by): Conversation {
            $conversation->forceFill([
                'status' => $conversation->assigned_user_id ? ConversationStatus::Open : ConversationStatus::Waiting,
                'closed_at' => null,
                'closed_by_user_id' => null,
            ])->save();

            $this->audit->log('conversation.reopened', $conversation, ['by_user_id' => $by->id]);

            ConversationUpdated::dispatch($conversation, 'reopened');

            return $conversation;
        });
    }
}
