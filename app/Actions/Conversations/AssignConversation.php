<?php

namespace App\Actions\Conversations;

use App\Enums\ConversationStatus;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\ConversationAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atribuição manual (Fase 1). A distribuição automática (Fase 4) deverá
 * reutilizar esta action após escolher o atendente.
 */
class AssignConversation
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Conversation $conversation, User $assignee, ?User $by = null): Conversation
    {
        if (! $assignee->belongsToCompany($conversation->company_id) || ! $assignee->is_active) {
            throw ValidationException::withMessages(['user_id' => 'Atendente inválido para esta empresa.']);
        }

        if ($conversation->isClosed()) {
            throw ValidationException::withMessages(['conversation' => 'Conversa encerrada não pode ser atribuída.']);
        }

        return DB::transaction(function () use ($conversation, $assignee, $by): Conversation {
            if ($conversation->assigned_user_id === $assignee->id) {
                return $conversation;
            }

            $now = now();

            ConversationAssignment::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $now]);

            ConversationAssignment::create([
                'company_id' => $conversation->company_id,
                'conversation_id' => $conversation->id,
                'user_id' => $assignee->id,
                'assigned_by_user_id' => $by?->id,
                'assigned_at' => $now,
            ]);

            $conversation->forceFill([
                'assigned_user_id' => $assignee->id,
                'assigned_at' => $now,
                'status' => ConversationStatus::Open,
            ])->save();

            $this->audit->log('conversation.assigned', $conversation, [
                'assignee_id' => $assignee->id,
                'by_user_id' => $by?->id,
            ]);

            ConversationUpdated::dispatch($conversation->setRelation('assignee', $assignee), 'assigned');

            return $conversation;
        });
    }
}
