<?php

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Models\Message;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;

/*
| Regressão: o worker da fila restaura o evento sem contexto de empresa.
| O payload precisa ser montado mesmo assim (bug encontrado no teste E2E).
*/
it('builds broadcast payloads after being restored by a queue worker without tenant context', function () {
    Event::fake([MessageCreated::class, ConversationUpdated::class]);
    ['widget' => $widget, 'company' => $company] = tenant();
    $id = startConversation(visitorToken($widget), 'mensagem enfileirada')['conversation']['id'];

    $message = app(TenantContext::class)->withoutScope(fn () => Message::with('conversation')->firstOrFail());

    // simula a serialização do job + restauração no worker
    $restored = unserialize(serialize(new MessageCreated($message)));
    app(TenantContext::class)->clear();

    $channels = collect($restored->broadcastOn())->map->name->all();
    $payload = $restored->broadcastWith();

    expect($channels)->toBe(["private-company.{$company->id}.inbox"])
        ->and($payload['message']['body'])->toBe('mensagem enfileirada')
        ->and($payload['conversation']['id'])->toBe($id)
        ->and(app(TenantContext::class)->has())->toBeFalse();
});
