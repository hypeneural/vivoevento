# Event Commercial Granularity Matrix

Estado validado em `2026-04-05`.

## Objetivo

Este documento consolida:

- a stack e a arquitetura comercial atual do Evento Vivo;
- a granularidade real hoje disponivel por plano, pacote, trial e grant administrativo;
- o que o super admin ja consegue fazer manualmente, inclusive sem pacote;
- o que ainda existe apenas como snapshot comercial, mas nao como enforcement operacional;
- a proposta de execucao para suportar limites vs ilimitado de forma consistente.

Este arquivo deve ser lido junto com:

- `docs/execution-plans/billing-subscriptions-execution-plan.md`
- `docs/execution-plans/event-whatsapp-commercial-execution-plan.md`
- `docs/execution-plans/whatsapp-zapi-webhook-execution-plan.md`
- `docs/architecture/event-media-intake-architecture.md`

## Evidencia validada

Analise reconciliada com codigo, contratos e testes.

Testes executados nesta rodada:

- `cd apps/api && php artisan test --filter=AdminQuickEventTest`
  - PASS, `6` testes, `111` assertions
- `cd apps/api && php artisan test --filter=EntitlementResolverServiceTest`
  - PASS, `6` testes, `37` assertions
- `cd apps/api && php artisan test --filter=EventCommercialStatusTest`
  - PASS, `5` testes, `33` assertions
- `cd apps/api && php artisan test --filter=PublicUploadTest`
  - PASS, `7` testes, `57` assertions
- `cd apps/api && php artisan test --filter=PublicTrialEventTest`
  - PASS, `2` testes, `21` assertions
- `cd apps/api && php artisan test --filter=AccessMatrixTest`
  - PASS, `4` testes, `37` assertions
- `cd apps/api && php artisan test --filter=EventIntakeChannelsTest`
  - PASS, `6` testes, `54` assertions
- `cd apps/api && php artisan test --filter=PublicUploadCommercialEnvelopeCharacterizationTest`
  - PASS, `4` testes, `21` assertions
- `cd apps/api && php artisan test --filter=EventCommercialEnvelopeCharacterizationTest`
  - PASS, `3` testes, `19` assertions
- `cd apps/api && php artisan test --filter=EventLifecycleCharacterizationTest`
  - PASS, `1` teste, `2` assertions
- `cd apps/api && php artisan test --filter=AdminQuickEventCommercialDefaultsCharacterizationTest`
  - PASS, `1` teste, `7` assertions

Bateria consolidada desta revisao final:

- os `11` arquivos de teste acima foram executados em uma unica rodada;
- resultado final: PASS, `45` testes, `399` assertions.

## Revisao em relacao ao resumo anterior

O resumo anterior ficou parcialmente defasado em dois pontos:

- `manual_override` sem pacote ja nao esta mais "incompleto"; hoje funciona e esta coberto por teste;
- o resolver ja nao materializa apenas modulos, branding e 3 limites; hoje ele tambem materializa um bloco `channels`, e o CRUD do evento ja usa esse bloco para validar parte da configuracao de intake.

O que continua correto no resumo anterior:

- a stack e boa;
- a granularidade declarativa esta a frente da granularidade operacional;
- o runtime ainda nao aplica de forma uniforme todas as capacidades comerciais;
- participantes unicos, janela temporal de intake, retencao real e IA por plano ainda nao estao fechados.

As duvidas que foram validadas nesta revisao:

- o upload publico ignora tanto o entitlement comercial quanto a existencia de `event_channel` ativo para `public_upload`;
- o evento ainda consegue habilitar `wall` e `play` acima do baseline comercial resolvido;
- `manual_override` sem pacote, quando omite limites, nao representa "ilimitado" de forma uniforme:
  - `retention_days` cai em `30`;
  - `max_photos` fica `null`.

## Veredito executivo

Hoje o sistema ja possui uma base comercial boa e relativamente flexivel:

- plano recorrente por organizacao;
- pacote avulso por evento;
- grant administrativo por evento;
- snapshot resolvido em `events.current_entitlements_json`;
- merge por prioridade e estrategia (`replace`, `expand`, `restrict`).

O ponto forte atual:

- o motor comercial por evento existe de verdade;
- `manual_override` sem pacote ja funciona;
- grants administrativos com snapshots diretos ja funcionam;
- canais de WhatsApp e upload publico ja entram no resolver de entitlements;
- o CRUD do evento ja conhece `intake_channels` e `intake_defaults`;
- o CRUD do evento ja valida grupos, WhatsApp direto, upload publico e modo de instancia contra os entitlements do evento;
- `event_channels` e `whatsapp_group_bindings` ja sao sincronizados na criacao/edicao do evento;
- trial publico ja cria grant e entitlement de evento.

O ponto fraco atual:

- varios limites ainda nao sao aplicados no runtime;
- o CRUD normal do evento ainda expoe configuracao operacional fora do envelope comercial para campos como moderacao, retencao e modulos;
- o `public_upload` publico ainda nao consulta os entitlements de canal nem de quota total;
- o `public_upload` publico tambem nao consulta a configuracao operacional de `event_channels`;
- nao existe quota de participantes unicos;
- nao existe janela "ativo por X horas/dias" aplicada em codigo;
- retencao existe como capacidade, mas eu nao encontrei purge automatico por idade;
- moderacao por IA ainda e configurada por evento, nao por entitlement do plano/pacote.

Em uma frase: o sistema ja consegue resolver entitlements por evento, mas ainda nao obedece todos eles nos fluxos operacionais.

## Stack e camadas do modelo

### Stack principal

| Camada | Stack |
| --- | --- |
| Backend | Laravel 12 + PHP 8.3 + PostgreSQL + Redis |
| Frontend | React 18 + TypeScript + Vite 5 + TailwindCSS 3 + shadcn/ui |
| Arquitetura comercial | `Plan` + `EventPackage` + `EventAccessGrant` + `current_entitlements_json` |
| Intake | upload publico + WhatsApp em evolucao para intake canonico por canal |

### Cadeia tecnica atual do evento

Hoje a cadeia mais importante do evento ficou assim:

1. create/update do evento persiste dados operacionais base;
2. `EventCommercialStatusService` recalcula `commercial_mode` e `current_entitlements_json`;
3. `SyncEventIntakeChannelsAction` usa esse snapshot resolvido para validar `intake_channels`;
4. se passar, sincroniza `event_channels`, `whatsapp_group_bindings` e defaults de instancia;
5. `EventResource` devolve `current_entitlements`, `intake_defaults` e `intake_channels`.

Isso da uma robustez real para a configuracao dos canais. O que ainda nao esta fechado e a camada seguinte: a borda de ingestao publica e o intake WhatsApp consumirem essas mesmas regras de forma uniforme.

Leitura arquitetural importante:

- hoje o sistema ja consegue governar a configuracao de canais com base no entitlement resolvido;
- mas ainda existem campos operacionais do evento que escapam desse envelope, como `modules`, `moderation_mode` e `retention_days`;
- por isso, a arquitetura atual nao tem um unico "source of truth" operacional completo; ela tem um source of truth comercial forte e uma camada operacional ainda parcialmente independente.

### Camadas de controle comercial

| Camada | Escopo | Estado atual | Observacao |
| --- | --- | --- | --- |
| Assinatura (`plans`) | organizacao | funcionando | baseline recorrente da conta |
| Pacote avulso (`event_packages`) | evento | funcionando | compra publica e grant admin com pacote |
| Grant (`event_access_grants`) | evento | funcionando | suporta `trial`, `bonus`, `manual_override` |
| Snapshot resolvido | evento | funcionando | salvo em `events.current_entitlements_json` |
| Configuracao operacional do evento | evento | parcial | intake de canais ja consulta entitlements; moderacao, retencao, modulos e upload publico ainda podem divergir |

### Prioridade e merge do entitlement

| Fonte | `source_type` | Prioridade default | Papel esperado |
| --- | --- | --- | --- |
| Trial | `trial` | `100` | baseline reduzido / onboarding leve |
| Assinatura | `subscription` | `500` | baseline da conta |
| Compra avulsa | `event_purchase` | `800` | baseline do evento pago |
| Bonus | `bonus` | `900` | cortesia baseada em pacote ou promocao |
| Override manual | `manual_override` | `1000` | excecao granular por evento |

Estrategias suportadas hoje:

- `replace`: substitui a referencia anterior;
- `expand`: amplia capacidade;
- `restrict`: reduz capacidade.

## O que existe hoje por fonte comercial

### 1. Matriz atual dos planos da organizacao

Catalogo semeado hoje:

| Plano | Preco default | Eventos ativos | Retencao | Wall | Play | WhatsApp | White label |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Starter | BRL 49,00/mes | `3` | `30 dias` | sim | nao | sim, flag legado `channels.whatsapp` | nao |
| Professional | BRL 99,00/mes | `10` | `90 dias` | sim | sim | sim, flag legado `channels.whatsapp` | nao |
| Business | BRL 199,00/mes | `50` | `180 dias` | sim | sim | sim, flag legado `channels.whatsapp` | sim |

Notas:

- o catalogo recorrente hoje nao semeia `media.max_photos`;
- o catalogo recorrente hoje nao semeia limite de participantes;
- o catalogo recorrente hoje nao semeia moderacao IA;
- o catalogo recorrente hoje nao semeia granularidade fina de canais;
- o flag legado `channels.whatsapp=true` hoje ja e mapeado pelo resolver para capacidades default de grupos, DM e instancia compartilhada.

### 2. Matriz atual dos pacotes por evento

Catalogo semeado hoje:

| Pacote | Preco default | Live | Hub | Wall | Play | Retencao | Max fotos | Watermark | White label |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Essencial | BRL 99,00 | sim | sim | nao | nao | `30 dias` | `150` | nao | nao |
| Interativo | BRL 199,00 | sim | sim | sim | nao | `90 dias` | `400` | nao | nao |
| Premium | BRL 299,00 | sim | sim | sim | sim | `180 dias` | `800` | nao | sim |

Notas:

- `live` nao aparece explicitamente no seed do pacote, mas o snapshot builder assume `live=true` por default;
- `hub` hoje tambem tende a nascer habilitado no snapshot builder;
- pacote hoje e a camada mais clara para vender `max_photos` por evento.

### 3. Trial publico atual

O trial publico ja nasce com grant proprio:

| Modo | Live | Hub | Wall | Play | Retencao | Max eventos ativos | Max fotos | Watermark |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `trial` | sim | sim | nao | nao | `7 dias` | `1` | `20` | sim |

Notas:

- o trial e um grant de evento, nao apenas uma flag de UX;
- ele ja persiste `commercial_mode=trial` e snapshot resolvido no evento.

### 4. Matriz atual de grants administrativos

| Caso | `source_type` | Precisa de pacote | Aceita snapshots diretos | Estado atual |
| --- | --- | --- | --- | --- |
| Cortesia com pacote | `bonus` | sim | nao como caminho principal | funcionando |
| Override com pacote | `manual_override` | nao, mas pode usar | sim | funcionando |
| Override sem pacote | `manual_override` | nao | sim, obrigatorio quando nao ha pacote | funcionando |

Conclusao importante:

- "evento bonus sem plano/pacote" ja e possivel tecnicamente hoje;
- no codigo atual, esse caso entra por `manual_override`, nao por `bonus`;
- `bonus` hoje e semantica de cortesia vinculada a pacote;
- `manual_override` hoje e o verdadeiro mecanismo de evento livre/granular sem pacote.

## O que o super admin ja consegue fazer hoje

O fluxo `admin/quick-events` ja permite:

- criar evento para organizacao nova ou existente;
- reutilizar usuario responsavel existente;
- criar grant `bonus` com pacote;
- criar grant `manual_override` com pacote;
- criar grant `manual_override` sem pacote;
- informar `features` e `limits` diretos no payload;
- controlar `merge_strategy`, `starts_at`, `ends_at`, `notes`, `reason` e `origin`;
- escolher `event.moderation_mode` no momento da criacao.

### Exemplo do que ja funciona no `manual_override` sem pacote

Hoje ja e possivel setar diretamente:

- `wall.enabled`
- `white_label.enabled`
- `channels.whatsapp_groups.enabled`
- `channels.whatsapp_groups.max`
- `channels.whatsapp_direct.enabled`
- `channels.public_upload.enabled`
- `channels.telegram.enabled`
- `channels.blacklist.enabled`
- `channels.whatsapp.shared_instance.enabled`
- `channels.whatsapp.dedicated_instance.enabled`
- `channels.whatsapp.dedicated_instance.max_per_event`
- `channels.whatsapp.feedback.reject_reply.enabled`
- `channels.whatsapp.feedback.reject_reply.message`
- `media.retention_days`
- `media.max_photos`

Isso ja esta coberto por teste.

### 5. Intake channels no CRUD do evento

Hoje o evento ja suporta no contrato de create/update:

- `intake_defaults.whatsapp_instance_id`
- `intake_defaults.whatsapp_instance_mode`
- `intake_channels.whatsapp_groups.enabled`
- `intake_channels.whatsapp_groups.groups`
- `intake_channels.whatsapp_direct.enabled`
- `intake_channels.whatsapp_direct.media_inbox_code`
- `intake_channels.whatsapp_direct.session_ttl_minutes`
- `intake_channels.public_upload.enabled`
- `intake_channels.telegram.enabled`

Robustez ja validada:

- cria e devolve `intake_channels` no payload do evento;
- sincroniza `event_channels`;
- sincroniza `whatsapp_group_bindings`;
- bloqueia grupos acima do limite comercial;
- bloqueia `whatsapp_direct` e `public_upload` quando o entitlement nao permite;
- bloqueia uso de instancia dedicada quando o entitlement nao permite ou quando a instancia ja esta presa a outro evento.

Conclusao:

- o sistema ja possui enforcement de configuracao para canais;
- o que ainda falta e enforcement uniforme na ingestao real desses canais.

Observacao decisiva:

- o fato de `intake_channels.public_upload.enabled=false` remover o canal configurado nao impede o endpoint publico de continuar disponivel;
- hoje o endpoint publico depende de `upload_slug`, modulo `live` e status ativo, nao da presenca do canal em `event_channels`.

## Matriz de granularidade atual por capacidade

### Leitura rapida

| Capacidade | Existe no catalogo/grant | Resolvida no evento | Enforced no CRUD/config | Enforced na ingestao/runtime | Observacao |
| --- | --- | --- | --- | --- | --- |
| `modules.live/wall/play/hub` | sim/parcial | sim | nao | parcial | evento ainda pode habilitar modulos acima do baseline comercial resolvido |
| `media.max_photos` | sim | sim | nao | nao de forma consistente | upload publico nao trava por limite total |
| `media.retention_days` | sim | sim | nao | parcial | existe como campo/capacidade, mas sem purge automatico identificado |
| `events.max_active` | sim | sim na conta | parcial | parcial | aparece na matriz de acesso, mas nao e o foco do intake |
| `channels.whatsapp_groups.enabled` | sim | sim | sim | parcial | ja bloqueia configuracao no CRUD; intake final ainda e parcial |
| `channels.whatsapp_groups.max` | sim | sim | sim | parcial | ja bloqueia excesso no CRUD; intake final ainda e parcial |
| `channels.whatsapp_direct.enabled` | sim | sim | sim | parcial | ja bloqueia configuracao no CRUD; sessao/intake ainda nao esta completo |
| `channels.public_upload.enabled` | sim | sim | sim | nao | CRUD bloqueia configuracao; endpoint publico ainda olha `live`, nao o entitlement |
| `channels.telegram.enabled` | sim | sim | sim | nao | sem runtime efetivo |
| `channels.blacklist.enabled` | sim | sim | nao | parcial/futuro | importante para intake WhatsApp |
| `channels.whatsapp.shared_instance.enabled` | sim | sim | sim | parcial | ja bloqueia configuracao no CRUD |
| `channels.whatsapp.dedicated_instance.enabled` | sim | sim | sim | parcial | ja bloqueia configuracao no CRUD; falta uso uniforme na ingestao |
| `channels.whatsapp.dedicated_instance.max_per_event` | sim | sim | sim | parcial | ja participa da validacao de configuracao |
| limite de participantes unicos | nao | nao | nao | nao | nao existe entitlement hoje |
| janela ativa por horas/dias | nao | nao | nao | nao | hoje `isActive()` depende de status manual |
| IA por plano/pacote | nao | nao | nao | nao | `moderation_mode=ai` e escolha operacional do evento |

### Detalhamento por tema

#### 1. Limite total de uploads de midia

Estado atual:

- existe como `media.max_photos`;
- ja aparece em pacote, trial, grant e snapshot resolvido;
- o detalhe do evento ja consegue mostrar esse valor;
- o upload publico hoje nao consulta esse limite para bloquear novos envios.
- isso foi validado por teste de caracterizacao.

Leitura pratica:

- comercialmente, a capacidade existe;
- operacionalmente, ainda nao e um gate hard-stop no intake publico.

#### 2. Limite de participantes unicos

Estado atual:

- nao existe `participants.max_*` no catalogo, grant ou resolver;
- o WhatsApp ja entrega identidade suficiente para isso:
  - `participantPhone`
  - `participantLid`
- mas ainda nao existe quota comercial por participante;
- o upload publico atual nao possui identidade forte do participante, apenas `sender_name`.

Conclusao:

- para WhatsApp, o sistema ja esta perto de conseguir contar participantes;
- para upload publico, ainda nao existe identidade suficiente para quota de participantes.

#### 3. Retencao de 7 dias, 3 meses, 1 ano ou vitalicio

Estado atual:

- `retention_days` existe no evento e no entitlement;
- pacotes e planos ja semeiam `30`, `90`, `180`;
- trial usa `7`;
- o CRUD normal do evento hoje aceita `1..365`;
- o campo bruto do evento ainda pode divergir do limite comercial resolvido;
- eu nao encontrei purge automatico por idade da midia do evento;
- "vitalicio" nao esta modelado de forma limpa hoje.
- isso foi validado por teste de caracterizacao.

Nuance importante:

- no `manual_override` sem pacote, omitir `retention_days` nao significa ilimitado;
- hoje isso faz o evento cair no default operacional de `30 dias`.

Conclusao:

- retencao hoje e capacidade declarada;
- ainda nao esta fechada como politica de lifecycle;
- o modelo atual do CRUD normal nao representa bem "sem limite".

#### 4. Evento ativo por X horas ou X dias

Estado atual:

- nao existe entitlement proprio para janela de intake;
- `starts_at` e `ends_at` existem, mas `isActive()` ainda depende de `status`;
- o upload publico fecha quando o evento nao esta `active`, nao por janela relativa.
- isso foi validado por teste de caracterizacao.

Conclusao:

- hoje o sistema trabalha com ativacao manual;
- nao existe ainda "ativo por 5 dias apos o evento" em runtime.

#### 5. Moderacao por IA baseada em plano

Estado atual:

- `moderation_mode` do evento aceita `none`, `manual`, `ai`;
- o admin quick event tambem aceita `event.moderation_mode`;
- a pipeline de safety so roda quando o evento esta em `ai` e as settings do modulo estao habilitadas;
- nao existe hoje um entitlement comercial do tipo `moderation.ai.enabled`.
- a atualizacao do evento ainda permite mudar para `ai` sem gate comercial.
- isso foi validado por teste de caracterizacao.

Conclusao:

- IA hoje e opcao operacional do evento;
- nao e gateada por plano, pacote ou grant.

#### 6. Modulos do evento vs baseline comercial

Estado atual:

- o resolver materializa `modules` no snapshot comercial;
- o CRUD do evento ainda permite ligar `wall` e `play` mesmo quando o baseline comercial resolvido os mantem desligados;
- isso gera divergencia entre `event_modules` reais e `current_entitlements_json.modules`;
- isso foi validado por teste de caracterizacao.

Conclusao:

- hoje `current_entitlements_json.modules` ainda nao funciona como teto operacional rigido;
- por isso, modulos precisam entrar explicitamente na proxima fase de endurecimento do envelope comercial.

## Onde o runtime ainda diverge do modelo comercial

### Divergencia 1. `current_entitlements_json` existe, mas o consumo ainda e desigual por camada

Exemplos:

- o CRUD do evento ja usa os entitlements para limitar `intake_channels`;
- `public_upload` nao valida `channels.public_upload.enabled`;
- `public_upload` nao bloqueia por `media.max_photos`;
- `public_upload` nao exige `event_channel` ativo para funcionar;
- a configuracao de modulos ainda nao e gateada pelo snapshot comercial;
- a configuracao de moderacao e retencao ainda nao e gateada pelo envelope comercial;
- a UI do detalhe do evento ja le parte do snapshot resolvido, mas ainda nao e a fonte unica para tudo.

### Divergencia 2. Campo operacional cru ainda contorna o modelo comercial

Hoje o evento ainda pode carregar:

- `moderation_mode` cru;
- `retention_days` cru;
- modulos no proprio evento.

Isso e util para operacao, mas perigoso sem regra clara de envelope:

- evento nao deveria conseguir expandir alem do que o entitlement permite;
- evento deveria poder, no maximo, restringir o que a camada comercial liberou.

Observacao importante:

- isso ja nao vale integralmente para `intake_channels`, porque essa parte ja ganhou enforcement.

### Divergencia 3. "Ilimitado" ainda nao esta padronizado

Hoje:

- `max_photos` pode ficar ausente e funcionar como "nao definido";
- `retention_days` cai em default operacional do evento;
- o payload manual usa `Arr::dot(...)` e filtra `null`, o que dificulta representar "ilimitado" de forma explicita.

Leitura atual de semantica:

- omitir `max_photos` em grant manual hoje tende a resultar em "nao definido";
- omitir `retention_days` em grant manual hoje tende a resultar em `30 dias`, nao em ilimitado;
- portanto, a semantica atual de ausencia de valor ja e inconsistente o bastante para impedir que "ilimitado" seja tratado como contrato seguro.

Conclusao:

- o conceito de ilimitado precisa virar contrato explicito, nao apenas ausencia de valor.

## Matriz recomendada para limite vs ilimitado

### Regra recomendada

Para qualquer capacidade negociavel, separar:

- o valor numerico, quando houver limite;
- o modo da capacidade, para evitar ambiguidade.

### Contrato recomendado

| Capacidade | Chave recomendada | Tipo | Semantica |
| --- | --- | --- | --- |
| total de fotos | `media.max_photos` + `media.max_photos_unlimited` | `int` + `bool` | ou limitado, ou ilimitado |
| participantes unicos | `participants.max_unique` + `participants.max_unique_unlimited` | `int` + `bool` | ou limitado, ou ilimitado |
| retencao | `media.retention_days` + `media.retention_lifetime` | `int` + `bool` | ou X dias, ou vitalicio |
| janela de intake | `intake.window_hours` + `intake.window_unlimited` | `int` + `bool` | ou X horas/dias, ou sem corte automatico |
| IA liberada | `moderation.ai.enabled` | `bool` | capability comercial |
| IA obrigatoria | `moderation.ai.required` | `bool` | opcional para planos premium/enterprise |

Motivo para esse desenho:

- `null` hoje nao e um bom contrato para payload administrativo;
- o sistema ja trabalha melhor com flat feature flags booleanas e inteiras;
- isso evita misturar "nao informado" com "ilimitado".

## Matriz recomendada de responsabilidades

| Camada | Pode definir | Pode restringir | Pode expandir | Observacao |
| --- | --- | --- | --- | --- |
| Plano da organizacao | baseline da conta | sim | nao | recorrente |
| Pacote do evento | baseline do evento pago | sim | nao | avulso |
| Grant admin `bonus` | adicionar cortesia controlada | sim | sim, se `expand` | recomendado para cortesias padronizadas |
| Grant admin `manual_override` | qualquer excecao auditada | sim | sim | recomendado para evento bonus sem pacote |
| Evento no CRUD | configuracao operacional dentro do envelope | sim | nao | intake de canais ja segue isso; modulos, moderacao e retencao ainda precisam convergir |

Regra recomendada:

- plano/pacote/grant definem o teto;
- o CRUD do evento escolhe apenas a configuracao efetiva dentro desse teto;
- operador do evento nunca expande capacidade comercial;
- super admin so expande via grant auditado.

## Evento bonus sem plano/pacote

### Estado atual

Ja funciona, mas pelo caminho:

- `source_type=manual_override`
- `package_id=null`
- `features` e `limits` diretos

### Recomendacao de negocio

Padronizar a semantica assim:

- `bonus`
  - uso quando a cortesia reaproveita um pacote conhecido;
  - bom para auditoria comercial e campanhas;
- `manual_override`
  - uso quando o evento e excepcional e granular;
  - melhor caminho para "evento bonus livre" sem pacote.

Se o produto quiser chamar isso de "bonus" tambem na interface:

- manter `manual_override` no backend;
- expor no frontend um rotulo comercial como `bonus_manual`;
- ou relaxar o request de `bonus` no futuro, aceitando snapshots diretos.

Recomendacao de curto prazo:

- nao mudar a semantica do backend agora;
- usar `manual_override` como o mecanismo oficial do super admin para evento livre/gratuito sem pacote.

## Identidade do participante para quotas futuras

Se a plataforma quiser limitar participantes unicos por evento, o contrato precisa ser por canal.

| Canal | Identidade atual disponivel | Serve para quota? | Observacao |
| --- | --- | --- | --- |
| WhatsApp grupo | `participantLid`, `participantPhone` | sim | melhor base para contagem |
| WhatsApp direto | `phone` / `chatLid` | sim | suficiente para quota |
| Upload publico | `sender_name` | nao | precisa telefone, OTP ou sessao |
| Telegram | futuro | ainda nao | definir depois |

Recomendacao:

- para WhatsApp: usar chave canonica de participante derivada de `participantLid`, com fallback para `participantPhone`;
- para upload publico: so vender quota por participante quando existir identificacao forte do convidado.

## Execucao recomendada em fases

### Fase 0. Fechar catalogo canonico de entitlements

Objetivo:

- congelar as chaves negociaveis;
- definir quais suportam limitado vs ilimitado;
- separar capacidade comercial de configuracao operacional.

Saida obrigatoria:

- tabela oficial de keys;
- semantica oficial de "unlimited";
- convenio para `bonus` vs `manual_override`.

### Fase 1. Introduzir envelope operacional do evento

Objetivo:

- impedir que o CRUD normal do evento expanda alem do entitlement em todos os campos, nao apenas nos canais.

Regras:

- `moderation_mode=ai` so se `moderation.ai.enabled=true`;
- `retention_days` local so pode ser menor ou igual ao entitlement;
- `modules` locais so podem desligar, nunca ligar algo nao contratado;
- quando existir `max_photos`, um cap local menor pode ser aceito.

Nota:

- `intake_channels` ja deu esse primeiro passo e pode servir de referencia de implementacao.
- os testes de caracterizacao novos mostram que `modules`, `moderation_mode` e `retention_days` sao os proximos alvos naturais desse endurecimento.

### Fase 2. Criar um guard central de intake

Objetivo:

- todos os canais consultam a mesma regra antes de aceitar novas midias.

Servico recomendado:

- `EventIntakeGuardService`

Responsavel por validar:

- evento ativo;
- janela de intake;
- canal habilitado;
- canal operacional efetivamente configurado no evento;
- quota de fotos;
- quota de participantes;
- blacklist;
- instancia compartilhada/dedicada;
- regras do canal.

Chamadores obrigatorios:

- `PublicUploadController`
- resolucao de contexto WhatsApp
- bindings de grupo
- abertura de sessao DM

### Fase 3. Fechar quota de participantes

Objetivo:

- criar limite de participantes unicos por evento.

Recomendacao:

- persistir uma chave canonica por participante e por canal;
- criar contador agregado por evento;
- bloquear novos participantes quando a quota estourar;
- nao bloquear novas midias de quem ja esta dentro da quota, salvo regra comercial diferente.

### Fase 4. Fechar retencao como lifecycle real

Objetivo:

- transformar `retention_days` em comportamento real.

Recomendacao:

- job recorrente de expiracao por evento;
- politica clara de soft delete vs hard delete;
- limpeza de variantes e artefatos;
- registrar motivo de expiracao em auditoria.

### Fase 5. Implementar janela "ativo por X horas/dias"

V1 recomendada:

- basear apenas em `starts_at`;
- usar `intake.window_hours`;
- sem heuristica por atividade de convidado.

V2 futura:

- opcionalmente abrir intake pela primeira atividade real do convidado.

### Fase 6. Gatear IA por entitlement

Objetivo:

- plano/pacote/grant liberam a capacidade;
- evento escolhe se usa ou nao, dentro do que foi contratado.

Regra recomendada:

- `moderation.ai.enabled=false` impede salvar evento em `ai`;
- `moderation.ai.required=true` impede desligar IA naquele evento.

## Mapa tecnico de execucao recomendado

### Backend por etapa

| Etapa | Alvo tecnico principal | Mudanca recomendada | Teste que deve virar gate |
| --- | --- | --- | --- |
| envelope operacional do evento | `Events/Actions/CreateEventAction.php`, `Events/Actions/UpdateEventAction.php`, `Events/Http/Requests/StoreEventRequest.php`, `Events/Http/Requests/UpdateEventRequest.php`, novo `Events/Support/EventOperationalEnvelopeService.php` | usar o entitlement resolvido como teto para `modules`, `moderation_mode`, `retention_days` e qualquer cap local de `max_photos` | `EventCommercialEnvelopeCharacterizationTest` |
| guard central de intake | novo `Shared/Support/EventIntakeGuardService.php`, `InboundMedia/Http/Controllers/PublicUploadController.php`, `WhatsApp/Services/WhatsAppInboundEventContextResolver.php`, `WhatsApp/Listeners/RouteInboundToMediaPipeline.php` | validar status, janela, canal comercial, canal operacional configurado, quota total e regras por canal antes de aceitar intake | `PublicUploadCommercialEnvelopeCharacterizationTest` |
| participantes unicos | novo agregado `event_participants` em `InboundMedia`, com model, migration e service de contagem canonica por canal | registrar `(event_id, channel, participant_key)` e bloquear novos participantes acima da quota sem bloquear quem ja esta dentro | novos testes de quota de participantes |
| retencao real | `MediaProcessing`, novo job de expiracao, `apps/api/routes/console.php` | transformar `retention_days` em purge real de midia e artefatos, com auditoria | novos testes de lifecycle |
| janela de intake | `EventIntakeGuardService`, `Events/Models/Event.php`, migration para marcador operacional se necessario | V1 por `starts_at` + `intake.window_hours`; V2 opcional por primeira atividade real | `EventLifecycleCharacterizationTest` |
| IA por entitlement | `Billing/Services/EntitlementResolverService.php`, `Events/Support/EventOperationalEnvelopeService.php`, `ContentModeration` | materializar `moderation.ai.enabled/required` e gatear salvamento/configuracao | novos testes de moderacao comercial |

### Frontend e painel

| Area | Arquivos alvo | Ajuste recomendado |
| --- | --- | --- |
| editor do evento | `apps/web/src/modules/events/components/EventEditorPage.tsx`, `apps/web/src/modules/events/types.ts` | renderizar os campos operacionais ja presos ao envelope efetivo; desabilitar expansao acima do entitlement |
| detalhe do evento | `apps/web/src/modules/events/EventDetailPage.tsx` | mostrar com clareza o que veio do entitlement resolvido vs configuracao efetiva do evento |
| super admin / grant manual | telas de billing/admin quick event | explicitar a semantica `limitado` vs `ilimitado`, sem depender de `null` |

### Sequencia recomendada de PRs

1. PR1: endurecer envelope operacional do evento para `modules`, `moderation_mode` e `retention_days`.
2. PR2: introduzir `EventIntakeGuardService` e ligar primeiro no `public_upload`.
3. PR3: ligar o mesmo guard na resolucao de contexto WhatsApp e nas sessoes DM.
4. PR4: fechar quota de participantes unicos por canal.
5. PR5: implementar retencao real por job agendado.
6. PR6: implementar janela de intake por horas/dias.
7. PR7: gatear IA por entitlement e alinhar a UI administrativa.

## TDD recomendado para fechar a evolucao

Nota:

- os testes de caracterizacao criados nesta revisao descrevem o comportamento atual, inclusive os gaps;
- conforme cada fase entrar, eles devem ser convertidos de "aceita mesmo assim" para "bloqueia corretamente".

### Billing e entitlements

- teste de grant admin com `manual_override` limitado;
- teste de grant admin com `manual_override` ilimitado;
- teste de merge entre baseline e override restritivo;
- teste de merge entre baseline e override expansivo.

### Evento e CRUD

- teste impedindo `moderation_mode=ai` sem entitlement;
- teste impedindo retencao acima do entitlement;
- teste impedindo habilitar canal nao contratado;
- teste aceitando configuracao mais restritiva que o entitlement.

### Intake

- teste de upload publico bloqueado por `channels.public_upload.enabled=false`;
- teste de upload publico bloqueado por `media.max_photos`;
- teste de grupo bloqueado por `channels.whatsapp_groups.max`;
- teste de DM bloqueado por canal nao habilitado;
- teste de bloqueio por participante acima da quota.

### Lifecycle

- teste de expiracao por retencao;
- teste de evento fora da janela de intake;
- teste de evento ainda ativo dentro da janela.

### Testes de caracterizacao ja criados nesta revisao

- upload publico continua aceitando envio mesmo com `channels.public_upload.enabled=false`;
- pagina publica de upload continua disponivel mesmo sem `event_channel` ativo de upload;
- upload publico continua aceitando envio mesmo apos atingir `max_photos`;
- upload publico continua aceitando envio mesmo sem `event_channel` ativo de upload;
- evento ainda aceita `retention_days` bruto acima do entitlement resolvido;
- evento ainda aceita `moderation_mode=ai` sem entitlement comercial;
- evento ainda aceita ligar modulos acima do baseline comercial resolvido;
- `isActive()` continua dependente apenas de status, nao de datas;
- `manual_override` sem pacote usa default de `30 dias` quando `retention_days` e omitido, enquanto `max_photos` fica sem definicao.

## Decisoes recomendadas para produto

### Decisao 1. O evento bonus sem pacote deve continuar existindo?

Minha recomendacao:

- sim;
- usar `manual_override` como mecanismo tecnico;
- tratar isso como ferramenta oficial do super admin.

### Decisao 2. "Ilimitado" deve ser ausencia de valor ou flag explicita?

Minha recomendacao:

- flag explicita;
- ausencia de valor deve significar "nao resolvido" ou "nao informado", nunca "ilimitado".

### Decisao 3. O evento pode escolher menos do que o contratado?

Minha recomendacao:

- sim;
- evento pode restringir localmente;
- evento nao pode expandir.

### Decisao 4. Participantes devem contar por pessoa unica ou por remetente unico por canal?

Minha recomendacao:

- contar por remetente unico canonico por canal;
- para WhatsApp, usar `participantLid` com fallback;
- para upload publico, so vender essa regra quando houver identidade forte.

## Resumo final

O estado atual ja suporta:

- assinatura por organizacao;
- pacote por evento;
- trial por evento;
- grant administrativo com pacote;
- grant administrativo sem pacote;
- snapshot resolvido por evento;
- boa base para canais e quotas de canal;
- enforcement de configuracao de canais no CRUD do evento.

O estado atual ainda nao suporta bem:

- participantes unicos por evento;
- vitalicio de forma canonica;
- janela de intake por horas/dias;
- IA por plano;
- enforcement unificado dos limites em todos os canais.

E hoje ainda carrega tres assimetrias importantes:

- `event_channels` ja governa a configuracao, mas ainda nao governa a borda do upload publico;
- `current_entitlements_json.modules` ainda nao impede que os modulos reais do evento sejam expandidos;
- ausencia de valor ainda nao e um contrato confiavel para representar "ilimitado".

Portanto, a melhor leitura hoje e:

- o motor comercial ja existe;
- o evento bonus manual sem pacote ja existe;
- parte importante do intake ja saiu do papel no CRUD de canais;
- a proxima etapa de maturidade nao e criar mais catalogo;
- a proxima etapa e transformar entitlement resolvido em regra operacional obrigatoria em toda a ingestao, no lifecycle e no envelope completo do evento.
