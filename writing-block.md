# PROMPT — DESENVOLVIMENTO DO NEXO CHAT
## Plataforma SaaS de atendimento digital do Grupo NEXO

# 1. PAPEL E RESPONSABILIDADE

Atue como Arquiteto de Software Sênior, Engenheiro Full Stack, Especialista em Laravel, Vue.js, SaaS, UX/UI, segurança da informação e sistemas de comunicação em tempo real.

Sua missão é projetar e desenvolver uma plataforma completa chamada **NEXO Chat**, pertencente ao Grupo NEXO.

O objetivo não é criar apenas um protótipo, dashboard estático ou demonstração visual. Quero desenvolver um produto comercial real, funcional, seguro, escalável e preparado para implantação em produção.

Utilize como referências funcionais JivoChat, Chatwoot, Crisp, tawk.to e Zendesk.

Não copie interfaces proprietárias, códigos ou identidades visuais dessas plataformas. Utilize-as apenas como referências de funcionalidades e experiência do usuário.

**Regra principal:** toda funcionalidade apresentada como concluída deve possuir implementação real, persistência e validação.

# 2. VISÃO DO PRODUTO

**Nome:** NEXO Chat  
**Proprietário:** Grupo NEXO  
**Categoria:** Plataforma SaaS de atendimento digital.

O NEXO Chat será uma plataforma que permitirá a empresas instalarem um widget de atendimento em seus próprios sites.

Visitantes poderão iniciar conversas diretamente pelo navegador, sem precisar instalar aplicativos ou utilizar WhatsApp.

Os atendentes receberão e responderão às mensagens através de uma central de atendimento web.

O sistema deverá suportar múltiplas empresas, mantendo isolamento completo entre clientes, usuários, conversas, configurações e dados.

## Modelo comercial

A plataforma deverá permitir:

- Utilização interna pelo Grupo NEXO.
- Cadastro de empresas clientes.
- Planos de assinatura mensais e anuais.
- Limitação de atendentes por plano.
- Controle de recursos disponíveis.
- Personalização individual dos widgets.
- Gestão centralizada pela NEXO.
- Possibilidade futura de white label.
- Escalabilidade para centenas ou milhares de empresas.

# 3. STACK TECNOLÓGICA OBRIGATÓRIA

Utilize Laravel 13 e Vue.js 3 como tecnologias centrais do projeto.

Não substitua essas tecnologias por Node.js, NestJS, Next.js ou outras alternativas sem autorização.

## Backend

- PHP 8.4 ou versão compatível com Laravel 13.
- Laravel 13.
- Laravel Eloquent ORM.
- Laravel Migrations.
- Laravel Form Requests.
- Laravel Policies e Gates.
- Laravel Events e Listeners.
- Laravel Jobs e Queues.
- Laravel Notifications.
- Laravel API Resources.
- Laravel Sanctum quando necessário para autenticação de APIs.
- Laravel Fortify para os recursos de autenticação, se aplicável.

Organize o backend utilizando arquitetura modular, separação de responsabilidades e princípios SOLID.

Evite controllers gigantes e regras de negócio diretamente nas rotas.

Utilize Services, Actions, Events, Jobs e Policies quando agregarem valor real à organização.

## Frontend

- Vue.js 3.
- TypeScript.
- Composition API.
- Inertia.js.
- Tailwind CSS.
- shadcn-vue para componentes de interface.
- Lucide Icons.
- Vite.

O frontend deverá utilizar componentes reutilizáveis, layouts compartilhados, composables e organização por funcionalidades.

Utilize tipagem adequada no TypeScript e evite o uso excessivo de `any`.

## Comunicação em tempo real

- Laravel Reverb.
- Laravel Echo.
- WebSockets.
- Redis para suporte a filas e distribuição de eventos quando necessário.

A comunicação deverá funcionar efetivamente entre visitante e atendente.

Não utilize intervalos de consulta HTTP como substituto permanente de WebSockets.

## Banco de dados

- PostgreSQL.
- Laravel Eloquent.
- Migrations versionadas.
- Relacionamentos e índices adequados.
- Transações para operações críticas.

## Infraestrutura

- Docker.
- Docker Compose.
- Nginx.
- Redis.
- AWS S3 ou armazenamento compatível.
- HTTPS.
- Configurações por variáveis de ambiente.

## Bibliotecas adicionais

Avalie a utilização de:

- Spatie Laravel Permission: gerenciamento de permissões.
- Laravel Horizon: monitoramento de filas Redis.
- Laravel Telescope: diagnóstico em desenvolvimento.
- Pest PHP ou PHPUnit: testes automatizados.

Instale somente bibliotecas necessárias e compatíveis com Laravel 13.

Antes de utilizar qualquer pacote, verifique compatibilidade, manutenção e documentação.

# 4. ARQUITETURA DA APLICAÇÃO

Utilize uma aplicação Laravel integrada ao Vue.js através do Inertia.js.

Não crie microsserviços desnecessários para o MVP.

A arquitetura inicial deverá seguir o modelo de monólito modular, permitindo evolução futura sem comprometer a manutenção.

Organize o projeto por domínios funcionais:

- Authentication.
- Companies.
- Users.
- Departments.
- Conversations.
- Messages.
- Visitors.
- Widgets.
- Websites.
- Subscriptions.
- Plans.
- Reports.
- Notifications.
- Integrations.
- Administration.

Cada módulo deverá manter responsabilidades bem definidas.

## Estrutura sugerida

```text
nexo-chat/
├── app/
│   ├── Actions/
│   ├── Events/
│   ├── Listeners/
│   ├── Jobs/
│   ├── Models/
│   ├── Policies/
│   ├── Services/
│   ├── Enums/
│   └── Http/
│       ├── Controllers/
│       ├── Middleware/
│       ├── Requests/
│       └── Resources/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── js/
│   │   ├── components/
│   │   ├── composables/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── types/
│   │   └── app.ts
│   ├── css/
│   └── views/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── docker/
├── public/
├── storage/
├── .env.example
├── Dockerfile
├── compose.yaml
├── composer.json
└── package.json
```

A estrutura poderá ser aprimorada conforme a evolução do projeto.

# 5. ESTRUTURA FUNCIONAL

Desenvolva quatro ambientes principais.

## A. Widget de atendimento

Widget instalado nos sites das empresas.

Funcionalidades:

1. Botão flutuante.
2. Abrir e fechar atendimento.
3. Identidade visual configurável.
4. Mensagem de boas-vindas.
5. Formulário inicial configurável.
6. Nome, e-mail e telefone do visitante.
7. Seleção de departamento.
8. Envio e recebimento de mensagens em tempo real.
9. Indicador de digitação.
10. Status online e offline.
11. Histórico persistente.
12. Indicador de mensagens não lidas.
13. Notificações sonoras opcionais.
14. Envio de imagens e arquivos.
15. Avaliação do atendimento.
16. Mensagem fora do horário comercial.
17. Formulário para contato offline.
18. Interface responsiva.
19. Identificação segura da sessão.
20. Funcionamento com widget minimizado.

O widget deverá ser leve e não prejudicar o carregamento do site hospedeiro.

Utilize iframe isolado ou arquitetura equivalente, evitando conflitos com CSS e JavaScript externos.

## B. Central de atendimento

Desenvolva uma interface profissional para operadores.

Layout:

- Coluna esquerda: filas e conversas.
- Área central: mensagens.
- Coluna direita: informações do visitante.
- Barra superior: perfil, notificações e disponibilidade.

Funcionalidades:

- Login individual.
- Status online, ocupado e ausente.
- Recebimento de conversas em tempo real.
- Atendimento simultâneo.
- Atribuição manual.
- Transferência de atendimentos.
- Departamentos.
- Fila de espera.
- Respostas rápidas.
- Notas internas.
- Tags.
- Pesquisa de conversas.
- Histórico de visitantes.
- Priorização.
- Encerramento e reabertura.
- Tempo de espera.
- Notificações.
- Página de origem.
- Avaliações.
- Controle de permissões.

Status:

- Aguardando atendimento.
- Em atendimento.
- Pendente.
- Finalizada.

Todos os estados devem ser persistidos no PostgreSQL.

## C. Painel administrativo da empresa

Cada empresa deverá possuir seu próprio ambiente administrativo.

Funcionalidades:

- Dashboard.
- Gestão de atendentes.
- Gestão de departamentos.
- Cadastro de sites.
- Configuração do widget.
- Horários comerciais.
- Respostas rápidas.
- Relatórios.
- Indicadores.
- Gestão de permissões.
- Configurações da empresa.
- Histórico de conversas.

O administrador de uma empresa não poderá acessar dados de outras organizações.

## D. Painel central NEXO

Ambiente exclusivo da administração do Grupo NEXO.

Funcionalidades:

- Cadastro e gestão de empresas.
- Ativação e suspensão de clientes.
- Gestão de planos.
- Gestão de assinaturas.
- Controle de limites.
- Controle de recursos.
- Gerenciamento de sites autorizados.
- Indicadores comerciais.
- Indicadores operacionais.
- Auditoria.
- Configurações globais.
- Monitoramento da plataforma.

A administração central deverá ser independente dos painéis dos clientes.

# 6. INSTALAÇÃO DO WIDGET

A instalação deverá ocorrer através de um pequeno script JavaScript.

Exemplo:

```html
<script
    src="https://chat.nexo.com.br/widget.js"
    data-widget-id="ID_DO_WIDGET"
    async>
</script>
```

O sistema deverá gerar automaticamente o código de instalação de cada widget.

Requisitos:

- Compatibilidade com WordPress.
- Compatibilidade com HTML puro.
- Compatibilidade com React.
- Compatibilidade com Vue.
- Compatibilidade com outros frameworks.
- Carregamento assíncrono.
- Comunicação HTTPS.
- WebSockets.
- Domínios autorizados.
- Isolamento visual.
- Múltiplos widgets por empresa conforme plano.

Não exponha tokens administrativos ou credenciais privilegiadas no código público.

Implemente validação de domínio no servidor. Considere que a identificação pública do widget não representa autenticação ou autorização suficiente.

# 7. COMUNICAÇÃO EM TEMPO REAL

Utilize Laravel Reverb e Laravel Echo.

Implemente:

- Envio de mensagens.
- Recebimento instantâneo.
- Reconexão automática.
- Persistência antes da confirmação de envio.
- Ordenação cronológica.
- Prevenção de mensagens duplicadas.
- Indicadores de digitação.
- Entrega e leitura quando aplicável.
- Atualização das filas.
- Atualização dos status.
- Isolamento dos canais por empresa.
- Controle de autorização dos canais privados.
- Recuperação do histórico após desconexão.

A gravação da mensagem no banco deverá ser independente da disponibilidade momentânea do WebSocket.

Utilize identificadores únicos e mecanismos de idempotência para evitar duplicações.

# 8. DEPARTAMENTOS E DISTRIBUIÇÃO

Permita departamentos configuráveis:

- Comercial.
- Suporte técnico.
- Financeiro.
- Administrativo.
- Outros.

Cada departamento deverá possuir atendentes associados.

Implemente inicialmente distribuição manual.

Prepare a arquitetura para distribuição automática considerando:

- Disponibilidade.
- Quantidade de atendimentos ativos.
- Ordem de entrada.
- Capacidade do operador.
- Departamento.

# 9. DASHBOARD E RELATÓRIOS

Crie dashboards com indicadores reais.

Indicadores:

- Conversas recebidas.
- Conversas atendidas.
- Conversas perdidas.
- Tempo médio de primeira resposta.
- Tempo médio de atendimento.
- Tempo em fila.
- Atendimento por operador.
- Satisfação.
- Horários de maior demanda.
- Conversas simultâneas.

Filtros:

- Período.
- Operador.
- Departamento.
- Status.

Permita exportação CSV e prepare geração de PDF.

Não utilize números estáticos para representar indicadores de produção.

# 10. IDENTIDADE VISUAL E UX/UI

Desenvolva identidade visual premium, moderna e corporativa.

Referências:

- Chatwoot.
- Crisp.
- Linear.
- Intercom.
- Zendesk.

A interface deverá transmitir tecnologia, organização, segurança e confiabilidade.

Utilize:

- Tailwind CSS.
- shadcn-vue.
- Lucide Icons.
- Tipografia consistente.
- Hierarquia visual.
- Espaçamentos padronizados.
- Componentes reutilizáveis.
- Feedback visual.
- Skeleton loading.
- Estados vazios.
- Modais.
- Toasts.
- Confirmações.

Implemente modo claro e escuro.

Não reproduza simplesmente dashboards genéricos de administração.

Priorize experiência operacional: o atendente deve conseguir visualizar, identificar e responder conversas com o mínimo de navegação possível.

Permita personalização da identidade visual dos widgets por empresa.

# 11. MULTI-TENANCY E SEGURANÇA

O isolamento entre empresas é requisito crítico.

Utilize uma estratégia de multi-tenancy com `company_id` nas entidades pertencentes às empresas.

Implemente:

- Middleware de identificação da empresa.
- Policies.
- Gates.
- Scopes de consulta.
- Validação de propriedade dos registros.
- Controle de permissões.
- Proteção de rotas.
- Autorização de canais Reverb.
- Rate limiting.
- Proteção contra XSS.
- Proteção contra CSRF quando aplicável.
- Prevenção de SQL Injection.
- Validação de uploads.
- Proteção contra acesso indevido a arquivos.
- Hash seguro de senhas.
- Gestão de sessões.
- Logs de auditoria.

Não confie apenas em filtros enviados pelo frontend para garantir isolamento.

Toda consulta e operação deverá respeitar o contexto da empresa autenticada.

Crie testes que comprovem que uma empresa não consegue acessar conversas, usuários ou arquivos de outra.

Considere LGPD desde a modelagem:

- Consentimento quando aplicável.
- Retenção de dados.
- Exportação de dados pessoais.
- Exclusão mediante autorização.
- Registro de ações administrativas.
- Proteção de informações pessoais.

# 12. PLANOS E ASSINATURAS

Crie uma estrutura comercial configurável.

Planos iniciais:

- Start.
- Professional.
- Enterprise.

Os limites deverão ser configuráveis pelo administrador NEXO.

Considere:

- Quantidade de atendentes.
- Quantidade de sites.
- Histórico.
- Departamentos.
- Relatórios.
- API.
- Webhooks.
- Integrações.
- Personalização.
- Recursos avançados.

Implemente gestão de planos e assinaturas.

A cobrança automática poderá ser desenvolvida posteriormente.

Não vincule regras comerciais diretamente ao código. Utilize configurações persistidas no banco.

# 13. MODELAGEM DO BANCO DE DADOS

Modele as seguintes entidades:

- Companies.
- Users.
- Roles.
- Permissions.
- Departments.
- Websites.
- Widgets.
- Visitors.
- Visitor Sessions.
- Conversations.
- Messages.
- Attachments.
- Conversation Assignments.
- Conversation Transfers.
- Tags.
- Conversation Tags.
- Ratings.
- Business Hours.
- Quick Replies.
- Plans.
- Subscriptions.
- Plan Features.
- Settings.
- Audit Logs.
- Integration Tokens.

Defina:

- Relacionamentos.
- Chaves estrangeiras.
- Índices.
- Restrições.
- Exclusão lógica quando necessária.
- Campos de auditoria.
- Estratégia de isolamento multiempresa.

Utilize migrations Laravel versionadas.

Não concentre todas as informações em tabelas genéricas ou estruturas JSON sem necessidade.

# 14. CONFIGURAÇÃO INICIAL

Implemente um assistente de configuração para novas empresas.

Etapas:

1. Cadastro da empresa.
2. Cadastro do primeiro site.
3. Personalização do widget.
4. Configuração da mensagem inicial.
5. Convite de atendentes.
6. Cadastro de departamentos.
7. Configuração dos horários.
8. Geração do script.
9. Teste de atendimento real.

O processo deverá possuir validações e permitir continuidade posterior.

# 15. INTEGRAÇÕES FUTURAS

Prepare a arquitetura para:

**GLPI**
- Abrir chamados.
- Vincular tickets.
- Consultar status.
- Identificar solicitantes.

**Microsoft 365**
- Identidade corporativa.
- Notificações.
- Integração com Teams.

**CRM**
- Cadastro de leads.
- Cadastro de contatos.
- Sincronização de informações.

**Webhooks**
- Conversa iniciada.
- Mensagem recebida.
- Conversa atribuída.
- Conversa transferida.
- Conversa encerrada.

**Inteligência artificial**
- Sugestões de respostas.
- Classificação de mensagens.
- Assistente virtual.
- Automação.
- Transferência para atendente humano.

Essas integrações não deverão ser dependências do MVP.

# 16. DESENVOLVIMENTO POR FASES

Não tente desenvolver todas as funcionalidades simultaneamente.

Priorize entregas pequenas, funcionais e testáveis.

## FASE 1 — MVP FUNCIONAL

Prioridade absoluta.

- Configuração do Laravel 13.
- Configuração do Vue.js 3.
- Inertia.js.
- Tailwind CSS.
- PostgreSQL.
- Redis.
- Laravel Reverb.
- Estrutura multiempresa.
- Autenticação.
- Cadastro de empresas.
- Cadastro de sites.
- Cadastro de atendentes.
- Criação do widget.
- Script de instalação.
- Comunicação visitante/atendente.
- Histórico persistente.
- Fila de atendimento.
- Atribuição manual.
- Encerramento de conversas.
- Painel básico da empresa.

## FASE 2 — OPERAÇÃO PROFISSIONAL

- Departamentos.
- Transferências.
- Respostas rápidas.
- Notas internas.
- Horários comerciais.
- Atendimento offline.
- Relatórios.
- Indicadores.
- Avaliações.
- Upload de arquivos.
- Pesquisa avançada.

## FASE 3 — GESTÃO SAAS

- Painel central NEXO.
- Planos.
- Assinaturas.
- Limites por empresa.
- Auditoria.
- Gestão comercial.
- Gestão de recursos.

## FASE 4 — EXPANSÃO

- Webhooks.
- Integração GLPI.
- API pública.
- IA.
- Automações.
- White label.
- Distribuição automática de conversas.

# 17. TESTES E QUALIDADE

Utilize Pest PHP ou PHPUnit no backend.

Implemente testes para:

- Autenticação.
- Cadastro.
- Permissões.
- Multi-tenancy.
- Persistência.
- Envio de mensagens.
- Recebimento de mensagens.
- Atribuição.
- Encerramento.
- Uploads.
- Autorização WebSocket.

No frontend, utilize ferramentas de testes compatíveis com Vue 3, como Vitest e Vue Test Utils.

Avalie testes E2E com Playwright.

Não considere uma funcionalidade concluída apenas porque a tela está renderizando.

# 18. CRITÉRIOS DE ACEITAÇÃO DO MVP

O MVP somente será considerado funcional quando for possível:

1. Inicializar aplicação e dependências.
2. Criar uma empresa.
3. Cadastrar um site.
4. Criar um atendente.
5. Gerar o script do widget.
6. Instalar o widget em uma página HTML externa.
7. Abrir a página em um navegador.
8. Iniciar conversa como visitante.
9. Acessar painel como atendente em outro navegador.
10. Receber a conversa em tempo real.
11. Responder ao visitante.
12. Atualizar a página sem perder histórico.
13. Encerrar a conversa.
14. Comprovar isolamento entre duas empresas.
15. Validar autorização dos canais WebSocket.

Todos os critérios devem ser testados.

# 19. REGRAS DE DESENVOLVIMENTO

Siga rigorosamente:

1. Desenvolva código real, não pseudocódigo.
2. Não entregue apenas mockups.
3. Não simule comunicação em tempo real.
4. Não utilize dados estáticos para representar operações reais.
5. Não deixe botões sem funcionalidade.
6. Não crie páginas sem integração com backend quando a funcionalidade exigir persistência.
7. Não duplique regras de negócio.
8. Não misture responsabilidades entre frontend e backend.
9. Utilize migrations para alterações no banco.
10. Mantenha compatibilidade entre dependências.
11. Documente decisões arquiteturais importantes.
12. Crie `.env.example`.
13. Forneça comandos de instalação.
14. Implemente testes.
15. Valide cada etapa antes de avançar.

Ao encontrar limitações no ambiente, informe claramente.

Não declare implementação concluída sem evidências de execução ou testes.

# 20. FORMA DE EXECUÇÃO

Comece apresentando:

1. Arquitetura escolhida.
2. Justificativa da stack Laravel 13 + Vue.js 3.
3. Estrutura de diretórios.
4. Modelagem inicial do banco.
5. Fluxo de autenticação.
6. Fluxo completo de comunicação visitante-atendente.
7. Estratégia de multi-tenancy.
8. Estratégia de comunicação com Laravel Reverb.

Depois, inicie imediatamente a implementação da FASE 1.

Desenvolva em etapas pequenas, mantendo o projeto executável ao final de cada etapa.

Não pare após apresentar o planejamento.

# OBJETIVO FINAL

Construir o **NEXO Chat**, um produto SaaS de atendimento digital do Grupo NEXO, desenvolvido com Laravel 13 e Vue.js 3.

A plataforma deverá oferecer uma experiência profissional, comunicação em tempo real, isolamento multiempresa, segurança, estabilidade e capacidade de comercialização.

**Comece agora pela FASE 1.**

Primeiro estabeleça a estrutura real do projeto, banco de dados, autenticação, multi-tenancy e fluxo funcional de mensagens entre visitante e atendente.

O resultado esperado é uma aplicação executável, não apenas uma proposta de arquitetura.