<?php

use App\Models\Conversation;
use App\Models\User;
use App\Models\Visitor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Broadcast;

/*
| Canais privados do NEXO Chat. Todo canal é privado e autorizado aqui.
| - company.{id}.inbox  : atendentes da empresa (fila / lista de conversas)
| - conversation.{uuid} : atendentes da empresa dona + o visitante dono da conversa
*/

Broadcast::channel('company.{companyId}.inbox', function (User $user, int $companyId) {
    return $user->is_active && $user->belongsToCompany($companyId);
}, ['guards' => ['web']]);

Broadcast::channel('conversation.{uuid}', function (User|Visitor $actor, string $uuid) {
    $tenant = app(TenantContext::class);

    $conversation = $tenant->withoutScope(
        fn () => Conversation::query()->where('uuid', $uuid)->first()
    );

    if (! $conversation) {
        return false;
    }

    if ($actor instanceof Visitor) {
        return $conversation->visitor_id === $actor->id
            && $conversation->company_id === $actor->company_id;
    }

    return $actor->is_active && $actor->belongsToCompany($conversation->company_id);
}, ['guards' => ['web', 'visitor']]);
