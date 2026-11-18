<?php

namespace Tests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Trava de segurança, executada pelo RefreshDatabase ANTES de recriar o
     * banco: nunca rodar migrate:fresh fora de um banco de testes (*_test).
     */
    protected function beforeRefreshingDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new \RuntimeException("Testes abortados: o banco \"{$database}\" não é um banco de testes (*_test).");
        }
    }

    /**
     * Em produção cada requisição tem um container novo. Nos testes o mesmo
     * container é reutilizado, então limpamos o estado por requisição
     * (contexto de empresa e guard padrão alterado por `auth:visitor`).
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $this->app->make(TenantContext::class)->clear();
        $this->app['auth']->shouldUse('web');

        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        $this->app['auth']->shouldUse('web');

        return $response;
    }
}
