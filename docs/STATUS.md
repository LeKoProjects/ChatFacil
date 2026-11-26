# NEXO Chat — Status das demandas

> Acompanhamento da [especificação](ESPECIFICACAO.md) (prompt de desenvolvimento).
> **Atualizado em:** 19/11/2026 · **Fase atual:** Fase 1 (MVP) — núcleo funcional entregue e validado.
> Arquitetura em [ARQUITETURA.md](ARQUITETURA.md) · Instalação no [README](../README.md)

Legenda: ✅ feito e verificado · 🟡 parcial · ⬜ não iniciado

---

## 1. Resumo

| Item | Situação |
|---|---|
| Stack obrigatória (Laravel 13, Vue 3, Inertia, Reverb, PostgreSQL, Redis, Docker) | ✅ instalada sem starter kit (instalação padrão + pacotes) |
| Fase 1 — MVP funcional | 🟡 núcleo completo + painel central NEXO; falta o assistente de configuração |
| Fase 2 — Operação profissional | ⬜ |
| Fase 3 — Gestão SaaS | 🟡 painel NEXO iniciado (empresas, suspensão, auditoria, indicadores); faltam planos, assinaturas e limites |
| Fase 4 — Expansão | ⬜ |
| Testes automatizados (backend) | ✅ 55 testes / 462 asserções passando (Pest + PostgreSQL) |
| Desempenho em desenvolvimento | ✅ ~0,14 s por requisição; entrega em tempo real < 0,4 s |
| Checagem de tipos (frontend) | ✅ `vue-tsc` sem erros · build Vite OK |
| Teste ponta a ponta manual | ✅ widget → central → resposta → encerramento, em tempo real (navegador) |

### Versões instaladas

| Pacote | Versão |
|---|---|
| Laravel Framework | 13.42 (PHP 8.4 no Docker) |
| Inertia (Laravel / Vue) | 3.7 / 3.11 |
| Laravel Fortify | 1.42 |
| Laravel Reverb | 1.13 |
| Laravel Sanctum | instalado (reservado para API pública / tokens de integração) |
| Pest | 4.7 |
| Vue / TypeScript / Vite / Tailwind | 3.5 / 6.0 / 8.3 / 4.3 |
| reka-ui (base do shadcn-vue) / lucide-vue-next / laravel-echo | 2.11 / 1.0 / 2.6 |
| PostgreSQL / Redis / Nginx | 16 / 7 / 1.27 |

---

## 2. Critérios de aceitação do MVP (seção 18)

| # | Critério | Status | Evidência |
|---|---|---|---|
| 1 | Inicializar aplicação e dependências | ✅ | `docker compose up` — app, nginx, reverb, queue, postgres, redis |
| 2 | Criar uma empresa | ✅ | Cadastro público cria empresa + admin · `AuthenticationTest` |
| 3 | Cadastrar um site | ✅ | Tela "Sites e widgets" · `CompanyPanelTest` |
| 4 | Criar um atendente | ✅ | Tela "Atendentes" · `CompanyPanelTest` (cria e faz login) |
| 5 | Gerar o script do widget | ✅ | Gerado por widget, com botão copiar · `CompanyPanelTest` |
| 6 | Instalar o widget em página HTML externa | 🟡 | Validado em `/widget/demo` (simula site cliente, **mesmo host** localhost:8080). Falta testar em domínio realmente externo |
| 7 | Abrir a página em um navegador | ✅ | Teste manual no navegador |
| 8 | Iniciar conversa como visitante | ✅ | Teste manual + `WidgetConversationTest` |
| 9 | Acessar painel como atendente em outro navegador | 🟡 | Validado em **outra aba** (sessões independentes: token × cookie). Falta repetir em navegador/perfil separado |
| 10 | Receber a conversa em tempo real | ✅ | Mensagem do visitante apareceu na central sem recarregar (Reverb) |
| 11 | Responder ao visitante | ✅ | Resposta chegou no widget em tempo real + auto-atribuição |
| 12 | Atualizar a página sem perder histórico | ✅ | Recarga do site manteve o histórico · teste automatizado |
| 13 | Encerrar a conversa | ✅ | Encerrada na central; widget mostrou "Atendimento encerrado" · teste |
| 14 | Comprovar isolamento entre duas empresas | ✅ | `TenantIsolationTest` (6 cenários) |
| 15 | Validar autorização dos canais WebSocket | ✅ | `BroadcastChannelAuthorizationTest` (assinatura real Pusher/Reverb) |

---

## 3. O que foi feito

### Infraestrutura
- ✅ Instalação padrão do Laravel 13 (`laravel/laravel`) — **sem starter kit**, pacotes adicionados manualmente.
- ✅ `Dockerfile` multi-stage (PHP 8.4-FPM: pdo_pgsql, redis, intl, zip, pcntl, gd, opcache) — alvos `dev` e `prod`.
- ✅ `compose.yaml`: app, nginx, reverb, queue, vite, postgres (+ banco de testes), redis.
- ✅ Nginx com proxy WebSocket para o Reverb no mesmo domínio e cache curto para `widget.js`.
- ✅ `.env.example` completo (PostgreSQL, Redis, Reverb servidor/cliente, S3).
- ✅ Seeder de demonstração (2 empresas, admins, atendentes, sites e widgets).

### Backend
- ✅ Migrations: companies, users (empresa, papel, disponibilidade, capacidade), websites, website_domains, widgets, visitors, visitor_sessions, conversations, messages, conversation_assignments, audit_logs.
- ✅ Multi-tenancy: `TenantContext` + `BelongsToCompany` (escopo global *fail-closed*, bloqueio de gravação cruzada), middleware antes do route model binding.
- ✅ Autenticação Fortify (login, cadastro empresa+admin, recuperação de senha), bloqueio de usuário inativo / empresa suspensa.
- ✅ Papéis `platform_admin`, `company_admin`, `agent` + Policies (conversa, site, usuário) + middleware `role`.
- ✅ Actions: RegisterCompany, SaveWebsite, StartVisitorSession, StartConversation, SendMessage, AssignConversation, CloseConversation, ReopenConversation.
- ✅ Mensagens: transação + lock, idempotência por `client_message_id`, ordem por sequência, persistência antes do broadcast (`ShouldDispatchAfterCommit`), 1 conversa ativa por visitante (índice único parcial).
- ✅ Widget seguro: domínio validado no servidor, CSP `frame-ancestors`, token de visitante (apenas hash no banco), guard próprio, rate limiting.
- ✅ Eventos Reverb: `message.created`, `conversation.updated`, `typing`; canais privados por conversa e por empresa.
- ✅ Recuperação após desconexão (`GET /api/widget/conversation?after=<seq>`).
- ✅ Logs de auditoria (empresa criada, site, widget, atendente, atribuição, encerramento, reabertura).
- ✅ Dashboard com indicadores reais (recebidas, atendidas, perdidas, 1ª resposta média, duração média, fila, em atendimento, atendentes online, série de 14 dias).

### Frontend (Vue 3 + TS + Inertia + Tailwind 4)
- ✅ **Identidade visual NEXO Chat aplicada** (manual de papelaria, 17/11): azul-marinho `#0E2A63`, azul NEXO `#1677F0`, azul claro `#3BA0FF`; Montserrat (títulos/marca) + Inter (texto), servidas pelo próprio app; símbolo do balão com três pontos (`NexoMark`), logotipo empilhado NEXO/Chat (`NexoLogo`), ondas (`BrandWaves`) e favicon. Aplicada em login/cadastro (painel no estilo da pasta), barra lateral, topo, dashboard, estados vazios e widget (cabeçalho marinho→cor do widget com ondas, botão com o balão, "Atendimento por NEXO Chat"). Cor padrão dos widgets passou a ser o azul NEXO (migration).
- ✅ **Modo claro e escuro**, toasts, estados vazios, modais, confirmações.
- ✅ Componentes no padrão shadcn-vue (Button, Input, Textarea, Label, Card, Badge, Avatar, Dialog, Checkbox) escritos no projeto sobre reka-ui + cva.
- ✅ Telas de login, cadastro, recuperar/redefinir senha.
- ✅ **Central de atendimento** em 3 colunas: filas/filtros/busca · mensagens · dados do visitante; barra superior com disponibilidade (online/ocupado/ausente/offline); envio otimista com reenvio em falha; indicador de digitação; tempo de espera; atribuição (assumir / atribuir a outro — admin); encerrar/reabrir; histórico do visitante; página de origem; indicador de conexão em tempo real; notificação do navegador.
- ✅ Painel: Dashboard, Sites e widgets (cadastro, domínios, personalização do widget, script com botão copiar), Atendentes (criar, editar, desativar, remover).
- ✅ **Widget**: loader `widget.js` (assíncrono, após o `load` do site, iframe isolado, responsivo/tela cheia no celular), botão flutuante, abrir/minimizar, cores/posição configuráveis, boas-vindas, formulário inicial configurável (nome/e-mail/telefone), status online/offline, mensagem offline, tempo real, digitação, histórico persistente, contador de não lidas, som opcional, encerramento + "nova conversa", aviso de reconexão.

### 3.1 Painel central NEXO (`/nexo`, exclusivo de `platform_admin`)
- ✅ **Visão geral** com indicadores reais de todas as empresas: empresas ativas/suspensas/novas, usuários e atendentes online, widgets ativos, conversas hoje/30 dias/ativas agora, mensagens em 30 dias, gráfico de 14 dias, ranking das empresas mais ativas.
- ✅ **Empresas**: lista paginada com busca (nome, CNPJ, e-mail de usuário) e filtro de situação; cadastro de empresa + primeiro administrador; detalhe com usuários, sites/domínios/widgets, estatísticas e auditoria recente; edição de nome e CNPJ/CPF.
- ✅ **Suspender / reativar**, com motivo registrado. Efeitos testados: usuários desconectados e sem login, atendentes ficam offline, widget e novas sessões de visitante bloqueados; ao reativar, tudo volta.
- ✅ **Auditoria da plataforma**: todas as ações administrativas, com filtros por empresa e por ação.
- ✅ Admin NEXO é levado direto a `/nexo` e não acessa as telas de empresa; administradores e atendentes de empresas recebem 403 no `/nexo`. Consultas entre empresas acontecem só nesse painel, de forma explícita (`withoutScope`).
- Login de demonstração: `admin@nexo.local` / `password`.

### 3.2 Produção (AWS — chat.nexoti.com.br)
- ✅ Imagens de produção: `prod` (PHP-FPM com código, dependências sem dev e front-end compilado; OPcache sem checagem de arquivos; caches do Laravel gerados ao iniciar) e `web` (Nginx com arquivos públicos).
- ✅ `compose.prod.yaml`: app, web (só em `127.0.0.1:8090`), reverb, queue, postgres e redis com reinício automático, volumes persistentes e logs rotacionados; Postgres e Redis sem portas expostas.
- ✅ `deploy/`: `setup-env.sh` (gera `.env` com chaves e senhas aleatórias), `deploy.sh` (pull, build, migrations, restart), `backup.sh` (pg_dump diário, 14 dias, cópia opcional para S3) e vhost do Apache com proxy de WebSocket.
- ✅ Usuários iniciais em produção pelo seeder normal (`php artisan db:seed --force`); senha `password`, a trocar no primeiro acesso.
- ✅ Testado localmente em modo produção: login, painel, widget e tempo real pelo proxy (conversa nova chegou ao atendente em 267 ms; resposta ao visitante em 365 ms).
- ⬜ Executar no servidor seguindo [DEPLOY-AWS.md](DEPLOY-AWS.md) (DNS, Docker, Apache, Certbot). A camada Apache/HTTPS só pode ser validada lá.

### 3.3 Chatbot de autoatendimento (sem IA)
- ✅ **Menu em árvore** configurável pela empresa (`/chatbot`): submenus, respostas prontas e "transferir para atendente" com setor; até 4 níveis e 100 opções; reordenar, editar e remover.
- ✅ **Perguntas frequentes**: o que o visitante digita é buscado na FAQ com a busca textual do PostgreSQL (português, sem acentos, por prefixo; pergunta e palavras-chave pesam mais). O bot sugere até 3 perguntas como botões. Tela com "Testar busca".
- ✅ **Fluxo**: com o chatbot ligado, a conversa nova começa com status `bot` (fora da fila). O visitante sai do bot ao clicar "Falar com um atendente", ao escolher uma opção de transferência ou depois de 2 mensagens sem resposta na FAQ. "Isso resolveu" encerra a conversa.
- ✅ **Segurança do fluxo**: só as opções da última mensagem do bot são aceitas; clique repetido (mesmo `client_message_id`) não duplica; o bot para assim que um atendente assume ou responde.
- ✅ **Central**: filtro "Com o bot", aviso quando a conversa está com o bot, setor escolhido e caminho percorrido no painel lateral, notificação "pediu atendimento".
- ✅ Textos editáveis (saudação, não entendeu, transferência com equipe online/offline, resolvido) e prévia. Seeder cria na Empresa Demo um chatbot completo (20 opções de menu de suporte de TI, financeiro e comercial + 8 perguntas frequentes).
- ✅ 12 testes de backend (menu, FAQ, transferência, encerramento, opções inválidas, tomada pelo atendente, filtro, isolamento entre empresas, permissão, seeder de demonstração).
- Custo zero por mensagem: tudo roda no próprio servidor. A estrutura (status `bot`, transferência, caminho) já serve para plugar uma IA depois, como reforço quando a FAQ não encontrar resposta.

### Problemas encontrados e corrigidos durante a validação
- Job de broadcast perdia a relação `conversation` ao ser restaurado na fila sem contexto de empresa → corrigido + teste de regressão.
- `loading="lazy"` impedia o iframe do widget de carregar em alguns casos → removido; montagem após `load` do site.
- PHP-FPM sem permissão de escrita em `storage/` no volume do Windows → pool de desenvolvimento ajustado (`docker/php/fpm-dev.conf`).
- Guard do visitante mantinha usuário em cache entre requisições no mesmo processo → `VisitorGuard` revalida quando o token muda.
- Lentidão (~3,6 s por requisição) → `vendor/` em volume Docker + fila bloqueante (ver seção 6).
- **Incidente em 17/11**: uma execução dos testes via `docker compose run` herdou o `DB_DATABASE` do `.env` e recriou o **banco de desenvolvimento**, que continha apenas dados de demonstração. O banco foi recriado com o seeder. Correções: variáveis de teste forçadas em `phpunit.xml` (`<env>` e `<server>`) e trava em `Tests\TestCase` que aborta a suíte se o banco não terminar em `_test` (verificado).

---

## 4. O que falta — Fase 1

| Item | Status | Observação |
|---|---|---|
| Assistente de configuração para novas empresas (seção 14) | ⬜ | Hoje: cadastro → Sites → Atendentes manualmente |
| Painel central NEXO | ✅ | Ver seção 3.1 |
| Teste do widget em domínio externo real (critério 6) | 🟡 | Ex.: subir uma página em outro host/porta e cadastrar o domínio |
| Teste com atendente em outro navegador/perfil (critério 9) | 🟡 | Validado em outra aba |
| Testes de frontend (Vitest + Vue Test Utils) | 🟡 | Dependências instaladas e configuradas; nenhum teste escrito |
| Testes E2E automatizados (Playwright) | ⬜ | O fluxo E2E foi feito manualmente |
| Contador da fila na central após encerrar/atribuir | 🟡 | Lista atualiza em tempo real; contadores dos filtros podem ficar defasados até a próxima recarga |
| Validação de capacidade do atendente (`max_concurrent_chats`) | ⬜ | Campo existe; ainda não bloqueia a atribuição |
| `php artisan boost:install` (Laravel Boost) | 🟡 | Pacote instalado como dev; instalação interativa não executada |

## 5. Próximas fases (não iniciadas)

**Fase 2 — Operação profissional:** departamentos (+ seleção no widget), transferências, respostas rápidas, notas internas (canal separado do visitante), horários comerciais + atendimento offline/formulário de contato, relatórios com filtros e exportação CSV (PDF preparado), avaliações, upload de imagens/arquivos (S3, validação, acesso autorizado), pesquisa avançada, tags, prioridade, status "pendente" na UI, leitura em tempo real (read receipts).

**Fase 3 — Gestão SaaS:** painel central NEXO completo, planos (Start/Professional/Enterprise) e recursos em banco, assinaturas, limites por empresa (atendentes, sites, widgets), auditoria navegável, indicadores comerciais/operacionais, configurações globais, LGPD (exportação/exclusão de dados pessoais, retenção).

**Fase 4 — Expansão:** webhooks, API pública (Sanctum), integração GLPI / Microsoft 365 / CRM, IA (sugestões, classificação, assistente), automações, white label, distribuição automática (as Actions de atribuição já estão prontas para receber o algoritmo).

---

## 6. Decisões e limitações registradas

- **Sem starter kit** (pedido do usuário): componentes shadcn-vue foram escritos no projeto em vez de gerados pela CLI.
- **Spatie Permission adiado**: papéis fixos + Policies atendem a Fase 1; reavaliar quando houver permissões configuráveis.
- **2FA e passkeys do Fortify desativados** (migrations removidas) — previstos para fase posterior.
- **PHP local é 8.2** (Laravel 13 exige ≥ 8.3): todo o desenvolvimento roda via Docker com PHP 8.4.
- **Desempenho no Windows (resolvido em 17/11)**: as requisições levavam ~3,6 s porque o PHP lia ~11 mil arquivos de `vendor/` pela pasta compartilhada Windows → Docker. `vendor/` agora fica num volume do Docker. Medições após a correção: requisição ~0,14 s; visitante inicia conversa ~0,5 s; envia mensagem ~0,2 s; entrega em tempo real visitante → atendente 80–370 ms e atendente → visitante 120–260 ms. O worker da fila passou a escutar o Redis de forma bloqueante (`REDIS_QUEUE_BLOCK_FOR`), o que eliminou até 1 s de espera por evento. Ao adicionar pacotes PHP, rode `composer` pelo container (o `vendor/` do Windows serve apenas para o editor).
- **Sessões de 120 min**: atendente precisa logar novamente após expirar (configurável em `SESSION_LIFETIME`).
- **Portas locais** escolhidas para não conflitar com outro projeto na máquina: app 8080, Reverb 8081, Postgres 5442, Redis 6380, Vite 5174.
