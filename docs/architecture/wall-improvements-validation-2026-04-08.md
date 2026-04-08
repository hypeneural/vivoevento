# Validacao Das Melhorias Do Telao - 2026-04-08

## Objetivo

Validar o estado real das melhorias do modulo `Wall/Telao` contra:

- `C:\Users\Usuario\.gemini\antigravity\brain\a2179783-ed8c-4e65-960f-0a7f5fd53e56\task.md.resolved`
- `C:\Users\Usuario\.gemini\antigravity\brain\a2179783-ed8c-4e65-960f-0a7f5fd53e56\implementation_plan.md.resolved`
- `C:\Users\Usuario\.gemini\antigravity\brain\a2179783-ed8c-4e65-960f-0a7f5fd53e56\artifacts\wall_comparativo_momentloop.md.resolved`
- `C:\Users\Usuario\.gemini\antigravity\brain\a2179783-ed8c-4e65-960f-0a7f5fd53e56\artifacts\kululu_reverseeng.md.resolved`
- `C:\Users\Usuario\.gemini\antigravity\brain\a2179783-ed8c-4e65-960f-0a7f5fd53e56\artifacts\momentloop_templates_reverseeng.md.resolved`

O foco principal desta auditoria foi a lista que aparecia como pendente no task original:

- `UpdateWallSettingsRequest: validation rules p/ novos layouts`
- `1.2 Model EventWallAd + factory`
- `1.3 Model EventWallSetting updates`
- `1.4 StoreWallAdRequest + validacao segura`
- `1.5 EventWallAdController`
- `1.6 WallPayloadFactory: ads no payload`
- `1.7 WallBroadcasterService: broadcastAdsUpdated`
- `1.8 Evento WallAdsUpdated`
- `1.11 Reducer: ad state + actions`
- `1.13 WallPlayerRoot: integrar ad overlay`
- `1.14 useWallRealtime: listen wall.ads.updated`
- `1.15 Testes backend completos`
- `1.16 Testes frontend completos`

## Resumo Executivo

Status consolidado da lista pendente:

- `11 itens` estao implementados e validados no codigo atual.
- `1 item` esta funcionalmente implementado, mas com arquitetura diferente da especificada no plano.
- `1 item` foi reforcado nesta auditoria com hardening e testes adicionais.

Veredito:

- O backend de anuncios do telao esta funcional e agora mais seguro.
- O player publico do telao ja consome anuncios, payload de boot e updates realtime.
- A trilha de anuncios no frontend nao esta no `reducer` como o task planejava; ela foi implementada em um hook paralelo (`useAdEngine`).
- O painel administrativo do telao agora fecha a gestao operacional de patrocinadores/anuncios ponta a ponta.

## Ajustes Realizados Nesta Auditoria

Foram aplicados ajustes reais no codigo durante esta validacao:

- `apps/api/app/Modules/Wall/Http/Controllers/EventWallAdController.php`
  - upload agora deriva a extensao final do MIME real detectado por `finfo`, e nao da extensao enviada pelo cliente.
  - reorder agora rejeita explicitamente IDs de anuncios que nao pertencem ao wall atual.
- `apps/api/tests/Feature/Wall/WallAdsTest.php`
  - adicionado teste garantindo extensao segura baseada no MIME real.
  - adicionado teste rejeitando reorder cruzado entre walls.
  - adicionados testes de broadcast para `WallAdsUpdated` em create/delete.
- `apps/web/src/modules/wall/player/components/AdOverlay.test.tsx`
  - nova cobertura para timeout de imagem, `onEnded` de video e safety timeout.
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`
  - nova cobertura para troca entre `LayoutRenderer` e `AdOverlay`.
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.test.tsx`
  - nova cobertura para hidratacao de `ads` no boot e update realtime via `wall.ads.updated`.

## Fechamento Do Gap Operacional Do Painel Admin

Em `2026-04-08`, o gap restante do painel admin foi fechado com integracao real no manager do telao:

- `apps/web/src/modules/wall/pages/EventWallManagerPage.tsx`
  - nova secao de patrocinadores no telao.
  - configuracao de `ad_mode`, `ad_frequency` e `ad_interval_minutes`.
  - listagem de criativos ativos.
  - upload de imagem/video.
  - remocao e reorder dos anuncios.
  - invalidacao de cache local com `queryKeys.wall.ads(eventId)` para manter a tela sincronizada.
- `apps/web/src/modules/wall/api.ts`
  - cliente frontend agora exposto para `getEventWallAds`, `createEventWallAd`, `deleteEventWallAd` e `reorderEventWallAds`.
- `apps/web/src/lib/api-types.ts`
  - adicionados `ApiWallAdMode`, `ApiWallAdItem` e campos de anuncios em `ApiWallSettings`.
- `apps/web/src/modules/wall/wall-settings.ts`
  - o draft do manager agora clona, normaliza, compara e persiste os campos de anuncios.
- `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`
  - cobertura de upload, reorder, delete e persistencia do agendamento de anuncios no manager.

## Status Item A Item

| Item | Status | Evidencia | Observacao |
|---|---|---|---|
| `UpdateWallSettingsRequest: validation rules p/ novos layouts` | `CHECK` | `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php` usa `Rule::enum(WallLayout::class)` | Como `WallLayout` ja contem `kenburns`, `spotlight`, `gallery`, `carousel`, `mosaic` e `grid`, a validacao cobre os layouts novos. |
| `1.2 Model EventWallAd + factory` | `CHECK` | `apps/api/app/Modules/Wall/Models/EventWallAd.php` e `apps/api/database/factories/EventWallAdFactory.php` | Model, factory, casts e scopes presentes. |
| `1.3 Model EventWallSetting updates` | `CHECK` | `apps/api/app/Modules/Wall/Models/EventWallSetting.php` | `fillable`, `casts`, relacoes `ads()` e `activeAds()` presentes. |
| `1.4 StoreWallAdRequest + validacao segura (finfo_file)` | `CHECK` | `apps/api/app/Modules/Wall/Http/Requests/StoreWallAdRequest.php` e `apps/api/app/Modules/Wall/Http/Controllers/EventWallAdController.php` | Request valida tamanho/extensoes; controller confirma MIME real com `finfo` antes de salvar. |
| `1.5 EventWallAdController (CRUD + upload + reorder)` | `CHECK` | `apps/api/app/Modules/Wall/Http/Controllers/EventWallAdController.php` e `apps/api/app/Modules/Wall/routes/api.php` | CRUD presente. Nesta auditoria o reorder foi endurecido contra IDs externos e o upload passou a mapear extensao segura pelo MIME real. |
| `1.6 WallPayloadFactory: ads no payload` | `CHECK` | `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php` e `apps/api/app/Modules/Wall/Http/Resources/WallBootResource.php` | `ads` entram no boot publico e `settings` carregam `ad_mode`, `ad_frequency` e `ad_interval_minutes`. |
| `1.7 WallBroadcasterService: broadcastAdsUpdated` | `CHECK` | `apps/api/app/Modules/Wall/Services/WallBroadcasterService.php` | Metodo existe e publica payload publico dos anuncios ativos. |
| `1.8 Evento WallAdsUpdated` | `CHECK` | `apps/api/app/Modules/Wall/Events/WallAdsUpdated.php` | Evento existe e usa `broadcastAs()` com `wall.ads.updated`. |
| `1.11 Reducer: ad state + actions (advance check, ad-finished)` | `PARCIAL` | `apps/web/src/modules/wall/player/engine/reducer.ts` e `apps/web/src/modules/wall/player/hooks/useAdEngine.ts` | O comportamento existe, mas nao esta no `reducer`. Foi implementado em `useAdEngine`, em paralelo ao engine principal. Funciona no runtime, mas nao cumpre 100% a arquitetura originalmente pedida. |
| `1.13 WallPlayerRoot: integrar ad overlay` | `CHECK` | `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx` | Quando `currentAd` existe, o player troca o layout principal por `AdOverlay`. |
| `1.14 useWallRealtime: listen wall.ads.updated` | `CHECK` | `apps/web/src/modules/wall/player/hooks/useWallRealtime.ts` e `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts` | Listener existe e atualiza `ads` em tempo real. |
| `1.15 Testes backend completos (Pest)` | `CHECK` | `apps/api/tests/Feature/Wall/WallAdsTest.php` + demais testes `Wall` | A cobertura do backend para a trilha de anuncios ficou consistente apos os testes adicionados nesta auditoria. |
| `1.16 Testes frontend completos (Vitest)` | `CHECK` | `apps/web/src/modules/wall/player/hooks/useAdEngine.test.ts`, `apps/web/src/modules/wall/player/components/AdOverlay.test.tsx`, `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`, `apps/web/src/modules/wall/player/hooks/useWallPlayer.test.tsx`, `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx` | A cobertura do modulo `wall` passou integralmente e o `npm run test` global do app web voltou a ficar verde nesta rodada. |

## Evidencias Tecnicas Relevantes

### Backend

- `apps/api/database/migrations/2026_04_07_225123_create_event_wall_ads_table.php`
  - cria `event_wall_ads`.
  - adiciona `ad_mode`, `ad_frequency` e `ad_interval_minutes` em `event_wall_settings`.
- `apps/api/app/Modules/Wall/Models/EventWallAd.php`
  - model ja usa `HasFactory`.
  - relacao `wallSetting()`.
  - scope `active()`.
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
  - `settings()` inclui configuracao de anuncios.
  - `ads()` serializa anuncios ativos para o player publico.
- `apps/api/app/Modules/Wall/Http/Resources/WallBootResource.php`
  - boot publico retorna `event`, `files`, `settings` e `ads`.
- `apps/api/app/Modules/Wall/Services/WallBroadcasterService.php`
  - `broadcastAdsUpdated()` publica `WallAdsUpdated`.

### Frontend

- `packages/shared-types/src/wall.ts`
  - tipos `WallAdItem`, `WallAdMode`, `WallBootData.ads`, `WALL_EVENT_NAMES.adsUpdated`.
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
  - carrega `ads` do boot.
  - aplica updates realtime via callback `onAdsUpdated`.
- `apps/web/src/modules/wall/player/hooks/useWallRealtime.ts`
  - faz bind em `wall.ads.updated`.
- `apps/web/src/modules/wall/player/hooks/useAdEngine.ts`
  - controla quando o anuncio entra.
  - seleciona anuncio round-robin.
  - finaliza o anuncio e retorna ao slideshow.
- `apps/web/src/modules/wall/player/components/AdOverlay.tsx`
  - trata imagem com timer.
  - trata video com `onEnded`.
  - usa timeout de seguranca para video travado.
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
  - alterna entre `LayoutRenderer` e `AdOverlay`.

## Gaps Reais Que Continuam Abertos

Estes pontos nao impedem o veredito positivo sobre a trilha central de anuncios e layouts, mas continuam abertos no produto como um todo:

### 1. Divergencia arquitetural no item 1.11

O plano original pedia o estado de anuncios dentro do `reducer`.

O codigo atual resolveu isso fora do `reducer`, em `useAdEngine`.

Impacto:

- funcionalmente o player toca anuncios.
- tecnicamente o requisito do task nao foi cumprido literalmente.
- para uma auditoria de "100% conforme o plano", este item precisa continuar marcado como `PARCIAL`.

### 2. Existem hooks/componentes de features comparativas que ainda nao estao plugados na UI final

Durante a auditoria apareceram componentes e hooks prontos, mas sem integracao visivel nas telas finais:

- `apps/web/src/modules/wall/player/hooks/useEmbedMode.ts`
- `apps/web/src/modules/wall/player/hooks/useQRDraggable.ts`
- `apps/web/src/modules/wall/player/components/QRFlipCard.tsx`

Ao mesmo tempo:

- `apps/web/src/modules/wall/player/WallPlayerPage.tsx` nao usa `useEmbedMode`.
- `apps/web/src/modules/wall/player/components/BrandingOverlay.tsx` nao usa `useQRDraggable` nem `QRFlipCard`.

Ou seja:

- ha avancos de implementacao comparativa com concorrentes.
- nem tudo que esta marcado como pronto no task antigo foi de fato conectado na experiencia final.

## Verificacao Final Executada

### 1. Backend

Comando executado:

```bash
cd apps/api && php artisan test --filter=Wall
```

Resultado:

- `PASS`
- `54 testes passaram`
- `219 assertions`

### 2. Frontend - suite global do app web

Comando executado:

```bash
cd apps/web && npm run test
```

Resultado:

- `PASS`
- `53 arquivos de teste passaram`
- `263 testes passaram`

Leitura correta desse resultado:

- o app web inteiro voltou a ficar com suite global verde.
- o fechamento do manager de patrocinadores nao introduziu regressao visivel na aplicacao.

### 3. Frontend - suite isolada do modulo wall

Comando executado:

```bash
cd apps/web && npx vitest run src/modules/wall
```

Resultado:

- `PASS`
- `22 arquivos de teste passaram`
- `163 testes passaram`

### 4. Frontend - type check

Comando executado:

```bash
cd apps/web && npm run type-check
```

Resultado:

- `PASS`

### 5. Shared types

Comando pedido no checklist antigo:

```bash
cd packages/shared-types && npm run build
```

Resultado real:

- `NAO EXECUTAVEL`
- `packages/shared-types` nao possui `package.json`
- portanto o comando do checklist esta desatualizado ou nunca foi finalizado no monorepo

Validacao tecnica substituta executada:

```bash
cd packages/shared-types
C:\laragon\www\eventovivo\apps\web\node_modules\.bin\tsc.cmd --noEmit src\index.ts src\wall.ts
```

Resultado:

- `PASS`

## Conclusao Final

Se a pergunta for:

> "as pendencias centrais do backend/player de anuncios e layouts do telao foram implementadas?"

Resposta:

- `sim, na maior parte`
- a trilha de runtime do telao esta pronta e validada
- o backend e a suite especifica do modulo `Wall` passaram

Se a pergunta for:

> "o plano antigo foi cumprido 100% exatamente como escrito?"

Resposta:

- `nao`

Os pontos que impedem esse `100%` literal sao:

- o item `1.11` nao foi implementado no `reducer`, e sim em `useAdEngine`
- o comando `npm run build` em `packages/shared-types` nao existe no estado atual do monorepo

## Recomendacao Objetiva

Para fechar o tema como `100% concluido` em produto e operacao:

1. decidir se o requisito `1.11` sera aceito como "feito por outra arquitetura" ou se precisa migrar de fato para o `reducer`
2. formalizar `packages/shared-types` como pacote buildavel ou atualizar o checklist oficial para refletir o comando real
