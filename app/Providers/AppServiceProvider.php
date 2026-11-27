<?php

namespace App\Providers;

use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Events\VisitorConversationUpdated;
use App\Events\VisitorMessageCreated;
use App\Support\Tenancy\TenantContext;
use App\Support\Widget\VisitorGuard;
use App\Support\Widget\VisitorTokenResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // "scoped": uma instância por requisição/job (seguro também com Octane e filas).
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Guard do visitante do widget (token de sessão no cabeçalho Authorization).
        Auth::extend('visitor-token', function ($app) {
            $guard = new VisitorGuard(
                fn (Request $request) => $app->make(VisitorTokenResolver::class)($request),
                $app['request'],
            );
            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });

        // O widget recebe eventos próprios, só com o que o visitante pode ver.
        Event::listen(MessageCreated::class, fn (MessageCreated $e) => broadcast(new VisitorMessageCreated($e->message)));
        Event::listen(ConversationUpdated::class, fn (ConversationUpdated $e) => broadcast(new VisitorConversationUpdated($e->conversation, $e->reason)));

        RateLimiter::for('widget-session', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('widget', function (Request $request) {
            $key = $request->bearerToken() ? hash('sha256', $request->bearerToken()) : $request->ip();

            return [
                Limit::perMinute(60)->by('widget:'.$key),
                Limit::perMinute(300)->by('widget-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('agent', fn (Request $request) => Limit::perMinute(240)->by('agent:'.($request->user()?->id ?? $request->ip())));
    }
}
