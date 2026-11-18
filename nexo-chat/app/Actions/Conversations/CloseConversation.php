<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Enums\MessageSender;
use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CloseConversation
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Conversation $conversation, ?User $by = null): Conversation
    {
        if ($conversation->isClosed()) {
            return $conversation;
        }

        return DB::transaction(function () use ($conversation, $by): Conversation {
            $now = now();

            $conversation->forceFill([
                'status' => ConversationStatus::Closed,
                'closed_at' => $now,
                'closed_by_user_id' => $by?->id,
            ])->save();

            $system = Message::create([
                'company_id' => $conversation->company_id,
                'conversation_id' => $conversation->id,
                'sender_type' => MessageSender::System,
                'body' => 'Atendimento encerrado.',
                'delivered_at' => $now,
            ]);
            $system->setRelation('conversation', $conversation);

            $this->audit->log('conversation.closed', $conversation, ['by_user_id' => $by?->id]);

            MessageCreated::dispatch($system);
            ConversationUpdated::dispatch($conversation, 'closed');

            return $conversation;
        });
    }
}
