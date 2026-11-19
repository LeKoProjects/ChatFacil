<?php

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use Illuminate\Support\Facades\Event;

/*
| Autorização real dos canais privados do Reverb (assinatura Pusher gerada
| localmente; nenhuma conexão de rede é necessária).
*/
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1000',
    ]);
    // força a recriação do broadcaster com a nova configuração
    app()->forgetInstance(Illuminate\Broadcasting\BroadcastManager::class);
    app()->forgetInstance(Illuminate\Contracts\Broadcasting\Factory::class);
    Illuminate\Support\Facades\Broadcast::clearResolvedInstances();
    require base_path('routes/channels.php');

    Event::fake([MessageCreated::class, ConversationUpdated::class]);

    $this->a = tenant('empresa-a.test');
    $this->b = tenant('empresa-b.test');
    $this->tokenA = visitorToken($this->a['widget'], 'empresa-a.test');
    $this->convA = startConversation($this->tokenA)['conversation']['id'];
    $this->tokenB = visitorToken($this->b['widget'], 'empresa-b.test');
    $this->convB = startConversation($this->tokenB)['conversation']['id'];
});

function channelAuth(string $channel): array
{
    return ['channel_name' => 'private-'.$channel, 'socket_id' => '1234.5678'];
}

it('authorizes agents only on their own company channels', function () {
    $agentA = $this->a['agent'];

    $this->actingAs($agentA)->post('/broadcasting/auth', channelAuth('company.'.$this->a['company']->id.'.inbox'))
        ->assertOk()->assertJsonStructure(['auth']);
    $this->actingAs($agentA)->post('/broadcasting/auth', channelAuth('conversation.'.$this->convA))
        ->assertOk();

    $this->actingAs($agentA)->post('/broadcasting/auth', channelAuth('company.'.$this->b['company']->id.'.inbox'))
        ->assertForbidden();
    $this->actingAs($agentA)->post('/broadcasting/auth', channelAuth('conversation.'.$this->convB))
        ->assertForbidden();
});

it('authorizes a visitor only on its own conversation channel', function () {
    $this->withToken($this->tokenA)->postJson('/api/widget/broadcasting/auth', channelAuth('conversation.'.$this->convA))
        ->assertOk()->assertJsonStructure(['auth']);

    $this->withToken($this->tokenA)->postJson('/api/widget/broadcasting/auth', channelAuth('conversation.'.$this->convB))
        ->assertForbidden();

    $this->withToken($this->tokenA)->postJson('/api/widget/broadcasting/auth', channelAuth('company.'.$this->a['company']->id.'.inbox'))
        ->assertForbidden();
});

it('rejects channel auth without credentials', function () {
    $this->postJson('/api/widget/broadcasting/auth', channelAuth('conversation.'.$this->convA))->assertUnauthorized();
    $this->post('/broadcasting/auth', channelAuth('conversation.'.$this->convA))->assertForbidden();
});
