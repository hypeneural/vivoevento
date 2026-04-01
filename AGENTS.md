# AGENTS.md — Guia para IA / Vibecoding

## Visão Geral

Este repositório é o monorepo do **Evento Vivo**, uma plataforma de experiências vivas para eventos.

**Stack principal:**
- Backend: Laravel 12 + PHP 8.3 + PostgreSQL + Redis
- Frontend: React 18 + TypeScript + Vite 5 + TailwindCSS 3 + shadcn/ui
- Arquitetura: API-first, monorepo simples, organização por módulos de domínio

---

## Regra Principal

> **Toda feature importante deve nascer dentro de um módulo de domínio.**
> Não criar lógica espalhada fora de módulos sem justificativa clara.

---

## Estrutura do Repositório

- `apps/api` — Backend Laravel (API)
- `apps/web` — Frontend React SPA (Painel administrativo)
- `packages/contracts` — Contratos de API e schemas
- `packages/shared-types` — Tipos compartilhados
- `docs/` — Documentação funcional e arquitetural
- `scripts/` — Scripts de produtividade e CI
- `docker/` — Configurações de containers

---

## Convenções do Backend

### Organização por Módulo

1. Cada domínio relevante fica em `apps/api/app/Modules/<ModuleName>`.
2. Cada módulo segue a estrutura padrão:

```
ModuleName/
├── Actions/          # Ações de negócio (uma classe = uma ação)
├── Data/             # Spatie Data objects
├── DTOs/             # Data Transfer Objects simples
├── Enums/            # Enums do domínio
├── Events/           # Events do Laravel (broadcasting)
├── Exceptions/       # Exceções específicas do módulo
├── Http/
│   ├── Controllers/  # Controllers finos
│   ├── Requests/     # Form Requests para validação
│   └── Resources/    # API Resources para resposta
├── Jobs/             # Jobs assíncronos
├── Listeners/        # Event Listeners
├── Models/           # Eloquent Models
├── Policies/         # Authorization Policies
├── Queries/          # Query objects (listagens complexas)
├── Services/         # Serviços técnicos (download, processamento)
├── Support/          # Classes auxiliares do módulo
├── routes/
│   └── api.php       # Rotas do módulo
├── Providers/
│   └── ModuleNameServiceProvider.php
└── README.md         # Documentação do módulo
```

### Regras de Código

3. **Controllers devem ser finos.** Máximo: receber request, chamar action/service, retornar resource.
4. **Regras de negócio** vão para Actions, Services ou Jobs.
5. **Requests** validam entrada de dados.
6. **Resources** padronizam saída da API.
7. **Policies** controlam autorização.
8. **Jobs** devem ter responsabilidade única.
9. Toda nova rota precisa ter pertencimento claro a um módulo.
10. Toda model importante deve ter migration, factory e testes.

### Shared

Código compartilhado entre módulos fica em `apps/api/app/Shared/`:

```
Shared/
├── Http/          # BaseController, middlewares globais
├── Support/       # Helpers utilitários
├── Contracts/     # Interfaces compartilhadas
├── Concerns/      # Traits de Model (HasOrganization, etc.)
├── Enums/         # Enums globais
├── Exceptions/    # Exception handler base
└── Traits/        # Traits genéricos
```

---

## Convenções do Frontend

### Organização por Módulo

1. Cada feature relevante fica em `apps/web/src/modules/<module-name>/`.
2. Cada módulo exporta uma página principal (`NomePage.tsx`).
3. Componentes internos do módulo ficam no diretório do módulo.
4. Componentes de UI base usam shadcn/ui em `src/components/ui/`.
5. Componentes reutilizáveis ficam em `src/shared/components/`.
6. Types compartilhados ficam em `src/shared/types/`.

### Regras de Código

7. **Páginas devem orquestrar composição**, não conter lógica complexa.
8. **Data fetching** usa TanStack Query (hooks `useQuery`/`useMutation`).
9. **Formulários** usam React Hook Form + Zod para validação.
10. **Estilização** usa TailwindCSS com design tokens via CSS variables.
11. **Rotas** são registradas em `App.tsx`.
12. Todo módulo frontend deve espelhar um módulo backend quando aplicável.

### Nomes no Frontend

| Tipo | Padrão | Exemplo |
|------|--------|--------|
| Página | `NomePage` | `EventsListPage` |
| Componente | `PascalCase` | `StatsCard` |
| Hook | `useNome` | `useMobile` |
| Type/Interface | `PascalCase` | `EventStatus` |
| Utilitário | `camelCase` | `formatDate` |

---

## Convenções de Nomes

### Backend

| Tipo | Padrão | Exemplo |
|------|--------|---------|
| Action | `VerbNounAction` | `CreateEventAction` |
| Job | `VerbNounJob` | `DownloadInboundMediaJob` |
| Service | `NounService` | `MediaVariantGeneratorService` |
| Policy | `NounPolicy` | `EventPolicy` |
| Controller | `NounController` | `EventController` |
| Request | `VerbNounRequest` | `StoreEventRequest` |
| Resource | `NounResource` | `EventResource` |
| Enum | `NounStatus/Type` | `EventStatus` |
| Query | `VerbNounQuery` | `ListEventsQuery` |
| Event (broadcast) | `NounVerbed` | `WallMediaPublished` |
| Listener | `VerbOnNounVerbed` | `BroadcastOnWallMediaPublished` |

### Rotas API

```
GET    /api/v1/{module}           → index
POST   /api/v1/{module}           → store
GET    /api/v1/{module}/{id}      → show
PATCH  /api/v1/{module}/{id}      → update
DELETE /api/v1/{module}/{id}      → destroy
POST   /api/v1/{module}/{id}/verb → ação custom
```

---

## Como Criar uma Nova Feature

### Checklist obrigatório

Antes de criar qualquer feature, responder:

1. 📦 **Em qual módulo backend ela entra?**
2. 🎨 **Precisa de módulo frontend correspondente?**
3. 📊 **Quais entidades ela toca?**
4. 🔒 **Existe alguma policy/permissão impactada?**
5. 📄 **Existe algum fluxo em `docs/flows/` que precisa ser atualizado?**
6. ✅ **Quais testes são necessários?**

### Criar novo módulo backend

```bash
# Usar o gerador (quando disponível)
bash scripts/generators/make-module.sh NomeDoModulo

# Ou manualmente:
# 1. Criar pasta em app/Modules/NomeDoModulo/
# 2. Criar todas as subpastas da estrutura padrão
# 3. Criar ServiceProvider
# 4. Registrar no config/modules.php
# 5. Criar README.md do módulo
# 6. Registrar no docs/modules/module-map.md
```

### Criar novo módulo frontend

```bash
# 1. Criar pasta em apps/web/src/modules/<nome>/
# 2. Criar página principal NomePage.tsx
# 3. Registrar rota em App.tsx
# 4. (Opcional) Criar subpastas components/, hooks/, types/
```

### Criar novo endpoint

```bash
# 1. Criar/atualizar routes/api.php no módulo
# 2. Criar Controller (fino!)
# 3. Criar Request para validação
# 4. Criar Action para lógica
# 5. Criar Resource para resposta
# 6. Criar Policy se necessário
# 7. Escrever testes
```

---

## Filas (Queues)

| Fila | Uso |
|------|-----|
| `webhooks` | Processamento de webhooks recebidos |
| `media-download` | Download de mídia de fontes externas |
| `media-process` | Geração de variantes, watermark, thumb |
| `media-publish` | Publicação de mídia aprovada |
| `notifications` | Envio de notificações e e-mails |
| `analytics` | Aggregação de métricas |
| `billing` | Processamento de cobranças |
| `default` | Fallback geral |

---

## Broadcasting (Realtime)

Canais principais via Laravel Reverb:

| Canal | Tipo | Uso |
|-------|------|-----|
| `event.{id}.gallery` | Private | Atualizações da galeria |
| `event.{id}.wall` | Private | Atualizações do telão |
| `event.{id}.moderation` | Private | Status de moderação |
| `event.{id}.play` | Private | Jogos e ranking |

---

## Fluxos Críticos

> ⚠️ Os fluxos abaixo são sensíveis e devem ser preservados:

1. **Ingestão de mídia via webhook** — InboundMedia → MediaProcessing → Gallery
2. **Processamento assíncrono de mídia** — Download → Variantes → Moderação → Publicação
3. **Moderação** — Pending → Approved/Rejected → Publicação
4. **Atualização realtime de wall** — Publicação → Broadcast → Wall
5. **Billing e mudança de plano** — Subscription lifecycle

---

## Restrições

- ❌ Não criar lógica de negócio dentro de controllers grandes
- ❌ Não duplicar regras entre módulos
- ❌ Não criar helpers globais sem necessidade
- ❌ Não misturar responsabilidades de Gallery, Wall, Play e Hub
- ❌ Não acoplar módulos diretamente (usar contracts/interfaces)
- ✅ Usar sempre Actions para operações de escrita
- ✅ Usar sempre Queries para listagens complexas
- ✅ Usar sempre Resources para respostas da API
- ✅ Usar sempre Requests para validação

---

## Testes

### Backend

Toda feature backend relevante deve ter:

- ✅ Teste de Feature (HTTP) para endpoints
- ✅ Teste de Unit para Actions/Services
- ✅ Teste de Job quando aplicável
- ✅ Factories para todas as Models

```bash
# Rodar todos os testes backend
cd apps/api && php artisan test

# Rodar testes de um módulo específico
cd apps/api && php artisan test --filter=Events

# Rodar com coverage
cd apps/api && php artisan test --coverage
```

### Frontend

```bash
# Testes unitários com Vitest
cd apps/web && npm run test

# Watch mode
cd apps/web && npm run test:watch

# Type checking
cd apps/web && npm run type-check
```

---

## Documentação Obrigatória

Ao criar um novo módulo backend:
- [ ] Criar `README.md` do módulo
- [ ] Registrar no `docs/modules/module-map.md`

Ao criar novo módulo frontend:
- [ ] Registrar rota em `apps/web/src/App.tsx`
- [ ] Atualizar tabela em `apps/web/README.md`

Ao alterar fluxo importante:
- [ ] Atualizar arquivo correspondente em `docs/flows/`

---

## Permissões

Permissões seguem o padrão `module.action`:

```
events.view, events.create, events.update, events.publish, events.archive
channels.view, channels.manage
media.view, media.moderate, media.reprocess, media.delete
gallery.view, gallery.manage
wall.view, wall.manage
play.view, play.manage
hub.view, hub.manage
billing.view, billing.manage
analytics.view
partners.manage
settings.manage
audit.view
```

Roles iniciais:
- `super-admin` — acesso total
- `platform-admin` — admin da plataforma
- `partner-owner` — dono da organização parceira
- `partner-manager` — gerente da organização
- `event-operator` — operador de evento
- `viewer` — apenas visualização
