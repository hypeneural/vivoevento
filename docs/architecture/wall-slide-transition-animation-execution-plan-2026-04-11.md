# Wall slide transition animation execution plan - 2026-04-11

## Objetivo

Transformar a analise de `docs/architecture/wall-slide-transition-animation-analysis-2026-04-11.md` em um plano de execucao implementavel para:

- formalizar um `transition registry` do wall;
- adicionar `transition_mode = fixed | random` sem drift de runtime;
- manter preview e player coerentes;
- expandir animacoes de forma segura, sem trocar de stack;
- preservar performance, reduced motion e previsibilidade operacional.

Este plano cobre apenas a **troca visual entre midias do slideshow do wall**.

Ele nao tenta unificar agora a semantica de slide hero e a semantica de board/slots.

Documento base e fonte de verdade tecnica:

- `docs/architecture/wall-slide-transition-animation-analysis-2026-04-11.md`

Toda decisao abaixo deriva diretamente das secoes:

- `O que a documentacao oficial valida`
- `Melhor abordagem para adicionar novas animacoes`
- `Melhor abordagem para o modo rand`
- `Bateria TDD recomendada antes da implementacao`

---

## Veredito executivo

O P0 real desta entrega nao e "criar animacao nova".

O P0 real e:

1. fechar o contrato de transicao no backend/shared;
2. substituir o `switch` atual por um `transition registry`;
3. resolver o efeito ativo no **advance** do player, nao no render;
4. liberar `random` apenas para layouts `single-item`;
5. garantir parity entre player e preview.

O P1 real entra depois da fundacao:

1. adicionar efeitos seguros novos;
2. permitir `transition_pool` custom;
3. ligar fallback por capability e reduced motion;
4. expor telemetria operacional da transicao.

Em uma frase:

**o ganho principal nao vem de trocar biblioteca; vem de transformar a transicao do wall em um subsistema formal com contrato, registry, scheduler, preview parity e bateria TDD propria.**

---

## Referencias primarias

- `docs/architecture/wall-slide-transition-animation-analysis-2026-04-11.md`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/engine/motion.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/types.ts`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/wall-settings.ts`
- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallTransition.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Http/Requests/SimulateWallRequest.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/Wall/Services/WallLiveSnapshotService.php`
- `apps/api/tests/Feature/Wall/WallOptionsCharacterizationTest.php`

As referencias oficiais de Motion e React continuam centralizadas na doc base, para evitar duplicacao de fonte.

---

## Baseline validada antes da execucao

### Frontend

Executado:

```bash
cd apps/web
npm run test -- src/modules/wall/player/engine/motion.test.ts src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx src/modules/wall/player/wall-theme-architecture-characterization.test.ts src/modules/wall/player/components/WallPlayerRoot.test.tsx
```

Resultado:

- `4 arquivos`
- `20 testes`
- `PASS`

O que esta bateria trava:

- `LayoutRenderer` usa `AnimatePresence mode="wait"` para `single-item`;
- `LayoutRenderer` chama `resolveLayoutTransition()` no caminho do slideshow principal;
- layouts `board` nao obedecem ao `transition_effect` principal hoje;
- `WallPlayerRoot` continua sendo o ponto global de `MotionConfig` e `reducedMotion`.

### Backend

Executado:

```bash
cd apps/api
php artisan test tests/Feature/Wall/WallOptionsCharacterizationTest.php
```

Resultado:

- `2 testes`
- `21 assertions`
- `PASS`

O que esta bateria trava:

- `/wall/options` ainda expoe apenas:
  - `fade`
  - `slide`
  - `zoom`
  - `flip`
  - `none`
- `random` ainda nao existe no contrato publico;
- o contrato atual de transicao continua simples e fixo.

### Leitura pratica da baseline

Hoje o wall esta assim:

- stack correta para evoluir motion sem trocar de engine;
- ponto global de policy ja existe;
- fluxo `single-item` esta suficientemente centralizado;
- o gargalo real e **arquitetura de transicao**, nao falta de biblioteca.

---

## Decisoes tecnicas fixadas antes da implementacao

Estas decisoes ficam travadas para evitar drift durante a execucao:

1. `framer-motion` continua sendo a engine desta entrega.
2. Nao misturar `framer-motion` e `motion/react` dentro da mesma feature.
3. `transition_effect` continua existindo como efeito base.
4. `transition_mode` nasce separado de `transition_effect`.
5. `random` e resolvido no **advance** do runtime, nao no render.
6. `Math.random()` nao entra em `LayoutRenderer`, `motion.ts` nem JSX.
7. `random` entra em P0 apenas para layouts `single-item`.
8. Layouts `board` continuam com semantica propria nesta entrega.
9. `MotionConfig` continua sendo a policy global de motion e reduced motion.
10. Animacoes novas devem priorizar `transform` + `opacity`.
11. `filter`, blur pesado, layout animation agressiva e 3D forte ficam fora do P0.
12. Preview e player devem consumir o mesmo resolver de transicao.
13. `useTransition` e `startTransition` podem ajudar o manager, mas nao viram engine visual do slide.
14. A migracao futura para `motion/react` continua separada desta entrega.

---

## Contrato alvo

## Contrato alvo do P0

No P0, o contrato novo minimo e:

```ts
type WallTransitionMode = 'fixed' | 'random';

interface WallSettings {
  transition_effect: WallTransition;
  transition_mode: WallTransitionMode;
}
```

Semantica travada:

- `transition_effect`
  - continua sendo o efeito base;
- `transition_mode = fixed`
  - usa exatamente `transition_effect`;
- `transition_mode = random`
  - usa um pool default seguro do player;
  - nao usa `Math.random()` em render;
  - so entra no slideshow `single-item`.

### Pool default do P0

Pool segura recomendada:

- `fade`
- `slide`
- `zoom`
- `flip`

`none` fica fora do pool aleatorio do P0.

Motivo:

- `none` continua util para fixed e fallback;
- mas enfraquece a semantica do modo aleatorio.

## Contrato alvo do P1

No P1, o contrato evolui para:

```ts
type WallTransitionMode = 'fixed' | 'random';

interface WallSettings {
  transition_effect: WallTransition;
  transition_mode: WallTransitionMode;
  transition_pool?: WallTransition[] | null;
}
```

Semantica adicional:

- `transition_pool`
  - e opcional;
  - so faz sentido quando `transition_mode = random`;
  - deve ser unica, saneada e capability-aware;
  - deve ignorar ou rejeitar efeitos nao suportados para o layout vigente.

## Contrato alvo de `/wall/options`

P0:

- manter `transitions` como lista oficial de efeitos;
- adicionar `transition_modes`;
- expor defaults de:
  - `transition_effect`
  - `transition_mode`

P1:

- manter `transitions`;
- manter `transition_modes`;
- opcionalmente expor `default_transition_pool`;
- opcionalmente expor metadata por efeito, como:
  - `scope`
  - `premium_only`
  - `reduced_motion_fallback`

---

## Mapa de ownership

## Shared e backend

Arquivos-alvo principais:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallTransition.php`
- novo `apps/api/database/migrations/*_add_transition_mode_to_event_wall_settings.php`
- novo `apps/api/database/migrations/*_add_transition_pool_to_event_wall_settings.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Http/Requests/SimulateWallRequest.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/Wall/Services/WallLiveSnapshotService.php`

## Frontend player

Arquivos-alvo principais:

- `apps/web/src/modules/wall/player/types.ts`
- `apps/web/src/modules/wall/player/engine/motion.ts`
- novo `apps/web/src/modules/wall/player/engine/transition-registry.ts`
- novo `apps/web/src/modules/wall/player/engine/transition-scheduler.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`
- `apps/web/src/modules/wall/player/engine/storage.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`

## Frontend manager e preview

Arquivos-alvo principais:

- `apps/web/src/modules/wall/wall-settings.ts`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`

## Testes

Backend:

- `apps/api/tests/Feature/Wall/WallOptionsCharacterizationTest.php`
- novo `apps/api/tests/Feature/Wall/WallTransitionOptionsTest.php`
- novo `apps/api/tests/Feature/Wall/WallTransitionSettingsTest.php`

Frontend:

- `apps/web/src/modules/wall/player/engine/motion.test.ts`
- novo `apps/web/src/modules/wall/player/engine/transition-registry.test.ts`
- novo `apps/web/src/modules/wall/player/engine/transition-scheduler.test.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.test.tsx`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`

---

## P0 - Fundacao obrigatoria

## Fase 0 - Travar baseline e caracterizacao

Objetivo:

- congelar o estado atual antes de mexer no contrato;
- garantir que os testes atuais continuem descrevendo a arquitetura real.

Subtarefas:

- [x] manter `WallOptionsCharacterizationTest.php` travando que `random` ainda nao existe antes da mudanca;
- [x] manter `LayoutRenderer.transition-characterization.test.tsx` travando a fronteira `single-item` vs `board`;
- [x] manter `WallPlayerRoot.test.tsx` travando `MotionConfig` como policy global;
- [x] registrar no plano que `random` em board fica fora do P0.

### Bateria TDD da fase 0

- [x] rodar `apps/api/tests/Feature/Wall/WallOptionsCharacterizationTest.php`
- [x] rodar `apps/web/src/modules/wall/player/engine/motion.test.ts`
- [x] rodar `apps/web/src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx`
- [x] rodar `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`
- [x] rodar `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`

Cenarios obrigatorios:

- [x] `single-item` usa `AnimatePresence mode="wait"`;
- [x] `board` nao entra no caminho do `transition_effect`;
- [x] `/wall/options` ainda reflete o contrato antigo antes do PR de contrato;
- [x] `MotionConfig` continua centralizado no root.

## Fase 1 - Contrato shared/backend do `transition_mode`

Objetivo:

- expandir o contrato sem quebrar `transition_effect`;
- preparar save, simulate, payload e `/wall/options` para o novo modo.

Arquivos-alvo:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallTransition.php`
- novo `apps/api/database/migrations/*_add_transition_mode_to_event_wall_settings.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Http/Requests/SimulateWallRequest.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/Wall/Services/WallLiveSnapshotService.php`

Subtarefas:

- [x] criar `WallTransitionMode` em `packages/shared-types/src/wall.ts`;
- [x] adicionar `transition_mode` em `WallSettings`;
- [x] criar migration para `transition_mode` com default `fixed`;
- [x] atualizar `EventWallSetting` para cast/default do novo campo;
- [x] atualizar `UpdateWallSettingsRequest` para validar `transition_mode`;
- [x] atualizar `SimulateWallRequest` para aceitar `transition_mode`;
- [x] manter `transition_effect` obrigatorio e compativel;
- [x] atualizar `WallPayloadFactory` e `WallLiveSnapshotService` para expor `transition_mode`;
- [x] expor `transition_modes` em `/wall/options`;
- [x] manter compatibilidade de payload legado sem `transition_mode`, assumindo `fixed`.

### Bateria TDD da fase 1

- [x] criar `apps/api/tests/Feature/Wall/WallTransitionOptionsTest.php`
- [x] criar `apps/api/tests/Feature/Wall/WallTransitionSettingsTest.php`
- [x] ampliar `apps/api/tests/Feature/Wall/WallOptionsCharacterizationTest.php`

Cenarios obrigatorios:

- [x] `/wall/options` passa a expor `transition_modes = [fixed, random]`;
- [x] `/wall/options` continua expondo apenas os efeitos oficiais atuais no P0;
- [x] salvar settings sem `transition_mode` persiste `fixed`;
- [x] `transition_mode` invalido falha em validacao;
- [x] `simulate` aceita `transition_mode` sem quebrar preview.

### Validacao do PR 1 - contrato shared/backend de `transition_mode` - 2026-04-11

Backend:

- `cd apps/api && php artisan test tests/Feature/Wall/WallOptionsCharacterizationTest.php tests/Feature/Wall/WallTransitionOptionsTest.php tests/Feature/Wall/WallTransitionSettingsTest.php`
  - `7 testes`
  - `70 assertions`
  - `PASS`
- `cd apps/api && php artisan test tests/Feature/Wall tests/Unit/Modules/Wall`
  - `94 testes`
  - `634 assertions`
  - `PASS`

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/wall-settings.test.ts src/modules/wall/player/api.test.ts src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
  - `3 arquivos`
  - `16 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `64 arquivos`
  - `310 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria do PR 1 travou:

- `/wall/options` agora separa `transitions` de `transition_modes`;
- `random` continua fora da lista de efeitos visuais do slideshow;
- `transition_mode` persiste em `event_wall_settings` e volta em `GET/PATCH` de settings;
- `transition_mode` invalido falha em `update` e em `simulate`;
- payload legado sem `transition_mode` continua resolvendo para `fixed`;
- os tipos compartilhados e a caracterizacao frontend ja reconhecem o novo campo sem abrir ainda a UI do editor.

## Fase 2 - Transition registry no frontend

Objetivo:

- tirar a logica de transicao de um `switch` unico e move-la para um registry declarativo;
- manter 100% de compatibilidade com os efeitos atuais no primeiro PR.

Arquivos-alvo:

- novo `apps/web/src/modules/wall/player/engine/transition-registry.ts`
- `apps/web/src/modules/wall/player/engine/motion.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`

Subtarefas:

- [x] criar `WallTransitionDefinition` com:
  - `id`
  - `scope`
  - `buildVariants`
  - `buildTransition`
  - `reducedMotionFallback`
- [x] registrar `fade`, `slide`, `zoom`, `flip` e `none`;
- [x] mover a resolucao de variants do `switch` para o registry;
- [x] manter `resolveLayoutTransition()` como facade compativel do P0;
- [x] garantir fallback seguro quando o efeito for invalido;
- [x] manter layouts `board` fora da semantica do slideshow principal.

### Bateria TDD da fase 2

- [x] criar `apps/web/src/modules/wall/player/engine/transition-registry.test.ts`
- [x] ampliar `apps/web/src/modules/wall/player/engine/motion.test.ts`
- [x] ampliar `apps/web/src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx`

Cenarios obrigatorios:

- [x] cada efeito legado resolve uma definicao valida no registry;
- [x] reduced motion cai para fallback seguro do registro;
- [x] `LayoutRenderer` continua usando `wait` no slideshow principal;
- [x] `board` continua fora desse caminho;
- [x] a migracao do `switch` para o registry nao muda a animacao atual.

### Validacao do PR 2 - transition registry no frontend - 2026-04-11

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/engine/transition-registry.test.ts src/modules/wall/player/engine/motion.test.ts src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx`
  - `4 arquivos`
  - `18 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `65 arquivos`
  - `315 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

O que esta bateria do PR 2 travou:

- o slideshow principal agora resolve efeitos a partir de um `transition registry`;
- `resolveLayoutTransition()` continua sendo a facade publica compativel do P0;
- efeitos invalidos passam a cair para `fade` de forma segura;
- reduced motion continua derrubando o efeito para `none` via metadata do registry;
- layouts `board` continuam fora da semantica do slideshow principal;
- a troca para registry nao abriu regressao na suite ampla do modulo `wall`.

## Fase 3 - Scheduler deterministico e `random` no runtime

Objetivo:

- fazer `random` nascer no lugar certo: o runtime do player;
- impedir troca de efeito por re-render;
- manter debug e parity previsiveis.

Arquivos-alvo:

- novo `apps/web/src/modules/wall/player/engine/transition-scheduler.ts`
- `apps/web/src/modules/wall/player/types.ts`
- `apps/web/src/modules/wall/player/engine/reducer.ts`
- `apps/web/src/modules/wall/player/engine/storage.ts`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.tsx`

Subtarefas:

- [x] adicionar ao `WallPlayerState`:
  - `activeTransitionEffect`
  - `lastTransitionEffect`
  - `transitionAdvanceCount`
- [x] criar scheduler puro para:
  - `fixed`
  - `random`
  - anti-repeticao
  - fallback seguro
- [x] usar pool default segura no P0:
  - `fade`
  - `slide`
  - `zoom`
  - `flip`
- [x] resolver `activeTransitionEffect` no `advance` do reducer;
- [x] resolver `activeTransitionEffect` tambem no boot inicial quando o item atual e definido;
- [x] manter `random` desabilitado para layouts `board` no P0;
- [x] fazer `LayoutRenderer` ler `activeTransitionEffect` do runtime, e nao sortear nada localmente;
- [x] decidir se `transitionAdvanceCount` entra em `storage.ts` para manter continuidade apos reload.

### Bateria TDD da fase 3

- [x] criar `apps/web/src/modules/wall/player/engine/transition-scheduler.test.ts`
- [x] ampliar `apps/web/src/modules/wall/player/hooks/useWallEngine.test.tsx`
- [x] ampliar `apps/web/src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx`
- [x] criar ou ampliar teste de `storage.ts` se `transitionAdvanceCount` for persistido

Cenarios obrigatorios:

- [x] `transition_mode = fixed` usa exatamente `transition_effect`;
- [x] `transition_mode = random` escolhe efeito no `advance`, nao no render;
- [x] re-render do mesmo slide nao muda o efeito ativo;
- [x] anti-repeticao evita repetir o ultimo efeito quando houver alternativa;
- [x] `board` ignora `random` do slideshow no P0;
- [x] reduced motion continua derrubando para fallback seguro;
- [x] o scheduler nao usa `Math.random()` dentro de JSX.

### Validacao do PR 3 - scheduler deterministico no runtime e `random` so para `single-item` - 2026-04-11

Frontend:

- `cd apps/web && npm run test -- src/modules/wall/player/engine/transition-scheduler.test.ts src/modules/wall/player/engine/storage.test.ts src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
  - `5 arquivos`
  - `32 testes`
  - `PASS`
- `cd apps/web && npm run test -- src/modules/wall`
  - `67 arquivos`
  - `324 testes`
  - `PASS`
- `cd apps/web && npm run type-check`
  - `PASS`

Decisao operacional fechada nesta fase:

- `activeTransitionEffect`, `lastTransitionEffect` e `transitionAdvanceCount` entram no runtime persistido para manter continuidade apos reload e reconnect do player.

O que esta bateria do PR 3 travou:

- o efeito ativo do slideshow nasce no runtime do reducer, nao no render;
- `transition_mode = random` so roda quando o layout solicitado e `single`;
- layouts `board` continuam ignorando `random` e usando o efeito base configurado;
- o efeito ativo fica estavel enquanto o slide atual nao muda, inclusive se settings forem atualizadas;
- a anti-repeticao evita repetir o ultimo efeito quando ha outras opcoes no pool seguro;
- `LayoutRenderer` passa a consumir `activeTransitionEffect` vindo do runtime;
- o runtime persistido guarda estado suficiente para retomar a mesma trilha de transicao apos reload.

## Fase 4 - Manager e preview parity

Objetivo:

- expor a nova configuracao no editor;
- impedir que preview e player implementem regras diferentes.

Arquivos-alvo:

- `apps/web/src/modules/wall/wall-settings.ts`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`

Subtarefas:

- [x] adicionar `transition_mode` na normalizacao de draft/settings;
- [x] hidratar payloads antigos como `fixed`;
- [x] expor controle de `fixed | random` no `WallAppearanceTab`;
- [x] manter a lista de efeitos em sincronia com `/wall/options`;
- [x] impedir `random` em layouts `board` no UI do P0, com copy explicita;
- [x] reaproveitar o mesmo scheduler/resolver no preview;
- [x] impedir divergencia entre preview local e player vivo.

### Bateria TDD da fase 4

- [x] ampliar `apps/web/src/modules/wall/wall-settings.test.ts`
- [x] ampliar `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx`
- [x] ampliar `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- [x] alinhar `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`

Cenarios obrigatorios:

- [x] draft antigo sem `transition_mode` continua editavel;
- [x] manager salva `fixed` e `random` corretamente;
- [x] `random` em `grid`, `mosaic`, `carousel` e `puzzle` fica bloqueado ou normalizado para `fixed`;
- [x] preview e player resolvem o mesmo efeito para a mesma configuracao base;
- [x] o editor nao cria dirty state infinito por causa do campo novo.

---

## P1 - Expansao segura

## Fase 5 - Novos efeitos e `transition_pool`

Objetivo:

- aumentar repertorio visual com custo controlado;
- permitir random custom sem reabrir a fundacao.

Arquivos-alvo:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Enums/WallTransition.php`
- novo `apps/api/database/migrations/*_add_transition_pool_to_event_wall_settings.php`
- `apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php`
- `apps/api/app/Modules/Wall/Http/Requests/SimulateWallRequest.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`
- `apps/web/src/modules/wall/player/engine/transition-registry.ts`
- `apps/web/src/modules/wall/player/engine/transition-scheduler.ts`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`

Subtarefas:

- [ ] adicionar efeitos seguros novos:
  - `lift-fade`
  - `cross-zoom`
  - `swipe-up`
- [ ] manter `blur-fade` fora do default ate budget/capability validarem;
- [ ] adicionar `transition_pool` ao contrato shared/backend;
- [ ] persistir `transition_pool` como JSON saneado;
- [ ] validar `transition_pool` como lista unica de efeitos permitidos;
- [ ] bloquear `none` no pool custom do modo random;
- [ ] expor `transition_pool` no manager apenas quando `transition_mode=random`;
- [ ] manter fallback para pool default quando a pool custom vier vazia ou invalida.

### Bateria TDD da fase 5

- [ ] ampliar `apps/api/tests/Feature/Wall/WallTransitionSettingsTest.php`
- [ ] ampliar `apps/api/tests/Feature/Wall/WallTransitionOptionsTest.php`
- [ ] ampliar `apps/web/src/modules/wall/player/engine/transition-registry.test.ts`
- [ ] ampliar `apps/web/src/modules/wall/player/engine/transition-scheduler.test.ts`
- [ ] ampliar `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx`

Cenarios obrigatorios:

- [ ] `transition_pool` invalida falha em validacao;
- [ ] `transition_pool` vazia cai para pool default segura;
- [ ] cada efeito novo tem definicao, fallback e teste proprio;
- [ ] `random` respeita pool custom sem perder anti-repeticao;
- [ ] manager nao permite pool incompativel com o layout vigente.

## Fase 6 - Capability, reduced motion e observabilidade operacional

Objetivo:

- ligar a transicao ao budget real do player;
- tornar fallback e degradacao observaveis.

Arquivos-alvo:

- `packages/shared-types/src/wall.ts`
- `apps/api/app/Modules/Wall/Http/Resources/WallDiagnosticsResource.php`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.ts`
- `apps/web/src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.tsx`
- `apps/web/src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.test.tsx`

Subtarefas:

- [ ] adicionar na telemetria do player:
  - `active_transition_effect`
  - `transition_mode`
  - `transition_random_pick_count`
  - `transition_fallback_count`
- [ ] marcar fallback por:
  - reduced motion
  - capability tier
  - efeito indisponivel
- [ ] refletir esses campos em diagnostics;
- [ ] mostrar copy operacional simples no manager;
- [ ] preparar regra capability-aware para efeitos premium mais caros no futuro.

### Bateria TDD da fase 6

- [ ] ampliar `apps/web/src/modules/wall/player/hooks/useWallPlayer.test.tsx`
- [ ] ampliar `apps/web/src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.test.tsx`
- [ ] ampliar suite backend que cobre diagnostics do wall

Cenarios obrigatorios:

- [ ] heartbeat carrega o efeito realmente ativo, nao so o configurado;
- [ ] fallback por reduced motion incrementa contador apropriado;
- [ ] diagnostics mostram quando `random` esta ativo;
- [ ] diagnostics mostram quando o player caiu para fallback seguro.

---

## O que fica fora do P0/P1

- `random` para `grid`, `mosaic`, `carousel` ou `puzzle`;
- shared transitions premium com `LayoutGroup/layoutId` como prerequisito;
- migracao de `framer-motion` para `motion/react`;
- segunda biblioteca de animacao;
- `Math.random()` em render;
- blur pesado ou 3D agressivo como default universal;
- unificacao forcada entre slide hero e burst/slot de board.

---

## Ordem recomendada de entrega

## PR 1 - Baseline e contrato do modo

Entregar:

- Fase 0 completa
- Fase 1 completa

Saida esperada:

- `transition_mode` existe em contrato e payload;
- `/wall/options` expoe modos de transicao;
- payload legado continua funcionando como `fixed`.

## PR 2 - Registry de transicao

Entregar:

- Fase 2 completa

Saida esperada:

- o `switch` some como ponto central;
- o registry passa a ser a fonte de verdade dos efeitos atuais.

## PR 3 - Scheduler runtime e modo random

Entregar:

- Fase 3 completa

Saida esperada:

- `random` funciona para `single-item`;
- efeito ativo nasce no reducer/runtime;
- re-render nao altera efeito.

## PR 4 - Manager e preview parity

Entregar:

- Fase 4 completa

Saida esperada:

- editor salva `fixed | random`;
- preview e player usam a mesma regra;
- `random` em board continua bloqueado.

## PR 5 - Expansao segura

Entregar:

- Fase 5 completa
- Fase 6 completa

Saida esperada:

- novos efeitos seguros entram;
- `transition_pool` custom passa a existir;
- diagnostics mostram fallback e efeito ativo.

---

## Sequencia recomendada por sprint

### Sprint 1

- PR 1
- PR 2

### Sprint 2

- PR 3
- PR 4

### Sprint 3

- PR 5
- medicao operacional em evento real ou ambiente controlado

---

## Matriz TDD consolidada

## Backend - manter

- `apps/api/tests/Feature/Wall/WallOptionsCharacterizationTest.php`

## Backend - criar

- `apps/api/tests/Feature/Wall/WallTransitionOptionsTest.php`
- `apps/api/tests/Feature/Wall/WallTransitionSettingsTest.php`

## Frontend - manter

- `apps/web/src/modules/wall/player/engine/motion.test.ts`
- `apps/web/src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx`
- `apps/web/src/modules/wall/player/hooks/useWallEngine.test.tsx`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- `apps/web/src/modules/wall/player/components/WallPlayerRoot.test.tsx`
- `apps/web/src/modules/wall/player/wall-theme-architecture-characterization.test.ts`
- `apps/web/src/modules/wall/player/hooks/useWallPlayer.test.tsx`

## Frontend - criar

- `apps/web/src/modules/wall/player/engine/transition-registry.test.ts`
- `apps/web/src/modules/wall/player/engine/transition-scheduler.test.ts`

---

## Comandos obrigatorios por marco

## Marco A - contrato backend

```bash
cd apps/api
php artisan test tests/Feature/Wall/WallOptionsCharacterizationTest.php tests/Feature/Wall/WallTransitionOptionsTest.php tests/Feature/Wall/WallTransitionSettingsTest.php
```

## Marco B - registry frontend

```bash
cd apps/web
npm run test -- src/modules/wall/player/engine/transition-registry.test.ts src/modules/wall/player/engine/motion.test.ts src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx src/modules/wall/player/components/WallPlayerRoot.test.tsx
npm run type-check
```

## Marco C - scheduler e random

```bash
cd apps/web
npm run test -- src/modules/wall/player/engine/transition-scheduler.test.ts src/modules/wall/player/hooks/useWallEngine.test.tsx src/modules/wall/player/components/LayoutRenderer.transition-characterization.test.tsx src/modules/wall/player/wall-theme-architecture-characterization.test.ts
npm run type-check
```

## Marco D - manager e preview parity

```bash
cd apps/web
npm run test -- src/modules/wall/wall-settings.test.ts src/modules/wall/components/manager/inspector/WallAppearanceTab.test.tsx src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx src/modules/wall/player/wall-theme-architecture-characterization.test.ts
npm run type-check
```

## Marco E - P1 completo

```bash
cd apps/api
php artisan test tests/Feature/Wall/WallOptionsCharacterizationTest.php tests/Feature/Wall/WallTransitionOptionsTest.php tests/Feature/Wall/WallTransitionSettingsTest.php

cd ../web
npm run test -- src/modules/wall/player/engine/transition-registry.test.ts src/modules/wall/player/engine/transition-scheduler.test.ts src/modules/wall/player/hooks/useWallPlayer.test.tsx src/modules/wall/components/manager/diagnostics/WallPlayerRuntimeCard.test.tsx
npm run type-check
```

---

## Definicao de pronto do P0

O P0 so pode ser considerado pronto quando:

- [ ] `transition_mode` existe em contrato shared, backend e frontend;
- [ ] payload legado sem `transition_mode` funciona como `fixed`;
- [ ] `/wall/options` expoe `transition_modes`;
- [ ] registry substitui o `switch` como fonte de verdade;
- [ ] `random` nasce no runtime do player, nao no render;
- [ ] re-render do mesmo slide nao altera o efeito;
- [ ] `random` fica restrito a `single-item`;
- [ ] preview e player usam o mesmo resolver;
- [ ] reduced motion continua derrubando para fallback seguro;
- [ ] nenhum teste de caracterizacao atual do wall regressa.

## Definicao de pronto do P1

O P1 so pode ser considerado pronto quando:

- [ ] `transition_pool` custom funciona e e validada;
- [ ] os efeitos novos seguros entram com testes proprios;
- [ ] diagnostics mostram efeito ativo e fallback;
- [ ] o manager explica claramente quando `random` foi degradado;
- [ ] o time consegue medir repeticao, fallback e uso dos efeitos sem inspecao manual do DOM.

---

## Recomendacao final

O erro mais caro aqui seria pular direto para "efeito bonito".

O caminho seguro continua sendo:

1. contrato;
2. registry;
3. scheduler;
4. preview parity;
5. so depois, efeitos novos e pool custom.

Se essa ordem for respeitada, o wall ganha variedade visual com previsibilidade.

Se essa ordem for ignorada, o time troca um `switch` simples por um conjunto de bugs intermitentes de render, preview e debug.
