# Event universal QR editor execution plan - 2026-04-11

## Objetivo

Este documento transforma a analise do editor universal de QR code em um plano de execucao implementavel, detalhado e orientado por TDD.

Ele cobre:

- a baseline real validada no repo antes da execucao;
- as decisoes tecnicas que ja ficam travadas para evitar drift;
- as fases de entrega em ordem de ROI;
- tarefas e subtarefas backend/frontend;
- testes obrigatorios antes de cada passo;
- criterios de aceite e riscos por fase;
- o que fica em V1, V1.5 e V2.

Documento base:

- `docs/architecture/event-universal-qr-editor-analysis-2026-04-11.md`

---

## Status da execucao inicial em 2026-04-11

### Fase 0

- [x] instalar e travar `qr-code-styling@1.9.2` no `apps/web`
- [x] validar import real da lib no setup atual
- [x] validar `append`, `update` e `getRawData('svg')` em spike com `jsdom`
- [x] criar wrapper local minimo `qrCodeStylingDriver.ts`
- [x] rodar `npm run test -- src/modules/qr-code/support`
- [x] rodar `npm run type-check`

### Blindagem inicial do schema e adapter

- [x] criar `qrTypes.ts`
- [x] criar `qrDefaults.ts`
- [x] criar `qrSchemaMigrator.ts`
- [x] criar `qrSchemaNormalizer.ts`
- [x] criar `qrGuardrails.ts`
- [x] criar `qrOptionsBuilder.ts`
- [x] cobrir schema/adapter/wrapper com testes puros
- [ ] integrar essa camada ao editor lazy-load
- [ ] conectar persistencia backend

### Resultado automatizado desta rodada

Comandos executados:

```bash
cd apps/web
npm run test -- src/modules/qr-code/support
npm run type-check
```

Resultado:

- `6` arquivos de teste passaram;
- `12` testes passaram;
- `type-check` passou.

---

## Resultado dos testes executados antes do plano

## Frontend

Comando:

```bash
cd apps/web
npm run type-check
```

Resultado:

- `type-check` passou.

Comando:

```bash
cd apps/web
npm run test -- src/modules/events/EventDetailPage.test.tsx src/modules/events/branding.test.ts src/modules/events/intake.test.ts src/modules/events/components/TelegramOperationalStatusCard.test.tsx
```

Resultado:

- `4` arquivos passaram;
- `12` testes passaram.

Leitura pratica:

- a trilha do detalhe do evento, branding e contrato basico da pagina esta verde;
- isso valida a superficie mais proxima do QR editor.

Comando:

```bash
cd apps/web
npm run test -- src/modules/events
```

Resultado:

- `17` arquivos passaram;
- `2` arquivos falharam;
- `73` testes passaram;
- `2` testes falharam.

Falhas residuais confirmadas:

- `src/modules/events/journey/__tests__/JourneyFlowCanvas.test.tsx`
  - espera `padding: 0.2`;
  - runtime atual usa `padding: 0.1`.
- `src/modules/events/journey/__tests__/buildJourneyGraph.test.ts`
  - ainda existe overlap entre dois nodes da etapa `decision`.

Leitura pratica:

- as falhas ficam registradas como baseline residual no subdominio `journey/*`;
- elas nao bloqueiam o QR editor;
- elas nao devem ser misturadas com a trilha de validacao do QR.

## Backend

Comando:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventDetailAndLinksTest.php tests/Feature/Events/CreateEventTest.php
```

Resultado:

- `18` testes passaram;
- `169` assertions passaram.

Comando:

```bash
cd apps/api
php artisan test tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Events/EventBrandingUploadTest.php
```

Resultado:

- `5` testes passaram;
- `42` assertions passaram.

Leitura pratica:

- `Events` esta verde na trilha de detalhe, links publicos, criacao do agregado e branding herdado;
- isso valida a base tecnica para:
  - conteudo encoded vindo de `EventPublicLinksService`;
  - cascata de branding `organizacao -> evento`.

---

## Fontes oficiais revalidadas antes da execucao

- `qr-code-styling`:
  - demo oficial: `https://qr-code-styling.com/`
  - repo/README oficial: `https://github.com/kozakdenys/qr-code-styling`
  - latest release exibida no repo: `v1.9.2`, em `April 11, 2025`
  - site oficial ainda exibe `npm v1.8.3`
- React:
  - `lazy`: `https://react.dev/reference/react/lazy`
  - `Suspense`: `https://react.dev/reference/react/Suspense`
  - `useDeferredValue`: `https://react.dev/reference/react/useDeferredValue`
  - `useTransition`: `https://react.dev/reference/react/useTransition`
  - `React Performance tracks`: `https://react.dev/reference/dev-tools/react-performance-tracks`
  - blog `React 19.2`: `https://react.dev/blog/2025/10/01/react-19-2`
- TanStack Query:
  - prefetching: `https://tanstack.com/query/latest/docs/framework/react/guides/prefetching`
  - important defaults: `https://tanstack.com/query/v5/docs/framework/react/guides/important-defaults`
  - optimistic updates: `https://tanstack.com/query/v5/docs/framework/react/guides/optimistic-updates`
- React Hook Form:
  - site oficial: `https://react-hook-form.com/`
- QR spec / leitura:
  - quiet zone / code area: `https://www.qrcode.com/en/howto/code.html`
  - error correction: `https://www.qrcode.com/en/about/error_correction.html`
  - contrast minimum: `https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum`
  - non-text contrast: `https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html`
- Web platform:
  - Barcode Detection API: `https://developer.mozilla.org/en-US/docs/Web/API/Barcode_Detection_API`
  - OffscreenCanvas: `https://developer.mozilla.org/en-US/docs/Web/API/OffscreenCanvas`
- WAI APG:
  - modal dialog pattern: `https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/`

---

## Decisoes tecnicas fixadas antes da implementacao

Estas decisoes ja ficam travadas para evitar rediscussao durante a execucao:

1. O produto salva um schema semantico proprio e nunca persiste o objeto bruto de `QRCodeStylingOptions`.
2. O schema salvo e versionado por `config_version`, com normalizacao e migracao no read/write path.
3. A dependencia `qr-code-styling` fica travada em versao especifica e encapsulada por um wrapper local minimo.
4. O conteudo encoded do QR nao e editavel livremente na V1 e continua vindo de `EventPublicLinksService`.
5. O editor e carregado sob demanda com `React.lazy` + `Suspense`.
6. O trigger do QR faz prefetch do chunk e da query em `onMouseEnter` e `onFocus`.
7. O formulario usa `React Hook Form` com subscriptions finas por `useWatch`, sem `watch()` global do editor.
8. O preview usa uma unica instancia de `QRCodeStyling`, com `append()` uma vez e `update()` depois.
9. O preview padrao e barato, pequeno e em `svg`; o export grande e sob demanda.
10. O save usa `useMutation` com update otimista e rollback.
11. A personalizacao usa cascata `organizacao -> evento -> preset de uso do link -> override local`.
12. O fluxo do editor comeca por `preset de uso`, depois `skin`, depois `logo` e so por ultimo `Avancado`.
13. A heuristica de leitura e obrigatoria na V1; decode real com `BarcodeDetector` e melhoria progressiva.
14. O score de contraste usa WCAG como piso para UI e um criterio interno mais conservador para o simbolo do QR.
15. O trigger do QR e `button` real e o dialog segue o padrao APG de foco preso, `Escape` e foco de retorno.
16. No mobile o editor e tratado como tarefa em `Drawer` alto/quase full-screen, nao como popup pequeno.
17. `React Performance tracks` nao entram como dependencia da V1 porque o repo atual ainda esta em `react 18.3.1`.
18. QR de Pix e QR temporario de WhatsApp ficam fora do escopo desta entrega.
19. `applyExtension` fica restrito a extensoes SVG curadas pelo produto, nao a edicao livre do usuario na V1.

---

## Ordem de ROI consolidada

Depois da revalidacao, a ordem de ROI para implementacao ficou:

1. schema semantico + adapter;
2. lazy-load + prefetch;
3. formulario com `useWatch` + preview diferido;
4. presets por cenario de uso;
5. decode real opcional.

## Pacote minimo obrigatorio da implementacao

Se o escopo precisar ser protegido contra inflacao, o pacote que nao deve cair e:

- schema semantico versionado + adapter;
- normalizacao e migracao por `config_version`;
- wrapper local minimo da dependencia;
- lazy-load + prefetch de chunk/query;
- preview SVG barato com instancia unica;
- `useWatch` + `useDeferredValue` + `useTransition` nos pontos certos;
- guardrails fortes de leitura;
- presets por cenario de uso;
- save otimista;
- acessibilidade do trigger e do modal;
- metricas basicas de performance.

---

## Mapa de ownership

## Backend

Ownership no modulo `Events`.

Arquivos/pastas esperados:

```text
apps/api/app/Modules/Events/
  Actions/
  Data/
  DTOs/
  Http/Controllers/
  Http/Requests/
  Http/Resources/
  Jobs/
  Models/
  routes/api.php
```

## Frontend

Ownership dividido entre engine reutilizavel e adaptador de dominio:

```text
apps/web/src/modules/qr-code/
apps/web/src/modules/events/qr/
```

---

## Fase 0 - Baseline e spike de dependencia

## Objetivo

Validar a dependencia `qr-code-styling` no Vite atual e blindar a baseline de testes usada como referencia da execucao.

## Tarefas

### T0.1 - Fixar a baseline automatizada

Subtarefas:

- registrar na execucao que `type-check` do web esta verde;
- registrar que a bateria focada em `EventDetailPage`, branding e intake esta verde;
- registrar as `2` falhas residuais em `journey/*` como baseline externa ao QR editor.

### T0.2 - Fazer o spike tecnico da lib

Subtarefas:

- instalar `qr-code-styling` em branch de trabalho;
- travar a versao escolhida no `package.json` com justificativa documentada;
- validar import no Vite;
- validar render simples em `svg`;
- validar `append`, `update`, `download`, `getRawData`;
- validar logo remota com `crossOrigin: "anonymous"`;
- validar se a versao escolhida sera `1.9.2` ou outra travada por motivo concreto;
- criar um wrapper local minimo para o spike, em vez de importar a lib em varios pontos.

### T0.3 - Confirmar precedentes internos de performance

Subtarefas:

- registrar que o repo ja usa `lazy` e `Suspense`;
- registrar que o repo ja usa `prefetchQuery`;
- registrar que o repo ja usa `useDeferredValue`, `useTransition` e `useWatch` em outras superficies.

## TDD obrigatorio da fase

Antes de subir qualquer UI do editor:

- criar um teste de caracterizacao para a escolha da versao e import da lib, se o time optar por um wrapper;
- adicionar um teste puro de smoke do adapter/wrapper local do QR, mesmo que ainda minimo;
- registrar o resultado do spike no documento base para evitar reabrir a discussao de versao depois.

## Criterios de aceite

- a versao da lib fica travada com justificativa documentada;
- o spike confirma render e export no browser;
- a baseline de testes do plano fica registrada.

---

## Fase 1 - Schema semantico do produto e adapter para a lib

## Objetivo

Separar definitivamente o contrato de produto do contrato do `qr-code-styling`.

## Tarefas

### T1.1 - Definir o schema semantico do produto

Subtarefas:

- definir `config_version`;
- definir enums de `link_key`, `usage_preset`, `skin_preset`, `export extension`, `readability status`;
- definir blocos semanticos:
  - `render`
  - `style`
  - `logo`
  - `advanced`
  - `export_defaults`
- definir defaults e estrategia formal de migracao futura;
- definir regras de normalizacao para payload parcial ou legado.

### T1.2 - Criar a camada pura de suporte no frontend

Arquivos sugeridos:

```text
apps/web/src/modules/qr-code/support/
  qrTypes.ts
  qrDefaults.ts
  qrPresets.ts
  qrGuardrails.ts
  qrOptionsBuilder.ts
  qrSchemaNormalizer.ts
  qrSchemaMigrator.ts
```

Subtarefas:

- modelar tipos TS do schema semantico;
- modelar defaults por preset e por tipo de link;
- criar `qrSchemaNormalizer` para preencher defaults e remover chaves invalidas;
- criar `qrSchemaMigrator` para evoluir `config_version`;
- criar `qrOptionsBuilder` para gerar `QRCodeStylingOptions`;
- criar `qrGuardrails` para clamp e autocorrecao de regras.

### T1.3 - Criar espelho de schema no backend

Arquivos sugeridos:

```text
apps/api/app/Modules/Events/Data/
  EventPublicLinkQrConfigData.php
  EventPublicLinkQrDefaultsData.php
  EventPublicLinkQrSchemaNormalizer.php
```

Subtarefas:

- modelar enums/constantes de versao no backend;
- garantir coerencia de nomes com o frontend;
- preparar normalizacao de payload vindo do request;
- definir como o backend reage a `config_version` antiga:
  - migrar em memoria;
  - devolver payload normalizado;
  - persistir na versao mais nova no proximo save.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `qrDefaults.test.ts`
  - valida defaults minimos por tipo de link;
- `qrSchemaNormalizer.test.ts`
  - valida preenchimento de defaults;
  - valida remocao de chaves invalidas;
- `qrSchemaMigrator.test.ts`
  - valida migracao de versao antiga para atual;
  - garante idempotencia ao migrar payload ja atual;
- `qrPresets.test.ts`
  - valida presets de uso e skins;
- `qrGuardrails.test.ts`
  - trava:
    - `margin_modules >= 4`
    - `imageSize <= 0.5`
    - `logo => errorCorrectionLevel >= H` ou regra equivalente da V1
- `qrOptionsBuilder.test.ts`
  - valida mapeamento do schema semantico para:
    - `dotsOptions`
    - `cornersSquareOptions`
    - `cornersDotOptions`
    - `backgroundOptions`
    - `imageOptions`
    - `qrOptions`
  - valida que `data` vem de fora do schema salvo.

### Backend - escrever primeiro

- `tests/Unit/Events/EventPublicLinkQrConfigDataTest.php`
  - valida defaults;
  - valida coercao de payload;
  - valida versao do schema.
- `tests/Unit/Events/EventPublicLinkQrSchemaNormalizerTest.php`
  - valida normalizacao de payload legado;
  - valida compatibilidade entre versoes.

## Criterios de aceite

- nenhum ponto do produto depende de salvar o objeto bruto da lib;
- existe adapter explicito;
- existe normalizador e migrador de schema;
- regras de guardrail mais criticas ficam blindadas por teste puro.

---

## Fase 2 - Editor lazy-load, trigger acessivel e prefetch

## Objetivo

Entregar a casca de interacao do editor sem ainda depender de persistencia completa.

## Tarefas

### T2.1 - Transformar o QR atual em trigger acessivel

Subtarefas:

- evoluir `PublicLinkCard` para que o bloco do QR seja clicavel;
- manter `Copiar` e `Abrir` como acoes separadas;
- adicionar texto de apoio `Clique para editar e baixar`;
- devolver foco ao trigger ao fechar editor;
- garantir `aria-label` claro por `linkKey`.

### T2.2 - Criar o carregamento sob demanda

Arquivos sugeridos:

```text
apps/web/src/modules/events/qr/
  EventPublicLinkQrTrigger.tsx
  EventPublicLinkQrEditor.tsx

apps/web/src/modules/qr-code/components/
  QrCodeEditorDialog.tsx
  QrCodeEditorDrawer.tsx
```

Subtarefas:

- usar `React.lazy` para o editor;
- usar `Suspense` com fallback pequeno;
- manter desktop em `Dialog` e mobile em `Drawer`;
- no mobile usar `Drawer` alto/quase full-screen com CTA fixa.

### T2.3 - Fazer prefetch do chunk e da query

Subtarefas:

- criar helper para preload do editor;
- disparar preload no `onMouseEnter` e `onFocus`;
- disparar `queryClient.prefetchQuery(...)` para a config daquele `linkKey`;
- proteger contra prefetch duplicado em cascata.

### T2.4 - Fechar acessibilidade do modal

Subtarefas:

- garantir foco inicial dentro do editor;
- garantir `Tab` e `Shift + Tab` presos dentro do dialog;
- garantir `Escape` para fechar;
- garantir foco de retorno ao trigger;
- manter botao de fechar visivel e sem modal dentro de modal.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `EventPublicLinkQrTrigger.test.tsx`
  - verifica que o QR vira `button`;
  - verifica `aria-label`;
  - verifica prefetch em `hover` e `focus`.
- `QrCodeEditorShell.test.tsx`
  - desktop usa `Dialog`;
  - mobile usa `Drawer`;
  - fallback aparece enquanto chunk lazy carrega.
- `QrCodeEditorAccessibility.test.tsx`
  - foco entra no dialog ao abrir;
  - `Escape` fecha;
  - foco volta ao trigger ao fechar.
- teste de caracterizacao ou unitario para helper de preload
  - garante que prefetch do chunk e da query acontece no maximo uma vez por janela curta.

## Criterios de aceite

- o editor nao entra no bundle inicial da pagina;
- o trigger e acessivel;
- o dialog respeita o padrao APG de foco e fechamento;
- a abertura do editor reutiliza o padrao de preload ja adotado no repo.

---

## Fase 3 - Preview ao vivo performatico

## Objetivo

Montar o preview em tempo real sem re-render desnecessario do formulario.

## Tarefas

### T3.1 - Criar o preview engine

Arquivos sugeridos:

```text
apps/web/src/modules/qr-code/components/QrCodePreviewPane.tsx
apps/web/src/modules/qr-code/hooks/useQrCodePreview.ts
```

Subtarefas:

- instanciar `QRCodeStyling` uma unica vez;
- chamar `append()` uma unica vez;
- chamar `update()` quando o draft mudar;
- usar preview pequeno em `svg`.

### T3.2 - Quebrar o formulario por subscriptions finas

Arquivos sugeridos:

```text
apps/web/src/modules/qr-code/components/
  QrCodeContentPanel.tsx
  QrCodeStylePanel.tsx
  QrCodeLogoPanel.tsx
  QrCodeExportPanel.tsx
  QrCodeAdvancedPanel.tsx
```

Subtarefas:

- usar `FormProvider`;
- usar `useWatch` apenas em campos que dirigem UI;
- evitar `watch()` global;
- usar `mode: 'onBlur'` e `reValidateMode: 'onChange'`.

### T3.3 - Desacoplar input e preview pesado

Subtarefas:

- usar `useDeferredValue` no objeto derivado de preview;
- usar `startTransition` apenas para updates nao urgentes:
  - trocar preset;
  - trocar aba;
  - aplicar reset;
- manter input controlado sincrono.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `useQrCodePreview.test.ts`
  - instancia `QRCodeStyling` uma vez;
  - chama `append` uma vez;
  - chama `update` nas mudancas;
  - nao recria instancia em cada ajuste.
- `QrCodeEditorForm.performance-characterization.test.tsx`
  - valida que paineis usam `useWatch` por campo e nao `watch()` global, se o time optar por teste de arquitetura;
  - ou valida isolamento de re-render com contadores simples por painel.
- `QrCodePreviewPane.test.tsx`
  - preview responde ao draft diferido;
  - export nao dispara no mero ajuste de slider.

## Criterios de aceite

- preview fluido;
- sem recriacao de instancia por mudanca simples;
- formulario nao rerenderiza em cascata inteira a cada input.

---

## Fase 4 - Persistencia backend do schema semantico

## Objetivo

Salvar e reabrir o editor no estado exato do link, sem misturar conteudo encoded com visual.

## Tarefas

### T4.1 - Criar storage dedicado

Arquivos sugeridos:

```text
apps/api/database/migrations/
  xxxx_xx_xx_xxxxxx_create_event_public_link_qr_configs_table.php

apps/api/app/Modules/Events/Models/
  EventPublicLinkQrConfig.php
```

Subtarefas:

- criar tabela `event_public_link_qr_configs`;
- unique por `event_id + link_key`;
- colunas de schema, assets e auditoria.

### T4.2 - Criar actions, requests e resources

Arquivos sugeridos:

```text
apps/api/app/Modules/Events/Actions/
  GetEventPublicLinkQrConfigAction.php
  UpsertEventPublicLinkQrConfigAction.php
  ResetEventPublicLinkQrConfigAction.php

apps/api/app/Modules/Events/Http/Controllers/
  EventPublicLinkQrController.php

apps/api/app/Modules/Events/Http/Requests/
  UpsertEventPublicLinkQrConfigRequest.php

apps/api/app/Modules/Events/Http/Resources/
  EventPublicLinkQrResource.php
```

Subtarefas:

- ler default quando nao houver config salva;
- juntar `qr_value` atual do link;
- resolver defaults a partir de branding efetivo;
- validar schema recebido;
- salvar config por link.

### T4.3 - Registrar rotas

Rotas:

```text
GET    /api/v1/events/{event}/qr-codes
GET    /api/v1/events/{event}/qr-codes/{linkKey}
PUT    /api/v1/events/{event}/qr-codes/{linkKey}
POST   /api/v1/events/{event}/qr-codes/{linkKey}/reset
```

O endpoint `/render` fica opcional e deve ser adiado ate a fase server-side.

## TDD obrigatorio da fase

### Backend - escrever primeiro

- `tests/Feature/Events/EventPublicLinkQrConfigTest.php`
  - `GET` sem config salva devolve default derivado;
  - `PUT` salva config por `event_id + link_key`;
  - `POST reset` remove override e volta ao default;
  - slug alterado continua atualizando `qr_value` sem perder visual;
  - acesso fora da organizacao e proibido.
- `tests/Unit/Events/GetEventPublicLinkQrConfigActionTest.php`
  - garante que `data` encoded vem do link atual, nao do JSON salvo.
- `tests/Unit/Events/UpsertEventPublicLinkQrConfigActionTest.php`
  - garante normalizacao do schema salvo.

## Criterios de aceite

- o editor reabre exatamente no estado salvo;
- o link encoded acompanha slug/wall code atual;
- visual e conteudo continuam desacoplados.

---

## Fase 5 - Integracao na pagina de evento e save otimista

## Objetivo

Acoplar o editor ao `EventDetailPage` e fazer a experiencia de salvar parecer instantanea.

## Tarefas

### T5.1 - Integrar trigger + card + editor

Subtarefas:

- ligar `PublicLinkCard` ao editor;
- expor editor para `gallery`, `upload`, `wall`, `hub`, `play`, `find_me`;
- manter fallback para links ainda inativos.

### T5.2 - Adotar save otimista

Subtarefas:

- usar `useMutation`;
- aplicar `onMutate` com snapshot anterior;
- atualizar cache local do link imediatamente;
- rollback em erro;
- invalidar no `onSettled`.

### T5.3 - Refletir configuracao salva no proprio card

Subtarefas:

- o card passa a renderizar preview salvo/default;
- exibir indicador de preset ou modo atual;
- manter CTA de copiar e abrir.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `PublicLinkCard.test.tsx`
  - clicar no QR abre editor;
  - botoes `Copiar` e `Abrir` continuam funcionando.
- `EventPublicLinkQrEditor.integration.test.tsx`
  - save atualiza preview do card otimisticamente;
  - erro de save faz rollback.
- `EventDetailPage.qr.integration.test.tsx`
  - garante que o editor abre a partir do contexto correto do link.

## Criterios de aceite

- o usuario percebe o save no card sem esperar roundtrip completo;
- rollback funciona;
- a pagina de evento continua estavel.

---

## Fase 6 - Presets inteligentes e cascata de personalizacao

## Objetivo

Transformar personalizacao em configuracao guiada por contexto de uso, nao apenas por estetica.

## Tarefas

### T6.1 - Implementar cascata de defaults

Ordem:

1. template da organizacao;
2. branding efetivo do evento;
3. preset de uso do link;
4. override local salvo.

Subtarefas:

- criar resolver de cascata no frontend;
- criar espelho minimo no backend para defaults retornados;
- decidir onde o template da organizacao fica nesta V1:
  - inline derivado do branding atual;
  - ou futura config institucional.

### T6.2 - Implementar presets por cenario

Subtarefas:

- `Telao`
- `Upload rapido`
- `Galeria premium`
- `Impresso pequeno`
- `Convite / WhatsApp`

### T6.3 - Implementar fluxo guiado de personalizacao

Subtarefas:

- abrir o editor sempre em `preset de uso`;
- mostrar `skin visual` depois do preset;
- manter `Avancado` recolhido por padrao;
- ter um modo rapido com foco em:
  - preset;
  - cor principal;
  - fundo;
  - logo;
  - baixar.

### T6.4 - Implementar explicabilidade da cascata

Subtarefas:

- devolver metadado de origem por campo principal;
- exibir badges como:
  - `Veio do evento`
  - `Veio do preset`
  - `Personalizado aqui`
- limitar a explicabilidade V1 aos campos de maior impacto:
  - cor principal;
  - fundo;
  - logo;
  - preset de uso;
  - exportacao default.

### T6.5 - Implementar thumbnails, microcopy e produtividade

Subtarefas:

- mostrar presets como cards com miniatura;
- adicionar microcopy curta por cenario;
- adicionar `Usar logo do evento` como CTA principal;
- adicionar `Restaurar esta secao`.

### T6.6 - Implementar copy style

Subtarefas:

- copiar visual de um link para outro do mesmo evento;
- nunca copiar `data` encoded;
- manter confirmacao clara do destino.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `qrPresetCascade.test.ts`
  - valida a ordem da cascata;
  - valida override local vencendo preset;
  - valida branding do evento vencendo template organizacional nos campos editados.
- `qrCascadeExplanation.test.ts`
  - valida origem correta de campos principais;
  - valida mudanca de badge quando houver override local.
- `qrPresetChooser.test.tsx`
  - garante que `preset de uso` aparece antes de knobs avancados;
  - garante thumbnail e microcopy por card.
- `qrCopyStyle.test.ts`
  - copia apenas visual;
  - preserva `qr_value` especifico do link de destino.
- `qrSectionReset.test.ts`
  - reseta apenas a secao alvo sem destruir overrides de outras secoes.

### Backend - escrever primeiro

- `tests/Feature/Events/EventPublicLinkQrDefaultsTest.php`
  - confirma defaults derivados de branding efetivo;
  - confirma que ausencia de template organizacional nao quebra o default.

## Criterios de aceite

- o usuario escolhe "o que quer fazer" antes de cair em knobs visuais;
- presets produzem QR robusto por contexto;
- a origem dos campos principais fica explicavel;
- copy style nao mistura conteudo entre links.

---

## Fase 7 - Validacao de leitura em duas camadas

## Objetivo

Entregar seguranca de leitura sem transformar a V1 numa dependencia de APIs experimentais.

## Tarefas

### T7.1 - Implementar heuristica obrigatoria

Subtarefas:

- calcular status `great | good | risky`;
- calcular score de contraste com base objetiva;
- avaliar:
  - contraste;
  - quiet zone;
  - tamanho da logo;
  - ECC;
  - gradiente;
  - transparencia;
  - shape decorativo.

Regra recomendada:

- usar WCAG como piso para cromia do editor e indicadores de UI;
- usar criterio interno mais conservador para dots vs fundo do QR;
- penalizar combinacoes com linhas finas, gradiente suave e transparencia.

### T7.2 - Implementar bloqueios e autocorrecao

Subtarefas:

- clamp de quiet zone;
- clamp de `imageSize`;
- auto-upgrade de ECC;
- bloqueio de export em combinacoes extremas;
- aviso visivel quando o QR depender do fundo externo por transparencia.

### T7.3 - Implementar decode real opcional

Subtarefas:

- feature detect de `BarcodeDetector`;
- gerar raster temporario do preview;
- tentar decode antes de save/download, sem travar a feature quando a API nao existir;
- avaliar `OffscreenCanvas` apenas se a operacao pesar.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `useQrCodeReadability.test.ts`
  - classifica casos seguros, bons e arriscados.
- `qrContrastScore.test.ts`
  - valida piso objetivo de contraste;
  - valida penalizacao por decoracao excessiva.
- `qrGuardrails.test.ts`
  - cobre clamps e auto-upgrades.
- `qrDecodeProbe.test.ts`
  - quando `BarcodeDetector` nao existe, fluxo volta para heuristica;
  - quando existe e falha decode, sistema avisa e nao quebra.

## Criterios de aceite

- heuristica sempre disponivel;
- decode real nunca vira dependencia hard da V1;
- o usuario nao consegue salvar combinacao obviamente insegura sem aviso forte.

---

## Fase 8 - Observabilidade e profiling da V1

## Objetivo

Medir a experiencia do editor antes de abrir fases de sofisticacao adicional.

## Tarefas

### T8.1 - Instrumentar metricas minimas

Metricas:

- `qr_editor_open_to_preview_ms`
- `qr_editor_preview_update_ms`
- `qr_editor_save_ms`

### T8.2 - Criar helpers de medicao

Subtarefas:

- helper com `performance.mark()` e `performance.measure()`;
- logs em dev;
- opcionalmente envio para trilha de telemetria interna.

### T8.3 - Instrumentar telemetria de UX

Subtarefas:

- medir selecao de preset de uso;
- medir abertura do avancado;
- medir uso de `Duplicar estilo`;
- medir uso de `Restaurar esta secao`;
- medir exibicao de alertas de leitura.

### T8.4 - Documentar trilha de profiling

Subtarefas:

- documentar como reproduzir medicao local;
- documentar que `React Performance tracks` fica como proxima etapa apos upgrade/trilha dedicada de React 19+.

## TDD obrigatorio da fase

### Frontend - escrever primeiro

- `qrPerformanceMarks.test.ts`
  - helper marca abertura, preview e save;
  - helper nao explode em ambiente sem `performance.measure`.
- `qrUxTelemetry.test.ts`
  - garante emissao dos eventos principais sem travar a UI.

## Criterios de aceite

- a V1 nasce mensuravel;
- o time consegue comparar antes/depois;
- profiling futuro nao depende de memoria oral.

---

## Fase 9 - Assets server-side e reuse cross-surface

## Objetivo

Adicionar renderer oficial do servidor apenas depois de a V1 no browser estar provada.

## Tarefas

### T9.1 - Criar renderer Node

Sugestao:

```text
scripts/qr/render-event-public-link.mjs
```

Subtarefas:

- usar `jsdom` para `svg`;
- usar `node-canvas` para raster;
- passar ambos quando `saveAsBlob` for usado;
- gerar `svg` e `png`.

### T9.2 - Criar job assicrono

Arquivos sugeridos:

```text
apps/api/app/Modules/Events/Jobs/RenderEventPublicLinkQrAssetsJob.php
apps/api/app/Modules/Events/Actions/RenderEventPublicLinkQrAssetsAction.php
```

### T9.3 - Persistir assets e expor URLs

Subtarefas:

- salvar caminhos gerados;
- devolver `svg_url` e `png_url` nas respostas;
- permitir reuso em outras telas.

### T9.4 - Avaliar extensoes SVG curadas

Subtarefas:

- usar `applyExtension` apenas em molduras ou badges prontas;
- vincular extensoes a presets ou templates;
- manter a edicao livre de SVG fora do escopo.

## TDD obrigatorio da fase

### Backend - escrever primeiro

- `tests/Feature/Events/EventPublicLinkQrRenderTest.php`
  - job agenda renderer;
  - assets ficam registrados;
  - falha do renderer nao corrompe config.

### Node worker - escrever primeiro

- teste local do renderer:
  - gera `png`;
  - gera `svg`;
  - gera `svg` com `saveAsBlob`.

## Criterios de aceite

- renderer server-side replica o visual salvo com paridade suficiente;
- a feature principal continua funcionando mesmo se o renderer falhar;
- setup nativo de `node-canvas` fica isolado da V1 browser-first.

---

## Sequencia recomendada de implementacao

Para reduzir retrabalho, a ordem deve ser:

1. `Fase 0`
2. `Fase 1`
3. `Fase 2`
4. `Fase 3`
5. `Fase 4`
6. `Fase 5`
7. `Fase 6`
8. `Fase 7`
9. `Fase 8`
10. `Fase 9` apenas se a V1 justificar

---

## Matriz de testes a adicionar

## Frontend

Arquivos provaveis:

- `apps/web/src/modules/qr-code/support/qrDefaults.test.ts`
- `apps/web/src/modules/qr-code/support/qrSchemaNormalizer.test.ts`
- `apps/web/src/modules/qr-code/support/qrSchemaMigrator.test.ts`
- `apps/web/src/modules/qr-code/support/qrPresets.test.ts`
- `apps/web/src/modules/qr-code/support/qrGuardrails.test.ts`
- `apps/web/src/modules/qr-code/support/qrContrastScore.test.ts`
- `apps/web/src/modules/qr-code/support/qrOptionsBuilder.test.ts`
- `apps/web/src/modules/qr-code/hooks/useQrCodePreview.test.ts`
- `apps/web/src/modules/qr-code/hooks/useQrCodeReadability.test.ts`
- `apps/web/src/modules/qr-code/support/qrDecodeProbe.test.ts`
- `apps/web/src/modules/qr-code/support/qrPerformanceMarks.test.ts`
- `apps/web/src/modules/qr-code/support/qrUxTelemetry.test.ts`
- `apps/web/src/modules/qr-code/support/qrSectionReset.test.ts`
- `apps/web/src/modules/qr-code/support/qrCascadeExplanation.test.ts`
- `apps/web/src/modules/events/qr/EventPublicLinkQrTrigger.test.tsx`
- `apps/web/src/modules/events/qr/EventPublicLinkQrEditor.test.tsx`
- `apps/web/src/modules/events/qr/QrCodeEditorAccessibility.test.tsx`
- `apps/web/src/modules/events/qr/qrPresetChooser.test.tsx`
- `apps/web/src/modules/events/components/PublicLinkCard.test.tsx`
- `apps/web/src/modules/events/EventDetailPage.qr.integration.test.tsx`

## Backend

Arquivos provaveis:

- `apps/api/tests/Feature/Events/EventPublicLinkQrConfigTest.php`
- `apps/api/tests/Feature/Events/EventPublicLinkQrDefaultsTest.php`
- `apps/api/tests/Feature/Events/EventPublicLinkQrRenderTest.php`
- `apps/api/tests/Unit/Events/EventPublicLinkQrConfigDataTest.php`
- `apps/api/tests/Unit/Events/EventPublicLinkQrSchemaNormalizerTest.php`
- `apps/api/tests/Unit/Events/GetEventPublicLinkQrConfigActionTest.php`
- `apps/api/tests/Unit/Events/UpsertEventPublicLinkQrConfigActionTest.php`

---

## Comandos obrigatorios por marco

## Marco A - suporte puro e adapter

```bash
cd apps/web
npm run test -- src/modules/qr-code/support
npm run type-check
```

## Marco B - editor shell e preview

```bash
cd apps/web
npm run test -- src/modules/qr-code src/modules/events/qr src/modules/events/components/PublicLinkCard.test.tsx src/modules/events/EventDetailPage.qr.integration.test.tsx
npm run type-check
```

## Marco C - persistencia backend

```bash
cd apps/api
php artisan test tests/Feature/Events/EventPublicLinkQrConfigTest.php tests/Feature/Events/EventPublicLinkQrDefaultsTest.php tests/Unit/Events/EventPublicLinkQrConfigDataTest.php tests/Unit/Events/EventPublicLinkQrSchemaNormalizerTest.php tests/Unit/Events/GetEventPublicLinkQrConfigActionTest.php tests/Unit/Events/UpsertEventPublicLinkQrConfigActionTest.php
```

## Marco D - integracao completa V1

```bash
cd apps/web
npm run test -- src/modules/qr-code src/modules/events/qr src/modules/events/EventDetailPage.qr.integration.test.tsx
npm run type-check

cd ../api
php artisan test tests/Feature/Events/EventPublicLinkQrConfigTest.php tests/Feature/Events/EventDetailAndLinksTest.php tests/Feature/Events/EventBrandingInheritanceTest.php
```

---

## Definicao de pronto da V1

A V1 pode ser considerada pronta quando:

- o QR da pagina de evento abre um editor lazy-load;
- o editor usa schema semantico proprio;
- o schema e versionado e coberto por normalizacao/migracao;
- preview funciona com instancia unica e `update()`;
- save e otimista com rollback;
- config persiste por `event_id + link_key`;
- defaults respeitam branding efetivo;
- presets por cenario de uso existem;
- o fluxo guiado comeca por preset de uso e nao por knobs tecnicos;
- o trigger e o modal respeitam acessibilidade de teclado e foco;
- heuristica de leitura bloqueia configuracoes inseguras;
- metricas minimas de abertura, preview e save existem;
- a bateria definida para V1 esta verde.

---

## Fora do escopo da V1

- QR de Pix;
- QR temporario de WhatsApp;
- editor de QR generico para texto livre;
- renderer server-side como dependencia obrigatoria;
- dependencia de `BarcodeDetector` para a feature funcionar;
- dependencia de `React Performance tracks` para validar performance da V1.
- editor livre de extensoes SVG arbitrarias via `applyExtension`.

---

## Proximo passo imediato

Abrir a implementacao pela `Fase 1`, mas so depois de concluir o spike da `Fase 0` e travar a versao da lib com um wrapper minimo local.
