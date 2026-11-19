# NEXO Chat — Arquitetura

Documento das decisões arquiteturais da Fase 1. Mantido junto ao código.

## 1. Arquitetura escolhida

**Monólito modular** Laravel 13 + Vue 3 via Inertia.js, sem microsserviços.

```
 Site do cliente                         Infra NEXO Chat (Docker Compose)
┌────────────────────┐   HTTPS   ┌──────────────────────────────────────────────┐
│ <script widget.js> │──────────▶│ Nginx ─┬─▶ PHP-FPM (Laravel 13)              │
│   └ iframe isolado │◀─────WS───│        └─▶ Reverb (WebSocket) /app /apps     │
└────────────────────┘           │ Queue worker (Redis) ──▶ broadcast p/ Reverb │
 Central (navegador) ──HTTPS/WS─▶│ PostgreSQL 16 · Redis 7                       │
                                 └──────────────────────────────────────────────┘
```

| Serviço (compose) | Papel |
|---|---|
| `app` | PHP 8.4-FPM com Laravel |
| `nginx` | HTTP + proxy WebSocket para o Reverb |
| `reverb` | Servidor WebSocket (Laravel Reverb) |
| `queue` | Worker Redis — entrega dos eventos de broadcast |
| `postgres` | Banco principal (+ `nexo_chat_test` para testes) |
| `redis` | Filas, cache e sessões |
| `vite` | Dev server do frontend (opcional) |

## 2. Por que Laravel 13 + Vue 3

- **Laravel 13**: Eloquent, migrations, policies, filas, broadcasting e Reverb nativos — tudo o que um chat multiempresa precisa, sem integrar peças avulsas.
- **Reverb**: WebSocket first-party, compatível com o protocolo Pusher, escala horizontalmente via Redis.
- **Vue 3 + Inertia**: SPA sem API REST duplicada para o painel; o backend continua sendo a fonte da verdade (rotas, autorização, validação).
- **Widget separado** (Vue sem Tailwind, ~41 KB + pusher-js) para não pesar no site do cliente.

## 3. Estrutura de diretórios (principal)

```
app/
  Actions/        Companies/RegisterCompany · Conversations/{Start,SendMessage,Assign,Close,Reopen}
                  Websites/SaveWebsite · Widget/StartVisitorSession · Fortify/*
  Enums/          UserRole · AgentAvailability · ConversationStatus · MessageSender · CompanyStatus
  Events/         MessageCreated · ConversationUpdated · TypingChanged
  Http/
    Controllers/  Agent/ (central) · Company/ (painel) · Widget/ (iframe + API do visitante)
    Middleware/   SetTenantContext · SetVisitorTenantContext · EnsureRole · HandleInertiaRequests
    Requests/     validação (Form Requests)
    Resources/    MessageResource · ConversationResource
  Models/         Company, User, Website, WebsiteDomain, Widget, Visitor, VisitorSession,
                  Conversation, Message, ConversationAssignment, AuditLog
    Concerns/BelongsToCompany.php   ← escopo multiempresa
  Policies/       ConversationPolicy · WebsitePolicy · UserPolicy
  Services/       AuditLogger
  Support/
    Tenancy/      TenantContext · CompanyScope
    Widget/       DomainAuthorizer · VisitorGuard · VisitorTokenResolver · WidgetPresenter
resources/js/
  pages/          auth/* · inbox/Index · dashboard/Index · websites/Index · agents/Index
  layouts/        AppLayout · AuthLayout
  components/ui/  componentes no padrão shadcn-vue (cva + reka-ui)
  composables/    useInboxRealtime · useTheme
  widget/         app do iframe do widget (main.ts, App.vue, api.ts)
public/widget.js  loader instalado nos sites clientes
routes/           web.php (painel) · api.php (widget) · channels.php (autorização Reverb)
docker/           nginx, php, postgres
```

## 4. Modelagem (Fase 1)

| Tabela | Observações |
|---|---|
| `companies` | tenant; `status` (active/suspended), soft delete |
| `users` | `company_id` (null = admin NEXO), `role`, `availability`, `max_concurrent_chats`, soft delete |
| `websites`, `website_domains` | domínios autorizados (único por site) |
| `widgets` | `public_key` (`wgt_` + ULID) — identificador público, **não** credencial; aparência e formulário |
| `visitors` | uuid público, dados de contato, `consented_at` (LGPD) |
| `visitor_sessions` | apenas **hash SHA-256** do token; expiração 30 dias |
| `conversations` | uuid público, status, responsável, contadores de não lidas, tempos (1ª resposta, atribuição, encerramento). **Índice único parcial**: 1 conversa ativa por visitante |
| `messages` | `client_message_id` com **único (conversation_id, client_message_id)** → idempotência |
| `conversation_assignments` | histórico de atribuições |
| `audit_logs` | ações administrativas (jsonb `metadata`) |

Todas as tabelas de cliente têm `company_id` com FK e índices compostos começando por `company_id`.
Entidades das fases 2–3 (departamentos, tags, avaliações, planos, assinaturas…) ainda **não** foram criadas.

## 5. Autenticação

- **Atendentes/admins**: Laravel Fortify (sessão + CSRF), telas Vue via Inertia. Cadastro público cria **empresa + administrador** (`RegisterCompany`, transação). Login bloqueado para usuário inativo e empresa suspensa (`Fortify::authenticateUsing`); quem já está logado é desconectado pelo `SetTenantContext`. Rate limit de login 5/min.
- **Visitantes**: sem conta. `POST /api/widget/{key}/sessions` (host precisa ser autorizado) devolve um token aleatório de 64 caracteres; o navegador guarda no `localStorage` do iframe e envia como `Authorization: Bearer`. Guard próprio `visitor` (`VisitorGuard` + `VisitorTokenResolver`).
- Papéis (Fase 1): `platform_admin`, `company_admin`, `agent` — coluna `role` + policies. Spatie Permission foi avaliado e **adiado** até existir necessidade de permissões granulares.

## 6. Fluxo visitante ↔ atendente

1. Site carrega `widget.js` (após o `load` da página) → cria iframe `/widget/frame/{key}?host=…`.
2. Servidor valida o host e responde com `Content-Security-Policy: frame-ancestors <domínios autorizados>`.
3. Widget abre sessão → recebe token + histórico (conversa ativa/última).
4. Visitante envia formulário → `StartConversation` (transação) cria/reutiliza conversa e grava a 1ª mensagem.
5. `SendMessage`: transação + `lockForUpdate` na conversa → verifica idempotência → grava → atualiza contadores → **após o commit** despacha `MessageCreated` (fila Redis).
6. Worker entrega no Reverb: canais `private-conversation.{uuid}` (visitante + atendentes) e `private-company.{id}.inbox` (filas da central).
7. Central atualiza a lista/conversa em tempo real; resposta do atendente segue o mesmo caminho (e atribui a conversa automaticamente se estava sem responsável).
8. Desconexão: ao reconectar, o widget busca `GET /conversation?after=<seq>` e a central recarrega as props — nada se perde porque a fonte da verdade é o PostgreSQL.

## 7. Multi-tenancy

- `TenantContext` (escopo por requisição/job) com a empresa atual, definido por `SetTenantContext` (usuário logado — roda **antes** do route model binding) ou `SetVisitorTenantContext` (visitante).
- Trait `BelongsToCompany`: escopo global `company_id = contexto`; **sem contexto ⇒ nenhum registro** (fail closed); preenche `company_id` na criação e **lança exceção** se tentar gravar para outra empresa.
- Route model binding já é filtrado: recurso de outra empresa ⇒ 404. Policies verificam empresa/papel.
- Desvio do escopo apenas explícito: `TenantContext::withoutScope()` (resolução de token, autorização de canal, jobs).
- Testes provam o isolamento (`tests/Feature/TenantIsolationTest.php`, `BroadcastChannelAuthorizationTest.php`).

## 8. Reverb

- Todos os canais são **privados** e autorizados em `routes/channels.php`; agentes via `/broadcasting/auth` (sessão), visitantes via `/api/widget/broadcasting/auth` (token).
- Eventos persistentes (`MessageCreated`, `ConversationUpdated`) são enfileirados com `ShouldDispatchAfterCommit`; digitação (`TypingChanged`) é efêmera (`ShouldBroadcastNow`, `toOthers()`).
- O cliente envia `X-Socket-ID` para não receber o próprio evento; deduplica por `client_message_id`/uuid e ordena pela sequência do servidor.
- Em produção, Nginx encaminha `/app` e `/apps` para o Reverb no mesmo domínio (TLS no Nginx). Para múltiplas instâncias do Reverb, habilitar `REVERB_SCALING_ENABLED=true` (Redis).
