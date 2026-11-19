<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->belongsToCompany($conversation->company_id);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && ! $conversation->isClosed()
            && ($conversation->assigned_user_id === null
                || $conversation->assigned_user_id === $user->id
                || $user->isCompanyAdmin());
    }

    /** Atendente pode assumir; atribuir a outra pessoa exige administrador. */
    public function assign(User $user, Conversation $conversation, ?User $assignee = null): bool
    {
        if (! $this->view($user, $conversation)) {
            return false;
        }

        if ($assignee === null || $assignee->id === $user->id) {
            return true;
        }

        return $user->isCompanyAdmin();
    }

    public function close(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && ($conversation->assigned_user_id === $user->id || $conversation->assigned_user_id === null || $user->isCompanyAdmin());
    }

    public function reopen(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
