# NEXO Chat

Plataforma SaaS de atendimento digital do Grupo NEXO — widget de chat para sites, central de atendimento em tempo real e painel por empresa.

**Stack:** Laravel 13 · PHP 8.4 · Vue 3 + TypeScript · Inertia.js · Tailwind 4 · componentes padrão shadcn-vue · Laravel Reverb · PostgreSQL 16 · Redis 7 · Docker.

Arquitetura e decisões: [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md).

## Requisitos

- Docker + Docker Compose (não é preciso PHP/Node instalados localmente).

## Instalação (desenvolvimento)

```bash
cp .env.example .env
docker network create nexo-net
docker compose build
docker compose run --rm app composer install
docker compose run --rm vite npm install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan reverb:install   # gera REVERB_APP_ID/KEY/SECRET no .env
docker compose up -d postgres redis
docker compose run --rm app php artisan migrate --seed
docker compose run --rm vite npm run build                # ou: docker compose up -d vite (HMR)
docker compose up -d app nginx reverb queue
```

| Serviço | Endereço |
|---|---|
| Aplicação | http://localhost:8080 |
| Reverb (WebSocket) | ws://localhost:8081 |
| PostgreSQL | localhost:5442 |
| Redis | localhost:6380 |
| Vite (HMR, opcional) | http://localhost:5174 |

### Usuários de demonstração (seeder — senha `password`)

| E-mail | Papel |
|---|---|
| `admin@demo.local` | Administrador da "Empresa Demo" |
| `atendente@demo.local` | Atendente da "Empresa Demo" |
| `admin@outra.local` / `atendente@outra.local` | Segunda empresa (teste de isolamento) |
| `admin@nexo.local` | Administração NEXO (painel central ainda não implementado) |

### Testar o widget

1. Abra http://localhost:8080/widget/demo?widget=wgt_demo0000000000000000000000 (página que simula um site cliente; `localhost:8080` já está autorizado no seeder). O seeder usa chaves fixas de demonstração (`wgt_demo…` e `wgt_outra…`), então o link continua válido após recriar o banco.
2. Em outro navegador/aba anônima, entre como `atendente@demo.local` em http://localhost:8080/inbox.

Para instalar em outro site, cadastre o domínio dele no site e cole o script:

```html
<script src="https://SEU_HOST/widget.js" data-widget-id="wgt_..." async></script>
```

> **Windows/macOS:** `vendor/` fica num volume do Docker (muito mais rápido que a pasta compartilhada). Instale/atualize pacotes PHP sempre pelo container: `docker compose run --rm app composer require ...`.

## Testes

Os testes usam o banco `nexo_chat_test` (criado automaticamente pelo container do Postgres) e abortam se apontarem para qualquer banco que não termine em `_test`.

```bash
docker compose run --rm app ./vendor/bin/pest
docker compose run --rm vite npx vue-tsc --noEmit
```

## Produção (resumo)

- `docker build --target prod`, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS no Nginx/balanceador, `SESSION_SECURE_COOKIE=true`.
- `VITE_REVERB_HOST` = domínio público, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https` (Nginx encaminha `/app` para o Reverb).
- Rodar `php artisan migrate --force`, `config:cache`, `route:cache`, worker de fila e Reverb supervisionados.
- `FILESYSTEM_DISK=s3` quando houver anexos (Fase 2).
