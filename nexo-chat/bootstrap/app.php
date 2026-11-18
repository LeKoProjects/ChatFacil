<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\SetVisitorTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'tenant' => SetTenantContext::class,
            'role' => EnsureRole::class,
            'visitor.tenant' => SetVisitorTenantContext::class,
        ]);

        // O contexto da empresa precisa existir antes do route model binding,
        // pois os modelos são resolvidos já com o escopo de empresa aplicado.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetTenantContext::class,
        );

        // Usuário já logado que abre /login ou /register vai para a central
        // (o padrão do Laravel seria a rota "dashboard", restrita a administradores).
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isPlatformAdmin() ? '/nexo' : '/inbox');

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
