<p align="center">
  <img src="public/favicon.svg" width="72" alt="NEXO Chat">
</p>

<h1 align="center">NEXO Chat</h1>

<p align="center">
  <strong>Atendimento online para o seu negócio.</strong><br>
  Plataforma SaaS do Grupo NEXO: widget de chat para sites, central de atendimento em tempo real e painéis de gestão para cada empresa e para a NEXO.
</p>

<p align="center">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP 8.4" src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white">
  <img alt="Vue 3" src="https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white">
  <img alt="TypeScript" src="https://img.shields.io/badge/TypeScript-3178C6?logo=typescript&logoColor=white">
  <img alt="Tailwind 4" src="https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white">
  <img alt="PostgreSQL 16" src="https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white">
  <img alt="Redis 7" src="https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white">
  <img alt="Docker" src="https://img.shields.io/badge/Docker-2496ED?logo=docker&logoColor=white">
</p>

![Central de atendimento](docs/screenshots/02-inbox.png)

## O que é

Uma empresa cliente cadastra seus sites, cola uma linha de script e passa a receber conversas dos visitantes numa central de atendimento em tempo real. Cada empresa enxerga só os próprios dados; a NEXO acompanha e administra todas pelo painel central.

| Para quem | O que faz |
|---|---|
| **Visitante do site** | Abre o chat no canto da página, conversa e continua de onde parou mesmo recarregando a página. |
| **Atendente** | Recebe as conversas em tempo real, assume, responde, vê quando o visitante está digitando e encerra. |
| **Administrador da empresa** | Acompanha os indicadores, cadastra sites e domínios autorizados, personaliza o widget e gerencia atendentes. |
| **Administração NEXO** | Vê a plataforma inteira, cadastra, edita, suspende e reativa empresas e consulta a auditoria. |

## Telas

### Widget no site do cliente
![Widget no site do cliente](docs/screenshots/03-widget.png)

### Central de atendimento
Fila, conversas em andamento e finalizadas, dados do visitante e mensagens em tempo real via WebSocket (imagem do topo).

### Painel da empresa

| Dashboard | Sites e widget |
|---|---|
| ![Dashboard da empresa](docs/screenshots/04-dashboard.png) | ![Sites e widget](docs/screenshots/05-sites-widget.png) |

| Atendentes | Login |
|---|---|
| ![Atendentes](docs/screenshots/06-atendentes.png) | ![Login](docs/screenshots/01-login.png) |

**Chatbot de autoatendimento (sem IA):** a empresa monta um menu de botões (submenus, respostas prontas, transferência para um setor) e uma lista de perguntas frequentes. O bot atende antes da equipe e transfere a conversa com o caminho que o visitante percorreu.

### Painel central NEXO

| Visão geral | Empresas |
|---|---|
| ![Visão geral da plataforma](docs/screenshots/07-nexo-visao-geral.png) | ![Empresas](docs/screenshots/08-nexo-empresas.png) |

![Auditoria](docs/screenshots/09-nexo-auditoria.png)

## Destaques técnicos

- **Tempo real:** Laravel Reverb (WebSocket) com canais privados por empresa e por conversa; eventos enviados pela fila no Redis depois do commit.
- **Multiempresa seguro:** escopo global por `company_id` que falha fechado sem contexto e bloqueia gravação em outra empresa; testes de isolamento.
- **Widget isolado:** carregado num iframe, só funciona nos domínios autorizados (validação no servidor e CSP `frame-ancestors`), visitante identificado por token aleatório guardado só como hash.
- **Mensagens confiáveis:** sem duplicadas (id do cliente + índice único), ordem garantida por sequência e recuperação do que foi perdido ao reconectar.
- **Chatbot sem custo por mensagem:** menu em árvore + busca na FAQ com a busca textual do PostgreSQL (português, sem acentos); o atendente assume a qualquer momento.
- **Testes:** 55 testes Pest (fluxo do widget, chatbot, atendimento, isolamento entre empresas, autorização dos canais, painéis) num banco separado que nunca toca o de desenvolvimento.

## Documentação

| Documento | Conteúdo |
|---|---|
| [docs/ARQUITETURA.md](docs/ARQUITETURA.md) | Arquitetura e decisões técnicas |
| [docs/STATUS.md](docs/STATUS.md) | O que já foi feito, o que falta e próximos passos |
| [docs/ESPECIFICACAO.md](docs/ESPECIFICACAO.md) | Especificação completa do produto (fases 1 a 4) |
| [docs/DEPLOY-AWS.md](docs/DEPLOY-AWS.md) | Deploy em produção na AWS (EC2 + Apache + Docker) |

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
| `admin@nexo.local` | Administração NEXO (painel central `/nexo`) |

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

## Produção

Imagem de produção (`Dockerfile`, alvos `prod` e `web`), `compose.prod.yaml` e scripts em `deploy/` (criar `.env`, deploy e backup). Passo a passo completo para AWS EC2 atrás do Apache: [docs/DEPLOY-AWS.md](docs/DEPLOY-AWS.md).

```bash
./deploy/setup-env.sh   # gera o .env com chaves e senhas aleatórias
./deploy/deploy.sh      # build, migrations e sobe tudo em 127.0.0.1:8090
docker compose -f compose.prod.yaml exec app php artisan nexo:admin voce@empresa.com.br
```

## Instalar em sites WordPress

Cadastre o domínio do site no painel (**Sites**) e cole o script do widget no rodapé, pelo plugin **WPCode** (*Code Snippets → Header & Footer → Footer*) ou pelo `functions.php` de um tema filho (`add_action('wp_footer', …)`). Se o site usa plugin de cache (WP Rocket, Autoptimize, LiteSpeed), exclua `widget.js` da opção de combinar/minificar JS e limpe o cache.
