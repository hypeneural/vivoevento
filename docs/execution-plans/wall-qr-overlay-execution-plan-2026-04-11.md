# Wall QR Overlay Execution Plan - 2026-04-11

## Objetivo

Transformar `wall-qr-overlay-current-state-analysis-2026-04-11.md` em um plano de execucao implementavel, detalhado e orientado por TDD para elevar o QR do telao de um overlay padrao para um subsistema configuravel, persistido e coerente entre manager, preview e player ao vivo.

Este plano responde 8 perguntas:

1. o que esta validado hoje por codigo e testes;
2. o que fica travado como decisao tecnica antes de escrever codigo;
3. qual passa a ser o contrato `qr_overlay` no backend e no frontend;
4. como migrar sem quebrar `show_qr` e o player atual;
5. como entregar personalizacao com baixo risco operacional;
6. onde entra o modo operador com drag, resize e hide;
7. quais testes precisam nascer antes de cada etapa;
8. qual e a definicao de pronto antes de ligar o fluxo como padrao do produto.

Documento base:

- `docs/architecture/wall-qr-overlay-current-state-analysis-2026-04-11.md`

Documentos correlatos:

- `docs/architecture/event-universal-qr-editor-analysis-2026-04-11.md`
- `docs/execution-plans/event-universal-qr-editor-execution-plan-2026-04-11.md`
- `docs/architecture/wall-video-playback-current-state-2026-04-08.md`
- `docs/execution-plans/wall-video-playback-execution-plan-2026-04-08.md`

---

## Validacao executada antes do plano

### Frontend

Comando:

```bash
cd apps/web
npm run test -- src/modules/wall/player/components/BrandingOverlay.test.tsx src/modules/wall/player/wall-qr-overlay-characterization.test.ts src/modules/wall/player/hooks/useQRDraggable.test.ts
```

Resultado:

- `3` arquivos passaram;
- `14` testes passaram.

Comando:

```bash
cd apps/web
npm run type-check
```

Resultado:

- `type-check` passou.

### Leitura pratica da baseline

Esta rodada ja travou os seguintes fatos:

- o QR ao vivo hoje nasce em `BrandingOverlay`, nao em cada tema;
- o valor real do QR vem de `event.upload_url`;
- `show_qr` ainda e o switch funcional do player;
- o preview do manager ainda usa URL fake fixa;
- `instructions_text` nao personaliza o card do QR;
- `puzzle` com `anchor_mode = qr_prompt` ainda entrega so texto;
- `useQRDraggable` existe, mas nao e trilha de produto pronta:
  - nao esta ligado ao overlay real;
  - nao faz clamp real de viewport;
  - persiste apenas em `localStorage`.

---

## Decisoes tecnicas fixadas antes da implementacao

Estas decisoes ficam travadas para evitar drift durante a execucao:

1. O QR do wall passa a ter contrato proprio em `WallSettings` e nao fica escondido dentro de `theme_config`.
2. O contrato novo se chama `qr_overlay` e vira a fonte de verdade do overlay.
3. `show_qr` continua existindo na primeira etapa apenas como compatibilidade, nao como desenho final.
4. O conteudo do QR na V1 continua vindo de `event.upload_url`; o operador nao edita payload livremente.
5. Preview e player ao vivo precisam consumir a mesma semantica de `qr_overlay`; o preview com URL fake fixa deixa de ser baseline aceitavel.
6. O QR global do shell e a ancora de CTA do tema continuam como conceitos separados.
7. O primeiro modo interativo nasce no manager preview; o wall publico continua passivo por padrao.
8. Posicao e escala persistidas usam coordenadas normalizadas pelo palco, nao pixels absolutos.
9. Persistencia de configuracao fica no backend do modulo `Wall`, nao em `localStorage`.
10. O overlay precisa respeitar safe areas e nunca pode escapar do palco util.
11. O primeiro corte de personalizacao e sem dependencias novas; `qrcode.react` continua suficiente.
12. `qr-code-styling` so entra no wall se houver necessidade real de skin premium ou export compartilhado com o editor universal.

---

## Ownership tecnico

## Backend

Ownership no modulo `Wall`.

Arquivos e pontos de integracao esperados:

```text
apps/api/app/Modules/Wall/
  Http/Requests/UpdateWallSettingsRequest.php
  Http/Requests/SimulateWallRequest.php
  Services/WallPayloadFactory.php
  Actions/ResetWallAction.php
  Models/EventWallSetting.php
apps/api/database/migrations/
packages/shared-types/src/wall.ts
```

## Frontend

Ownership no modulo `wall`.

Arquivos e pontos de integracao esperados:

```text
apps/web/src/modules/wall/
  wall-settings.ts
  components/manager/inspector/WallAppearanceTab.tsx
  components/manager/stage/WallPreviewCanvas.tsx
  player/components/WallPlayerRoot.tsx
  player/components/BrandingOverlay.tsx
  player/hooks/useStageGeometry.ts
  player/hooks/useQRDraggable.ts
  player/themes/puzzle/PuzzleLayout.tsx
packages/shared-types/src/wall.ts
```

Arquivos novos recomendados:

```text
apps/web/src/modules/wall/player/components/WallQrOverlay.tsx
apps/web/src/modules/wall/components/manager/stage/WallQrOverlayEditor.tsx
apps/web/src/modules/wall/player/hooks/useQrOverlayBounds.ts
apps/web/src/modules/wall/player/hooks/useQrOverlayDraft.ts
apps/api/tests/Feature/Wall/WallQrOverlaySettingsTest.php
apps/web/src/modules/wall/player/components/WallQrOverlay.test.tsx
apps/web/src/modules/wall/components/manager/stage/WallQrOverlayEditor.test.tsx
```

---

## Contrato recomendado de `qr_overlay`

## Regra de modelagem

`qr_overlay` deve representar apenas:

- configuracao visual do overlay;
- posicionamento;
- escala;
- copy curta;
- origem do conteudo.

Ele nao deve representar:

- CTA interno do tema;
- estado efemero do drag em andamento;
- permissao de operador;
- runtime local do player.

## Contrato compartilhado proposto

Adicionar em `packages/shared-types/src/wall.ts`:

```ts
export type WallQrOverlaySource = 'event_upload_url';
export type WallQrOverlayStylePreset = 'classic' | 'glass' | 'minimal';
export type WallQrOverlayTextMode = 'full' | 'compact' | 'hidden';
export type WallQrOverlayPositionMode = 'preset' | 'custom';
export type WallQrOverlayPresetPosition =
  | 'top-left'
  | 'top-right'
  | 'bottom-left'
  | 'bottom-right';

export interface WallQrOverlayConfig {
  config_version: 1;
  enabled: boolean;
  source: WallQrOverlaySource;
  style_preset: WallQrOverlayStylePreset;
  text_mode: WallQrOverlayTextMode;
  title: string | null;
  subtitle: string | null;
  position_mode: WallQrOverlayPositionMode;
  preset_position: WallQrOverlayPresetPosition;
  offset_x_pct: number | null;
  offset_y_pct: number | null;
  scale: number;
}
```

Adicionar em `WallSettings`:

```ts
export interface WallSettings {
  ...
  show_qr: boolean;
  qr_overlay: WallQrOverlayConfig;
  ...
}
```

## Leitura do contrato

- `config_version`
  - protege evolucoes futuras de schema;
  - evita repetir o erro de configs implicitas demais.
- `source`
  - na V1 trava em `event_upload_url`;
  - deixa o caminho pronto para futuras fontes sem abrir payload livre agora.
- `text_mode`
  - resolve a necessidade real de:
    - card completo;
    - card mais discreto;
    - QR sem texto.
- `position_mode`
  - `preset` cobre o caso mais simples e robusto;
  - `custom` e o resultado persistido do ajuste do operador.
- `offset_x_pct` e `offset_y_pct`
  - devem ser percentuais normalizados do palco;
  - isso preserva comportamento entre preview, desktop, TV e device 4K.
- `scale`
  - controla tamanho sem explodir em dezenas de campos de largura/altura;
  - deve ter clamp de produto no frontend e no backend.

## Faixas recomendadas

- `scale`: `0.75` a `1.75`
- `offset_x_pct`: `0` a `100`
- `offset_y_pct`: `0` a `100`

Observacao:

- esses valores nao autorizam o overlay a sair do palco;
- o renderer ainda precisa clamp interno por bounding box.

## Exemplo de payload persistido

```json
{
  "config_version": 1,
  "enabled": true,
  "source": "event_upload_url",
  "style_preset": "glass",
  "text_mode": "full",
  "title": "Envie sua foto",
  "subtitle": "Aponte a camera para entrar no upload do evento.",
  "position_mode": "preset",
  "preset_position": "bottom-right",
  "offset_x_pct": null,
  "offset_y_pct": null,
  "scale": 1
}
```

## Persistencia backend recomendada

Recomendacao objetiva:

- adicionar coluna JSON propria `qr_overlay` em `event_wall_settings`;
- nao reutilizar `theme_config`;
- nao empilhar isso em `instructions_text`;
- nao depender de `background_image_path` ou branding para carregar o contrato.

### Mudancas de backend recomendadas

Migration:

```text
apps/api/database/migrations/*_add_qr_overlay_to_event_wall_settings_table.php
```

Model:

- incluir `qr_overlay` em `$fillable`
- incluir `qr_overlay` em `$casts` como `array`
- criar resolver semantico, por exemplo:
  - `resolvedQrOverlay(): array`

Payload:

- `WallPayloadFactory::settings()` deve devolver `qr_overlay`
- `show_qr` continua sendo devolvido apenas como alias de compatibilidade na Fase 1

Request validation:

- `UpdateWallSettingsRequest`
- `SimulateWallRequest`

Campos a validar:

```text
qr_overlay
qr_overlay.config_version
qr_overlay.enabled
qr_overlay.source
qr_overlay.style_preset
qr_overlay.text_mode
qr_overlay.title
qr_overlay.subtitle
qr_overlay.position_mode
qr_overlay.preset_position
qr_overlay.offset_x_pct
qr_overlay.offset_y_pct
qr_overlay.scale
```

## Compatibilidade com `show_qr`

Para reduzir blast radius:

- Fase 1:
  - `qr_overlay.enabled` vira a fonte de verdade;
  - `show_qr` e espelhado a partir de `qr_overlay.enabled` no payload final;
  - requests antigas sem `qr_overlay` ainda podem chegar com `show_qr`.
- Fase 2:
  - manager e player deixam de depender de `show_qr`.
- Fase 3:
  - `show_qr` vira alias legado documentado;
  - deprecacao formal so depois da migracao completa.

---

## Personalizacao que entra na V1

Estas personalizacoes sao coerentes com o ROI e com a stack atual:

- ligar ou desligar o QR;
- trocar copy curta:
  - titulo;
  - subtitulo;
- trocar estilo:
  - `classic`
  - `glass`
  - `minimal`
- trocar modo de texto:
  - `full`
  - `compact`
  - `hidden`
- mudar posicao por preset;
- mover livremente no preview do manager;
- aumentar e diminuir com `+` e `-`;
- esconder com `x`;
- resetar para o preset original;
- persistir o resultado no backend.

## Personalizacao que nao entra na V1

- edicao livre do payload do QR;
- skins SVG arbitrarias com `qr-code-styling`;
- logo embutido no QR do wall;
- animacoes chamativas do proprio QR;
- multi-QR por tema;
- QR diferente por fase do wall;
- CTA de tema automatico puxando o mesmo contrato sem regra clara.

---

## Fases de execucao

## Fase 0 - Blindagem do contrato e compatibilidade

### Objetivo

Criar o schema `qr_overlay` sem quebrar o wall atual.

### Backend

- adicionar coluna `qr_overlay`
- criar normalizador default no model
- espelhar `show_qr -> qr_overlay.enabled` quando vier request antigo
- devolver `qr_overlay` em `WallPayloadFactory`
- manter `show_qr` no payload como alias temporario

### Frontend

- adicionar tipos novos em `shared-types`
- atualizar `ApiWallSettings` e utilitarios de clone/compare/payload
- garantir que `resolveManagedWallSettings()` preencha default de `qr_overlay`

### TDD obrigatorio antes de implementar

Backend:

- criar `WallQrOverlaySettingsTest.php`
  - valida default quando `qr_overlay` e `null`
  - valida compatibilidade com request legado usando `show_qr`
  - valida roundtrip `PATCH settings -> GET settings`

Frontend:

- expandir `wall-settings.test.ts`
  - clone preserva `qr_overlay`
  - compare detecta mudanca em `qr_overlay`
  - payload nao perde `qr_overlay`

### Criterios de aceite

- settings novas e antigas convivem sem quebra
- payload publico do wall devolve `qr_overlay`
- manager consegue editar draft sem perder o objeto

---

## Fase 1 - Paridade real entre preview e player

### Objetivo

Parar de tratar o preview como mundo paralelo e fazer preview e player consumirem o mesmo contrato.

### Backend

- nenhuma mudanca estrutural extra alem da Fase 0

### Frontend

- extrair o renderer atual para `WallQrOverlay`
- fazer `BrandingOverlay` consumir `qr_overlay`
- fazer `WallPreviewCanvas` consumir o mesmo componente e a mesma regra de fonte do QR
- eliminar `PREVIEW_QR_URL` fixa como baseline de produto

### Decisao importante desta fase

O preview ainda pode usar uma URL de exemplo quando o evento nao tiver `upload_url`, mas isso deve passar pelo mesmo `source resolver` do player, nao por constante solta escondida no preview.

### TDD obrigatorio antes de implementar

- `WallQrOverlay.test.tsx`
  - renderiza por `qr_overlay.enabled`
  - respeita `text_mode`
  - respeita `style_preset`
  - nao renderiza sem `qrUrl`
- atualizar `BrandingOverlay.test.tsx`
  - deixa de validar copy fixa hardcoded como unica trilha
- atualizar `wall-qr-overlay-characterization.test.ts`
  - preview e player usam a mesma semantica
  - `PREVIEW_QR_URL` fixa deixa de ser dependencia obrigatoria

### Criterios de aceite

- preview e live mostram o mesmo layout-base do QR
- copy configurada aparece igual nos dois lados
- o QR nao depende mais de constante fake solta no manager

---

## Fase 2 - Controles do manager e persistencia operacional

### Objetivo

Entregar o modo operador no manager preview, com controles claros e persistencia real.

### Frontend

- criar `WallQrOverlayEditor`
- adicionar affordances visiveis no preview:
  - `+`
  - `-`
  - `x`
  - `reset`
- habilitar drag apenas em modo operador do manager
- persistir resultado no draft `qr_overlay`
- clamp por bounding box do palco

### Backend

- nenhuma mudanca estrutural alem da persistencia ja criada

### Decisao tecnica importante

`useQRDraggable` nao deve ser reutilizado como esta.

Ele pode servir como referencia, mas a V1 precisa de:

- clamp real;
- coordenadas normalizadas;
- integracao com draft do manager;
- desligamento claro fora do modo operador.

### TDD obrigatorio antes de implementar

- `WallQrOverlayEditor.test.tsx`
  - `+` aumenta `scale` com clamp maximo
  - `-` reduz `scale` com clamp minimo
  - `x` desabilita `qr_overlay.enabled`
  - `reset` volta para preset default
  - drag atualiza `offset_x_pct` e `offset_y_pct`
  - drag nunca deixa o overlay escapar do palco
- atualizar `useQRDraggable.test.ts`
  - migrar a expectativa de "nao clampa" para o novo hook de bounds
- `WallPreviewCanvas.test.tsx`
  - modo operador mostra controles
  - modo normal nao mostra controles

### Criterios de aceite

- operador consegue ajustar o QR direto no preview
- salvar persiste a posicao e o tamanho
- reload do manager recompÃµe o overlay igual ao ultimo estado salvo

---

## Fase 3 - Endurecimento de produto e UX

### Objetivo

Fechar a semantica de produto do QR no wall para nao misturar overlay global com CTA de tema.

### Itens obrigatorios

- revisar copy default
- documentar no manager que o QR do shell e diferente da ancora do tema
- revisar `qr_prompt` do `puzzle`

### Decisao recomendada

Escolher uma das duas trilhas abaixo e travar no produto:

1. `qr_prompt` continua existindo, mas passa a ser renomeado para algo semanticamente correto:
   - `upload_prompt`
   - `event_prompt`
2. `qr_prompt` passa a renderizar QR real dentro do tema, o que e mais caro e deve ficar para depois.

Recomendacao deste plano:

- V1: renomear a semantica no produto e manter o QR real apenas como overlay global;
- V2: avaliar QR real dentro do tema se ainda fizer sentido.

### TDD obrigatorio antes de implementar

- atualizar `wall-qr-overlay-characterization.test.ts`
  - garantir que o puzzle nao seja vendido como QR real se continuar so textual
- criar teste do manager:
  - a UI diferencia `QR do telao` de `Ancora do tema`

### Criterios de aceite

- o operador entende onde mexe no QR real
- o produto nao chama texto de ancora de "QR" quando ele nao for QR

---

## Fase 4 - Modo operador no player ao vivo

### Objetivo

Avaliar, ja com a base estabilizada, se vale liberar ajuste no proprio wall ao vivo.

### Recomendacao

Nao ligar isso na primeira entrega.

Se entrar, deve entrar apenas com protecao explicita:

- query param protegido ou sessao autenticada;
- controles invisiveis no wall publico padrao;
- persistencia disparada por acao consciente do operador.

### TDD obrigatorio antes de implementar

- teste de gate:
  - player publico nao mostra controles
  - player em modo operador mostra controles
- teste de runtime:
  - drag nao interfere no reducer de playback

### Criterios de aceite

- publico nao enxerga affordances do editor
- operacao ao vivo nao quebra playback, captions, side thumbnails ou safe areas

---

## Bateria TDD consolidada

## Backend

Arquivos alvo:

- `apps/api/tests/Feature/Wall/WallQrOverlaySettingsTest.php`
- `apps/api/tests/Feature/Wall/PublicWallBootTest.php`
- `apps/api/tests/Feature/Wall/WallSimulationQrOverlayTest.php`

Casos minimos:

1. devolve `qr_overlay` default no boot e no settings show.
2. aceita `qr_overlay` completo no `PATCH`.
3. normaliza payload parcial com defaults faltantes.
4. request legado com `show_qr=false` desliga `qr_overlay.enabled`.
5. `ResetWallAction` recompÃµe default de `qr_overlay`.
6. `SimulateWallRequest` aceita `qr_overlay` e devolve preview coerente.

## Frontend

Arquivos alvo:

- `apps/web/src/modules/wall/wall-settings.test.ts`
- `apps/web/src/modules/wall/player/components/WallQrOverlay.test.tsx`
- `apps/web/src/modules/wall/player/components/BrandingOverlay.test.tsx`
- `apps/web/src/modules/wall/player/wall-qr-overlay-characterization.test.ts`
- `apps/web/src/modules/wall/components/manager/stage/WallQrOverlayEditor.test.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx`
- `apps/web/src/modules/wall/pages/EventWallManagerPage.test.tsx`

Casos minimos:

1. `qr_overlay` entra no draft e no payload final.
2. overlay respeita `enabled`, `text_mode`, `style_preset` e `scale`.
3. preview e player usam o mesmo componente base.
4. drag atualiza coordenadas normalizadas.
5. drag faz clamp real.
6. controles `+`, `-`, `x` e `reset` funcionam.
7. save otimista preserva `qr_overlay`.
8. reload do manager recompÃµe overlay salvo.
9. `puzzle` continua semanticamente separado do QR global.

## Comandos de validacao por rodada

Frontend:

```bash
cd apps/web
npm run test -- src/modules/wall/player/components/WallQrOverlay.test.tsx src/modules/wall/player/components/BrandingOverlay.test.tsx src/modules/wall/player/wall-qr-overlay-characterization.test.ts src/modules/wall/components/manager/stage/WallQrOverlayEditor.test.tsx src/modules/wall/components/manager/stage/WallPreviewCanvas.test.tsx src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/wall/wall-settings.test.ts
npm run type-check
```

Backend:

```bash
cd apps/api
php artisan test tests/Feature/Wall/WallQrOverlaySettingsTest.php tests/Feature/Wall/PublicWallBootTest.php tests/Feature/Wall/WallSimulationQrOverlayTest.php
```

---

## Ordem recomendada de implementacao

Se o escopo precisar ser defendido contra inflacao, a ordem correta fica:

1. contrato `qr_overlay` em `shared-types`;
2. persistencia backend + alias temporario de `show_qr`;
3. `WallQrOverlay` unico para preview e live;
4. ajustes do manager com copy, preset e `text_mode`;
5. drag, resize, hide e reset no preview;
6. limpeza da semantica do `puzzle`;
7. modo operador no player ao vivo apenas se ainda fizer sentido.

Pacote minimo que nao deve cair:

- contrato versionado;
- persistencia backend;
- preview/live parity;
- editor basico no manager;
- clamp real;
- TDD de roundtrip, preview e drag.

---

## Riscos reais e mitigacoes

### R1. Drift entre `show_qr` e `qr_overlay.enabled`

Mitigacao:

- resolver no backend com alias explicito;
- cobrir roundtrip em teste de feature.

### R2. Preview e player divergirem outra vez

Mitigacao:

- extrair `WallQrOverlay` compartilhado;
- eliminar renderer paralelo de preview.

### R3. Drag persistir posicao ruim em device diferente

Mitigacao:

- usar coordenadas normalizadas;
- clamp por bounds;
- opcao de reset.

### R4. Mistura entre QR global e CTA do tema continuar confusa

Mitigacao:

- separar nomes e superficies no manager;
- nao usar `qr_prompt` como nome de produto se ele nao renderiza QR real.

### R5. Escopo crescer para virar editor universal completo

Mitigacao:

- payload do QR continua travado em `event.upload_url`;
- nada de logo, skin arbitraria ou export premium nesta entrega.

---

## Definicao de pronto

Esta entrega so pode ser considerada pronta quando:

- `qr_overlay` existe como contrato em backend e frontend;
- o manager consegue ler, editar e salvar esse contrato;
- o preview usa o mesmo renderer base do player ao vivo;
- o QR respeita copy, preset, escala e posicao salvas;
- o drag no preview faz clamp real e persiste o resultado;
- `show_qr` deixa de ser dependencia principal do frontend;
- o produto diferencia claramente `QR do telao` de `ancora do tema`;
- a bateria TDD da trilha esta verde;
- `type-check` esta verde;
- a doc de analise continua coerente com o plano executado.

---

## Checklist executivo

- [ ] criar coluna `qr_overlay` no backend
- [ ] adicionar `WallQrOverlayConfig` em `packages/shared-types/src/wall.ts`
- [ ] expor `qr_overlay` em `WallPayloadFactory`
- [ ] validar `qr_overlay` em `UpdateWallSettingsRequest`
- [ ] validar `qr_overlay` em `SimulateWallRequest`
- [ ] manter alias temporario de `show_qr`
- [ ] extrair componente `WallQrOverlay`
- [ ] alinhar preview e player ao vivo
- [ ] criar editor do QR no manager preview
- [ ] implementar drag com clamp real
- [ ] persistir `scale`, `position_mode`, `offset_x_pct`, `offset_y_pct`
- [ ] adicionar `+`, `-`, `x` e `reset`
- [ ] revisar semantica do `puzzle`
- [ ] fechar bateria TDD backend/frontend

---

## Conclusao

O QR do wall nao precisa ser refeito do zero.

O que falta e fechar tres coisas que hoje estao soltas:

1. contrato de produto;
2. paridade real entre preview e player;
3. modo operador com persistencia confiavel.

O melhor caminho e incremental:

- primeiro virar `qr_overlay` em contrato real;
- depois consolidar renderer compartilhado;
- depois liberar ajuste no manager;
- e so por ultimo discutir se o wall ao vivo tambem vira superficie editavel.

Esse caminho reduz risco, fecha a semantica de produto e entrega a personalizacao que realmente faz falta sem transformar o QR do telao em mais um subsistema improvisado.
