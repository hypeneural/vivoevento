# 🎨 Evento Vivo — Frontend (Web)

**Painel administrativo SPA para a plataforma Evento Vivo.**

---

## Stack

| Tecnologia | Versão | Propósito |
|-----------|--------|-----------|
| React | 18.x | UI Library |
| TypeScript | 5.x | Tipagem estática |
| Vite | 5.x | Bundler e dev server |
| TailwindCSS | 3.x | Utility-first CSS |
| shadcn/ui | — | Componentes Radix-based |
| React Router | 6.x | Roteamento SPA |
| TanStack Query | 5.x | Data fetching e cache |
| Framer Motion | 12.x | Animações |
| React Hook Form + Zod | — | Formulários e validação |
| Recharts | 2.x | Gráficos e dashboards |
| Vitest | 3.x | Testes unitários |

---

## Estrutura de Pastas

```
apps/web/
├── public/                  # Assets estáticos (favicon, robots.txt)
├── src/
│   ├── app/                 # Infraestrutura da aplicação
│   │   ├── guards/          # Route guards (autenticação)
│   │   ├── layouts/         # Layouts (AdminLayout, Header, Sidebar)
│   │   └── providers/       # Context providers (Auth, Theme)
│   ├── components/
│   │   ├── ui/              # shadcn/ui components (49 componentes)
│   │   └── NavLink.tsx      # Componente de navegação
│   ├── hooks/               # Custom React hooks
│   ├── lib/                 # Utilitários (cn, utils)
│   ├── modules/             # Módulos de feature (espelham backend)
│   │   ├── analytics/       # Dashboard de métricas
│   │   ├── audit/           # Trilha de auditoria
│   │   ├── auth/            # Login, autenticação
│   │   ├── clients/         # Gestão de clientes
│   │   ├── dashboard/       # Dashboard principal
│   │   ├── events/          # CRUD de eventos
│   │   ├── gallery/         # Galeria ao vivo
│   │   ├── hub/             # Página do evento
│   │   ├── media/           # Mídia recebida
│   │   ├── moderation/      # Moderação de conteúdo
│   │   ├── partners/        # Gestão de parceiros B2B
│   │   ├── plans/           # Planos e assinatura
│   │   ├── play/            # Jogos interativos
│   │   ├── settings/        # Configurações
│   │   └── wall/            # Telão / slideshow
│   ├── pages/               # Páginas genéricas (Index, NotFound)
│   ├── shared/              # Código compartilhado
│   │   ├── components/      # Componentes reutilizáveis (PageHeader, StatsCard, etc.)
│   │   ├── mock/            # Dados mockados para desenvolvimento
│   │   └── types/           # TypeScript types e interfaces
│   ├── test/                # Setup de testes
│   ├── index.css            # Design tokens (CSS variables) + utilidades
│   ├── main.tsx             # Entry point
│   └── App.tsx              # Router e providers
├── .env.example             # Variáveis de ambiente
├── components.json          # Configuração do shadcn/ui
├── index.html               # HTML entry point
├── package.json             # Dependências e scripts
├── tailwind.config.ts       # Configuração do Tailwind
├── tsconfig.json            # TypeScript config
├── vite.config.ts           # Vite config (proxy para API)
└── vitest.config.ts         # Config de testes
```

---

## Como Rodar

```bash
# 1. Instalar dependências
cd apps/web
npm install

# 2. Copiar .env
cp .env.example .env

# 3. Dev server (porta 5173)
npm run dev
```

### Scripts Disponíveis

| Comando | Descrição |
|---------|-----------|
| `npm run dev` | Dev server com HMR |
| `npm run build` | Build de produção |
| `npm run preview` | Preview do build |
| `npm run lint` | Lint com ESLint |
| `npm run lint:fix` | Auto-fix lint |
| `npm run test` | Testes unitários |
| `npm run test:watch` | Testes em watch mode |
| `npm run type-check` | Verificação de tipos |

---

## Design System

### Tema (Dark Mode)

O frontend usa um design system customizado com CSS variables em HSL:

- **Primary**: Purple (`258 70% 58%`) — cor principal da marca
- **Accent**: Blue (`215 80% 55%`) — destaque e links
- **Success/Warning/Destructive**: Feedback semântico
- **Background**: Dark (`230 15% 8%`) — dark mode nativo

### Utilitários CSS

| Classe | Efeito |
|--------|--------|
| `.glass` | Glassmorphism leve |
| `.glass-strong` | Glassmorphism forte |
| `.glow-primary` | Box-shadow glow roxo |
| `.glow-accent` | Box-shadow glow azul |
| `.gradient-primary` | Gradiente primary→accent |
| `.gradient-text` | Texto com gradiente |
| `.card-hover` | Hover animation em cards |
| `.scrollbar-thin` | Scrollbar estilizada |

### Tipografia

- **Sans**: Inter (Google Fonts)
- **Mono**: JetBrains Mono

---

## Integração com API

O Vite proxy redireciona chamadas `/api` para o backend Laravel em `localhost:8000`:

```ts
// vite.config.ts
proxy: {
  "/api": {
    target: "http://localhost:8000",
    changeOrigin: true,
  },
}
```

Variáveis de ambiente com prefixo `VITE_` ficam disponíveis via `import.meta.env`.

---

## Módulos ↔ Backend

| Módulo Frontend | Módulo Backend | Status |
|----------------|---------------|--------|
| `auth` | Auth | 🟡 UI pronta |
| `dashboard` | Analytics/Events | 🟡 UI pronta |
| `events` | Events | 🟡 UI pronta |
| `media` | InboundMedia | 🟡 UI pronta |
| `moderation` | MediaProcessing | 🟡 UI pronta |
| `gallery` | Gallery | 🟡 UI pronta |
| `wall` | Wall | 🟡 UI pronta |
| `play` | Play | 🟡 UI pronta |
| `hub` | Hub | 🟡 UI pronta |
| `partners` | Partners | 🟡 UI pronta |
| `clients` | Organizations/Users | 🟡 UI pronta |
| `plans` | Plans/Billing | 🟡 UI pronta |
| `analytics` | Analytics | 🟡 UI pronta |
| `audit` | Audit | 🟡 UI pronta |
| `settings` | — | 🟡 UI pronta |

> **Status 🟡 UI pronta**: Interface visual implementada com dados mockados. Falta integração com a API real.

---

## Convenções

### Novos Módulos

1. Criar pasta em `src/modules/<nome>/`
2. Cada módulo exporta uma página principal (`NomePage.tsx`)
3. Componentes internos do módulo ficam no próprio diretório
4. Registrar rota em `App.tsx`

### Componentes

- Componentes UI base: `src/components/ui/` (shadcn)
- Componentes reutilizáveis: `src/shared/components/`
- Componentes específicos do módulo: `src/modules/<nome>/components/`

### Tipos

- Types compartilhados: `src/shared/types/`
- Types do módulo: `src/modules/<nome>/types/`
