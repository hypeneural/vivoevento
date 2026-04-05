# Billing And Subscriptions Discovery

## Objetivo

Este documento consolida:

- o estado real de `plans`, `subscriptions` e compras avulsas hoje;
- a logica atual de vinculo entre organizacao, usuario, cliente e evento;
- os gaps tecnicos e de produto antes de tratar billing como modulo confiavel;
- a arquitetura recomendada para suportar assinatura recorrente e pacote avulso sem ambiguidades.

Documento complementar:

- ver `docs/architecture/billing-subscriptions-execution-plan.md` para a ordem detalhada de execucao, com tasks, subtasks, dependencias, criterios de aceite e primeira entrega recomendada.

Contexto de produto assumido:

- a plataforma atende operacoes B2B, com a organizacao parceira como conta principal;
- cada evento pertence a uma organizacao;
- o produto tende a precisar de um modelo hibrido: plano recorrente da conta + extras ou pacotes por evento;
- modulos como `wall`, `play`, `hub` e limites operacionais precisam ser habilitados por entitlements claros, nao por regras espalhadas.

## Status Atualizado Em 2026-04-03

Esta discovery foi reconciliada com o estado real do codigo em 2026-04-03.

Implementado na base hoje:

- `direct_customer` ja existe em `OrganizationType`;
- o onboarding OTP ja aceita jornada e nao termina obrigatoriamente em `/plans`;
- `events` ja possuem `commercial_mode` e `current_entitlements_json`;
- `GET /api/v1/events/{id}/commercial-status` ja existe;
- `event_access_grants` ja existe como motor de ativacao do evento;
- `EntitlementResolverService` e `OrganizationEntitlementResolverService` ja existem;
- `MeResource` e `/access/matrix` ja consomem entitlements resolvidos;
- `event_packages`, `event_package_prices` e `event_package_features` ja existem;
- o catalogo publico e autenticado de `event_packages` ja esta exposto por API.
- `event_purchases` ja aceita `package_id` e convive com `plan_id` legado;
- `commercial-status`, resolver de entitlements e historico de billing ja leem compra avulsa nova e legada.
- `POST /api/v1/public/trial-events` ja existe com sessao leve, criando usuario, organizacao, evento e grant `trial` no mesmo fluxo.
- `POST /api/v1/public/event-checkouts` ja existe em versao simplificada, criando usuario leve, organizacao `direct_customer`, evento e `billing_order` preliminar.
- `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm` ja converte o checkout simplificado em `event_purchase` e grant `event_purchase`.
- `POST /api/v1/admin/quick-events` ja existe para criacao rapida assistida com grant `bonus` ou `manual_override`.
- `billing_orders` e `billing_order_items` ja existem em versao minima para sustentar a jornada event-first.
- `payments` e `invoices` minimos ja existem no modulo de billing.
- `GET /api/v1/billing/invoices` ja devolve invoices reais baseadas em `billing_orders`.
- `POST /api/v1/billing/checkout` ja cria order, payment e invoice usando `plan_prices`.
- `POST /api/v1/billing/subscription/cancel` ja existe para cancelar a assinatura da conta autenticada.
- `BillingGatewayInterface`, `BillingGatewayManager` e `ManualBillingGateway` ja existem como camada inicial de provider.
- `billing_gateway_events` ja persiste webhook, idempotencia e resultado processado.
- `POST /api/v1/webhooks/billing/{provider}` ja existe para processar eventos financeiros.
- cancelamento ao fim do ciclo ja preserva entitlements da conta e `commercial-status` dos eventos cobertos ate `ends_at`.
- a pagina `/plans` ja saiu do mock, faz checkout real simplificado e agora tambem aciona cancelamento real da assinatura.

Ainda pendente e prioritario:

- integrar um gateway externo real via adapter;
- endurecer webhooks, retries e conciliacao financeira automatica;
- endurecer renovacao automatica e estados financeiros avancados;
- endurecer enforcement operacional dos limites resolvidos.

## Veredito Executivo

Hoje o dominio de billing ja saiu da fase puramente estrutural e entrou numa base transicional utilizavel, mas ainda nao fechou como produto financeiro completo.

O que ja existe e esta correto:

- existe catalogo de planos com precos e features;
- existe assinatura vinculada a organizacao;
- existe estrutura para compra avulsa por evento;
- existe motor de grants e entitlements do evento;
- existe catalogo separado de `event_packages`;
- a sessao autenticada ja usa entitlements resolvidos para expor acesso e feature flags ao frontend.

O que ainda nao fecha:

- o checkout de assinatura ja cria order, payment e invoice atraves da camada de gateway, mas ainda com provider manual;
- a compra direta de evento unico ja existe com confirmacao manual e webhook interno, mas ainda sem provider externo real;
- o frontend de planos e billing ja esta ligado a contratos reais de catalogo, assinatura atual, invoices, checkout e cancelamento da assinatura, mas ainda falta maturar UX comercial publica;
- ainda faltam adapter externo real, retries, conciliacao e renovacao automatica para fechar o dominio financeiro.

Conclusao:

- a assinatura hoje esta vinculada a `organizacao`, nao a `usuario` nem a `evento`;
- a compra avulsa esta desenhada para ficar vinculada a `evento`, mas a regra de negocio ainda nao foi fechada;
- o sistema precisa formalizar um modelo hibrido: `organizacao` como dono da assinatura recorrente e `evento` como alvo de compras avulsas, upgrades ou snapshots de capacidade.

Leitura mais atualizada do codigo:

- o sistema ja tem onboarding OTP-first por WhatsApp com contexto de jornada;
- a modelagem atual ja comporta parceiro recorrente, cliente direto e evento com modo comercial explicito;
- a camada de ativacao do evento ja existe por `event_access_grants`;
- o melhor proximo passo nao e reescrever billing inteiro, e sim ligar um provider externo real sobre a fundacao comercial que ja entrou.

## Stack Atual Confirmada

### Backend

- Laravel 13
- PHP 8.3
- PostgreSQL
- Redis
- fila `billing` prevista na arquitetura
- camada de gateway ja implementada com provider manual

### Frontend

- React 18
- TypeScript
- Vite 5
- TanStack Query
- pagina administrativa `/plans` ligada a endpoints reais de billing da conta

### Conclusao de stack

A stack base esta correta.

Nao falta framework. Falta consolidar:

- modelo comercial;
- contratos de API reais;
- fluxo de checkout e cobranca;
- servico de entitlements;
- integracao de gateway via adaptador.

## O Que O Codigo Ja Tem E Vale Reaproveitar

## 1. `organization` ja e a conta-matriz do dominio

Hoje isso ja esta consolidado em:

- `organizations`
- `organization_members`
- `clients`
- `events`

Essa base continua correta para:

- parceiro recorrente;
- operacao interna;
- cliente direto, se o tipo da organizacao for expandido.

Ponto importante:

- o enum atual de tipo de organizacao ja existe e hoje contem `partner`, `direct_customer`, `host`, `agency`, `brand` e `internal`.

Conclusao:

- nao precisamos trocar o eixo do dominio;
- nao precisamos criar um sistema paralelo para cliente direto.

## 2. OTP por WhatsApp ja existe

Hoje ja existem:

- `POST /api/v1/auth/register/request-otp`
- `POST /api/v1/auth/register/resend-otp`
- `POST /api/v1/auth/register/verify-otp`

E a action atual:

- cria o usuario;
- cria uma organizacao automaticamente;
- vincula o usuario como `partner-owner`;
- autentica e devolve onboarding.

Isso e muito valioso porque elimina uma fase inteira de fundacao tecnica.

Mas existe um gap claro:

- o onboarding ja aceita contexto de jornada, mas o produto ainda nao abriu as jornadas publicas reais de trial e compra direta;
- parte do frontend ainda comunica billing com mentalidade `plan-first`.

Conclusao:

- o onboarding OTP ja foi reaproveitado como base;
- agora ele precisa ser conectado as jornadas comerciais reais do produto.

## 3. O evento ja tem embrião de snapshot comercial

Hoje `events` ja tem:

- `purchased_plan_snapshot_json`
- `commercial_mode`
- `current_entitlements_json`

Esse campo ja e um sinal de que o evento foi pensado como unidade comercial.

Conclusao:

- o evento ja tem situacao comercial explicita e snapshot efetivo resolvido;
- o que falta agora e endurecer os fluxos que alimentam esse estado.

## 4. `event_purchases` ja aparece como origem de receita

Hoje `event_purchases` ja e usado em:

- KPIs de dashboard;
- ranking de parceiros por receita;
- historico improvisado de `billing/invoices`.

Isso mostra que a compra por evento nao e apenas ideia de schema. Ela ja comecou a contaminar leitura operacional e financeira.

Conclusao:

- qualquer evolucao futura precisa preservar compatibilidade de leitura com `event_purchases`;
- nao da para tratar compra avulsa como apendice irrelevante.
- o historico financeiro principal, porem, ja pode se apoiar em `invoices` reais em vez de `event_purchases`.

## 5. O frontend ja tem onboarding OTP e sessao orientada a features

O painel hoje ja:

- fala com os endpoints de OTP;
- persiste sessao;
- recebe `subscription` e `feature_flags` em `/auth/me`.

Mas o fluxo atual fecha em:

- cadastro concluido;
- CTA `Ir para planos`;
- jornada comercial ainda plan-first.

Conclusao:

- o frontend ja tem base tecnica;
- o que falta e redesenho de jornada e contratos comerciais.

## Estado Atual Confirmado

## Catalogo de planos

Hoje o catalogo esta modelado em tres tabelas:

- `plans`
- `plan_prices`
- `plan_features`

O seeder atual cria tres planos B2B:

- `starter`
- `professional`
- `business`

As features atuais ja misturam:

- limites operacionais, como `events.max_active`;
- politicas de retencao, como `media.retention_days`;
- habilitacao de modulos, como `play.enabled`, `wall.enabled`, `channels.whatsapp`, `white_label.enabled`.

Isso mostra que o plano hoje e usado como fonte de:

- precificacao;
- limites;
- feature flags.

## Assinatura recorrente

A assinatura atual esta na tabela `subscriptions`.

Campos mais importantes:

- `organization_id`
- `plan_id`
- `status`
- `billing_cycle`
- `starts_at`
- `trial_ends_at`
- `renews_at`
- `ends_at`
- `canceled_at`
- ids externos de gateway

O ponto mais importante do dominio atual e este:

1. a assinatura pertence a uma `organizacao`;
2. o usuario chega nela por `currentOrganization()`;
3. o evento nao tem uma assinatura propria.

Hoje a aplicacao assume que a conta comercial principal e a organizacao parceira.

## Compra avulsa por evento

Existe a tabela `event_purchases`, com os campos:

- `organization_id`
- `client_id` opcional
- `event_id` opcional
- `plan_id`
- `price_snapshot_cents`
- `currency`
- `features_snapshot_json`
- `status`
- `purchased_by_user_id`
- `purchased_at`

Isso indica que o sistema ja previu um segundo modelo comercial:

- nao apenas assinatura recorrente da organizacao;
- mas tambem compra avulsa com snapshot de preco e features.

O nome da tabela confirma a intencao de compra ligada ao contexto de evento.

Ponto de atencao importante:

- hoje ela ja aceita `package_id`, mas ainda convive com `plan_id` por compatibilidade;
- isso mostra uma transicao controlada entre a leitura legada e o novo catalogo avulso.

Isso foi util para sair do zero, mas tende a gerar ambiguidade de produto.

## Snapshot no evento

A entidade `events` tem o campo:

- `purchased_plan_snapshot_json`

Esse campo reforca a ideia de que o evento pode carregar um snapshot comercial proprio, separado da assinatura global da organizacao.

Hoje a regra de acesso ja esta centralizada pelo resolver de entitlements, mas a origem financeira ainda nao esta totalmente consolidada.

## API atual

Hoje os endpoints expostos sao:

- `GET /api/v1/billing/subscription`
- `GET /api/v1/billing/invoices`
- `POST /api/v1/billing/checkout`
- `POST /api/v1/webhooks/billing/{provider}`
- `POST /api/v1/public/trial-events`
- `GET /api/v1/public/event-packages`
- `POST /api/v1/public/event-checkouts`
- `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm`
- `POST /api/v1/admin/quick-events`
- `GET /api/v1/plans/current`
- `GET /api/v1/plans`
- `GET /api/v1/plans/{id}`
- `GET /api/v1/subscriptions`
- `GET /api/v1/subscriptions/{id}`

## O que o backend ja faz hoje

- lista catalogo de planos com precos e features;
- devolve a assinatura corrente da organizacao;
- devolve o plano corrente da organizacao;
- injeta subscription e feature flags no payload de `me`;
- permite checkout simplificado de assinatura com `billing_order`, `payment` e `invoice`;
- permite checkout simplificado de evento unico com order, confirmacao manual e grant.

## O que o backend ainda nao fecha

### 1. O checkout ainda nao e checkout real

Hoje `POST /billing/checkout`:

- valida apenas `plan_id` e `billing_cycle`;
- busca a organizacao atual;
- usa `plan_prices` para montar o snapshot comercial e financeiro;
- cria `billing_order` e `billing_order_item`;
- passa por `BillingGatewayInterface` para abrir o checkout;
- gera `payment` e `invoice` minimos;
- no provider manual, liquida o pagamento no mesmo fluxo;
- no desenho atual ja suporta webhook e idempotencia, embora ainda sem provider externo real.

Isso saiu do stub mais bruto e ja ganhou fronteira de provider, mas ainda nao e checkout produtivo de verdade.

### 2. O endpoint de invoices agora representa invoices, mas ainda em liquidacao manual

Hoje `GET /billing/invoices` busca `org->invoices()` e pagina invoices reais vinculadas a `billing_orders`.

Na pratica:

- o nome do endpoint e `invoices`;
- o dado retornado agora e `invoices` com snapshot do pedido, do plano ou do pacote.

O gap restante nao e mais conceitual. O gap agora e operacional:

- settlement ainda manual no provider inicial;
- ausencia de conciliacao automatica com provider externo;
- ausencia de estados financeiros avancados alem do fluxo feliz.

### 3. A resolucao de acesso ja existe, mas ainda precisa endurecer enforcement

Hoje `MeResource` e `commercial-status` ja usam resolvers de entitlements.

Isso melhorou bastante o dominio, mas ainda faltam duas camadas:

- enforcement sistematico dos limites operacionais;
- uso mais amplo desses entitlements nas jornadas financeiras e comerciais.

### 4. O sistema esta em transicao entre catalogo recorrente e catalogo avulso

Hoje `event_packages` ja existe, mas `event_purchases` ainda referencia `plan_id`.

Isso pode funcionar, mas gera ambiguidade:

- um plano recorrente de parceiro e o mesmo produto de um pacote avulso de evento?
- ou sao catalogos diferentes com finalidades diferentes?

No estado atual, o schema ja separou os catalogos, mas a tabela legada de compra avulsa ainda precisa ser adaptada para a nova fonte.

### 5. O ciclo de vida comercial esta incompleto

Hoje ainda faltam fluxos reais para:

- upgrade;
- downgrade;
- mudanca de ciclo mensal/anual;
- cobranca proporcional;
- reativacao;
- inadimplencia;
- expiracao de compra avulsa;
- renovacao automatica;
- webhook de gateway.

### 6. O onboarding ja aceita jornadas, mas a experiencia publica ainda nao esta fechada

Hoje a base de autenticacao e onboarding:

- ja aceita contexto de jornada;
- ja cria organizacao compativel com `partner` ou `direct_customer`;
- ja suporta trial publico, compra direta simplificada e criacao assistida por admin;
- ainda nao tem toda a experiencia de frontend comercial finalizada.

O maior atrito atual saiu do backend e ficou na camada de produto/UI para os cenarios:

- cerimonialista/fotografo querendo testar rapido;
- noiva comprando um evento unico;
- super admin liberando cortesia.

### 7. O dominio de cliente ainda herda demais a visao da assinatura da organizacao

Hoje `ClientResource` e `ListClientsQuery` ja expoem:

- `plan_key`
- `plan_name`
- `subscription_status`

Na rodada mais recente, isso passou a aparecer tambem sob um bloco explicito `organization_billing`, o que melhora a leitura do dominio, mas ainda nao conclui a limpeza completa dos acoplamentos antigos.

Na segunda rodada do `M6-T1`, o dashboard e as telas de evento tambem foram ajustados para reduzir essa ambiguidade:

- dashboard passou a separar receita liquidada de assinatura e receita liquidada de evento;
- ranking de parceiros passou a mostrar mix entre recorrencia da conta e compra avulsa por evento;
- telas de evento passaram a priorizar `commercial_mode` e `commercial-status` para explicar cobertura da conta versus ativacao propria do evento.

Esses campos fazem sentido para visao administrativa B2B, mas reforcam que o sistema ainda pensa comercialmente primeiro na conta e so depois no evento.

Isso nao e um erro, mas precisa ser desacoplado das jornadas event-first.

## Frontend Atual

## O que existe

Existe a pagina `/plans` no painel administrativo.

Ela expoe tres abas:

- `Planos`
- `Assinaturas`
- `Cobrancas`

## O que existe de verdade hoje

No backend:

- catalogo de planos real;
- assinatura atual real;
- plano atual real.

No frontend:

- a tela principal de planos ja usa catalogo real, assinatura atual real, invoices reais e checkout real de assinatura;
- ainda faltam estados financeiros avancados e uma UX comercial menos administrativa para as jornadas publicas.

Conclusao:

- o backend ja tem uma base;
- o frontend principal de billing da conta ja espelha o estado real do dominio, mas ainda nao cobre toda a jornada comercial event-first.

## Jornadas Prioritarias Que O Modelo Precisa Suportar

Hoje o codigo atende bem apenas uma parte do problema: o parceiro com conta.

O produto precisa suportar bem quatro portas de entrada:

### 1. Trial de evento

Cenario:

- cerimonialista ou fotografo quer testar sem compromisso;
- nao quer assinar nada antes de ver valor;
- quer um evento demo com limites claros.

Estado atual:

- suportado via `POST /api/v1/public/trial-events` com sessao leve;
- o evento ja nasce com grant `trial` e `commercial_mode=trial`;
- ainda falta a experiencia final de frontend para expor essa jornada de forma comercial no produto.

### 2. Compra direta de evento unico

Cenario:

- noiva, debutante ou cliente final quer contratar um unico evento;
- nao quer linguagem de SaaS;
- quer pacote de evento, nao assinatura.

Estado atual:

- suportado em versao simplificada por:
  - `POST /api/v1/public/event-checkouts`
  - `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm`
  - `POST /api/v1/webhooks/billing/{provider}`
- a jornada ja cria conta leve `direct_customer`, evento, order preliminar, compra avulsa e grant;
- a jornada ja aceita confirmacao manual e webhook sobre a trilha financeira minima;
- ainda falta plugar um provider externo real e fechar a UX final de frontend.

### 3. Parceiro recorrente

Cenario:

- fotografo, cerimonialista ou empresa quer operar varios eventos;
- assinatura na organizacao continua sendo a melhor resposta.

Estado atual:

- esse e o caminho mais maduro do sistema hoje.

### 4. Bonus ou grant manual

Cenario:

- super admin quer criar ou liberar evento sem depender de pagamento;
- precisa de auditoria, motivo, prazo e origem da concessao.

Estado atual:

- suportado em versao inicial por `POST /api/v1/admin/quick-events`;
- o fluxo ja cria ou reutiliza usuario/organizacao, gera evento e concede grant `bonus` ou `manual_override`;
- ainda falta o envio real de acesso por WhatsApp e, se necessario, endpoints separados para alterar grants depois da criacao.

## Logica Atual De Vinculo

## Conta principal

A conta comercial principal hoje e a `organizacao`.

Ela concentra:

- assinatura;
- permissao de billing;
- historico de compras;
- disponibilidade de modulos para os usuarios da organizacao.

## Usuario

O usuario nao possui assinatura propria.

O usuario:

- pertence ou opera dentro de uma organizacao;
- acessa a assinatura por `currentOrganization()`;
- pode ser o autor da compra, via `purchased_by_user_id`, apenas para auditoria.

## Evento

O evento:

- pertence a uma organizacao;
- pode carregar `purchased_plan_snapshot_json`;
- pode ser alvo de `event_purchases`.

Isso indica que o evento e o nivel correto para compras avulsas, upgrades temporarios e snapshots comerciais por entrega.

## Cliente

`client_id` em `event_purchases` parece representar o cliente final do evento, nao o dono da conta da plataforma.

Hoje isso ainda esta ambiguo.

Hipotese mais provavel:

- `organization` = parceiro que usa a plataforma;
- `client` = contratante final do evento;
- `event_purchase` pode registrar se o upgrade foi comprado para um evento de um cliente especifico.

Essa regra precisa ser formalizada.

## Leitura recomendada da logica atual

Hoje o sistema sugere este desenho:

1. a organizacao contrata um plano base recorrente;
2. os usuarios da organizacao herdam acesso conforme permissao + features do plano;
3. um evento pode ter um snapshot de capacidades proprio;
4. uma compra avulsa pode complementar ou especializar um evento.

O problema nao e falta de estrutura. O problema e falta de regra oficial de precedencia.

## Leitura Atualizada Do Dominio

Hoje o billing do produto esta dividido, na pratica, em duas camadas misturadas:

### Camada 1: financeiro e catalogo

- `plans`
- `plan_prices`
- `plan_features`
- `subscriptions`
- `billing_orders`
- `billing_order_items`
- `payments`
- `invoices`
- `event_purchases`

### Camada 2: acesso efetivo

Hoje essa camada ja existe de forma formal, mas ainda convive com leituras antigas.

Ela esta espalhada em:

- `event_access_grants`;
- `EntitlementResolverService`;
- `OrganizationEntitlementResolverService`;
- `MeResource` e `AccessMatrix`;
- checks de permissao e snapshots no evento.

Esse e o ponto central da evolucao:

- pagamento e uma coisa;
- direito de operar o evento e outra.

## O Que Ja Esta Certo

- assinatura vinculada a organizacao faz sentido para o modelo parceiro B2B;
- catalogo de planos com features e precos separados esta em boa direcao;
- snapshot de compra por evento e uma boa ideia para congelar o contrato daquela entrega;
- `MeResource` ja prova que billing precisa conversar com acesso e modulos;
- existe permissao de billing no sistema de roles.

## Gaps Criticos Restantes

## 1. Falta endurecer o fluxo real de compra avulsa baseado em `event_package`

O schema, a leitura unificada e a jornada publica ja suportam `package_id`, e o produto ja possui checkout simplificado com order, `event_purchase` e grant.

O que ainda falta nesta frente:

- gateway real de pagamento;
- webhooks, idempotencia financeira e conciliacao;
- frontend comercial consumindo esse fluxo de ponta a ponta.

Minha recomendacao:

- manter `event_purchases` como registro transicional de historico comercial;
- preservar `event_package` como catalogo oficial de compra avulsa;
- endurecer agora a trilha financeira em cima de `billing_orders`, sem voltar a acoplar a compra avulsa em `plan_id`.

## 2. Falta endurecer enforcement em cima dos entitlements

Hoje o resolver ja existe, salva snapshot e abastece `MeResource` e `commercial-status`.

O gap agora e outro:

- fazer os modulos operacionais realmente obedecerem limites como `events.max_active`, `retention_days` e capacidades por evento;
- reduzir leituras antigas que ainda olham diretamente para `plan` em vez do estado resolvido.

## 3. Falta fechar a regra de precedencia

Pergunta estrutural:

- um evento pode usar mais do que a assinatura base da organizacao porque comprou um pacote proprio?

Se sim, a regra recomendada e:

1. assinatura da organizacao define baseline;
2. compra avulsa do evento adiciona ou expande capacidades do evento;
3. snapshot final do evento e congelado para aquela entrega;
4. permissoes de usuario continuam obrigatorias.

Eu adicionaria uma quinta regra:

5. evento com grant proprio ativo nao deve morrer automaticamente quando a assinatura da organizacao expira, se o grant cobre aquela entrega.

## 4. Falta enforcement real de limites

Hoje as features existem, mas ainda nao ha evidencia de enforcement sistematico para:

- numero maximo de eventos ativos;
- retencao de midia;
- acesso real a modulos por evento;
- limite de canais;
- limite de operadores;
- uso de white label;
- uso de dominio customizado.

Sem enforcement, o billing vira apenas rotulo de UI.

## 5. Falta trilha financeira real

Para producao, ainda faltam entidades e fluxos para:

- webhook;
- falha;
- reembolso;
- cancelamento;
- tentativa automatica.

## 6. Falta gateway, webhook e conciliacao automatica

Hoje ainda faltam:

- adapter externo real de gateway;
- retries e conciliacao automatica.

Sem essa camada, o produto ate consegue representar payment e invoice minima, mas nao fecha conciliacao financeira produtiva com provider externo.

## 7. Falta tirar o frontend de billing do modo mock

O backend de fundacao comercial ja amadureceu mais do que a UI.

Hoje ainda faltam:

- `/plans` conectado a contratos reais;
- leitura real de assinatura, compras e historico;
- jornada comercial separada para trial, compra direta e parceiro recorrente.

## Direcao Recomendada

## Modelo comercial recomendado

A direcao mais coerente com o schema atual e um modelo hibrido:

### 1. Assinatura recorrente da organizacao

Responsavel por:

- habilitar a conta parceira;
- liberar modulos base;
- controlar limites padrao;
- definir faturamento mensal ou anual.

### 2. Compra avulsa por evento

Responsavel por:

- adicionar capacidade extra a um evento;
- liberar modulos premium em um evento especifico;
- vender pacote fechado para um cliente final;
- congelar o que foi contratado naquele evento.

### 3. Snapshot no evento

Responsavel por:

- garantir que o evento preserve a configuracao comercial contratada;
- evitar que mudanca futura no plano da organizacao altere retroativamente a entrega do evento;
- sustentar auditoria e suporte.

### 4. Grant manual ou bonificado

Responsavel por:

- ativar evento sem pagamento;
- suportar onboarding assistido;
- permitir cortesia, parceria e recuperacao comercial;
- manter tudo auditavel.

### 5. Trial de evento

Responsavel por:

- reduzir friccao comercial;
- permitir demonstracao sem cartao;
- facilitar conversao do mesmo evento depois.

## Arquitetura alvo recomendada

## Camada de catalogo

Separar melhor os produtos comerciais:

- `plans`: catalogo recorrente da organizacao;
- `packages` ou `billing_products`: catalogo avulso de evento, add-ons e extras.

Se o time quiser ir mais generico:

- `products`
- `prices`
- `product_features`

Mas, para o estado atual do projeto, eu prefiro algo mais pragmatico:

- `plans` para recorrencia;
- `event_packages` para avulso.

## Camada de grants de evento

Criar:

- `event_access_grants`

Responsabilidades:

- representar trial;
- representar compra avulsa;
- representar bonus;
- representar manual override;
- representar cobertura vinda da assinatura, se isso for desejado como grant sintetico.

Campos recomendados:

- `organization_id`
- `event_id`
- `source_type`
- `source_id`
- `package_id`
- `status`
- `priority`
- `merge_strategy`
- `starts_at`
- `ends_at`
- `features_snapshot_json`
- `limits_snapshot_json`
- `granted_by_user_id`
- `notes`
- `metadata_json`

## Camada financeira

Entidades recomendadas para medio prazo:

- `subscriptions`
- `subscription_changes`
- `billing_orders`
- `billing_order_items`
- `payments`
- `invoices`
- `event_purchases` ou `event_order_links`

Se a equipe quiser evoluir sem ruptura, `event_purchases` pode permanecer no curto prazo como registro de compra avulsa, desde que:

- o nome seja assumido oficialmente;
- o fluxo de criacao dela seja implementado;
- o conceito de invoice fique separado.

## Camada de entitlements

Criar um servico unico para resolver capacidade efetiva.

Exemplo de responsabilidades:

- ler assinatura ativa da organizacao;
- ler pacote avulso ativo do evento;
- ler grants ativos do evento;
- combinar features e limites;
- devolver um contrato unico de acesso.

Exemplo de saida:

```json
{
  "organization": {
    "plan": "professional",
    "billingCycle": "monthly"
  },
  "event": {
    "source": "event_purchase",
    "snapshotVersion": 1
  },
  "modules": {
    "hub": true,
    "wall": true,
    "play": true
  },
  "limits": {
    "eventsMaxActive": 10,
    "mediaRetentionDays": 90
  }
}
```

## Logica recomendada de vinculo

Regra principal recomendada:

1. `organization` e a dona da assinatura recorrente;
2. `event` e o alvo da compra avulsa;
3. `user` e apenas ator operacional ou comprador auditavel;
4. `client` e referencia comercial do evento, nao o dono da assinatura da plataforma.

Evolucao recomendada do enum de organizacao:

- manter `partner` e `internal`;
- avaliar se `agency` e `brand` continuam como taxonomia util ou se viram variacoes de parceiro;
- adicionar `direct_customer` para compra direta;
- eventualmente tratar `host` como subtipo operacional, nao eixo comercial principal.

## Fluxos recomendados

### Fluxo A: assinatura da organizacao

1. organizacao escolhe plano;
2. sistema cria order ou checkout session;
3. gateway processa pagamento;
4. webhook confirma;
5. assinatura entra em `active`;
6. entitlements da organizacao sao atualizados.

### Fluxo B: upgrade avulso do evento

1. operador ou owner escolhe pacote do evento;
2. sistema gera compra avulsa vinculada a `organization_id` e `event_id`;
3. snapshot de features e preco e congelado;
4. pagamento confirma;
5. evento recebe novo snapshot comercial efetivo.

### Fluxo C: trial de evento

1. usuario entra por OTP;
2. sistema cria ou reutiliza organizacao leve;
3. evento e criado em modo demo;
4. grant `trial` e ativado;
5. evento entra no painel ja utilizavel;
6. conversao futura reaproveita o mesmo evento.

Observacao atualizada:

- na base atual, a primeira versao foi implementada por `sessao leve` em vez de OTP estrito;
- o endpoint ja devolve token autenticado, onboarding e `next_path` para o evento criado.

### Fluxo D: bonus ou grant manual

1. super admin cria ou escolhe organizacao;
2. cria evento ou reaproveita um evento existente;
3. cria grant `bonus` ou `manual_override`;
4. sistema recalcula entitlements;
5. evento fica operativo sem assinatura fake.

### Fluxo E: renovacao e inadimplencia

1. job agenda renovacao;
2. gateway tenta cobranca;
3. webhook informa sucesso ou falha;
4. assinatura muda de estado;
5. entitlements reagem conforme regra definida.

## Stack sugerida para billing

## Backend

Manter:

- Laravel 12
- Jobs + queue `billing`
- Webhooks dedicados por gateway

Adicionar:

- `BillingGatewayInterface`
- adaptadores concretos por gateway
- actions dedicadas para checkout, activate, cancel, change plan e record payment
- `EntitlementResolverService`

## Gateway

Como a operacao e Brasil-centric, faz sentido avaliar primeiro:

- Pagar.me
- Asaas
- Mercado Pago

`Stripe` ainda pode ser mantido como opcao de abstracao, mas eu nao desenharia o dominio acoplado a Stripe.

## Frontend

Manter:

- React
- TanStack Query

Adicionar ou consolidar:

- contratos reais de `/plans`, `/billing/subscription`, `/billing/invoices`;
- jornadas separadas para `trial`, `single event purchase` e `partner plan`;
- formularios com React Hook Form + Zod para checkout e troca de plano;
- pagina `/plans` ligada a dados reais;
- estados claros para trial, ativo, cancelado, expirado e inadimplente.

## Impactos Concretos No Codigo Atual

## Backend

### Mudancas incrementais recomendadas

1. Criar trilha financeira real
   - `billing_orders`
   - `billing_order_items`
   - `payments`
   - `invoices`

2. Preservar `event_purchases` no curto prazo
   Porque dashboard e historico ja usam essa tabela

### Mudancas de comportamento recomendadas

1. `billing/checkout` passa a cuidar apenas de assinatura recorrente
2. checkout de evento ganha endpoint proprio
3. `billing/invoices` deixa de devolver `event_purchases`
4. `MeResource` passa a consumir um resolver de entitlements, nao leitura manual espalhada

## Frontend

### Mudancas de jornada recomendadas

1. cadastro OTP nao deve terminar sempre em `/plans`
2. a home comercial deve separar:
   - `Teste gratis`
   - `Contratar meu evento`
   - `Sou parceiro`
3. o painel do evento deve exibir situacao comercial simplificada
4. `/plans` deve virar area de billing da conta, nao ponto inicial universal

### Mudancas de linguagem recomendadas

1. cliente final nao deve ver linguagem de SaaS como ponto de partida
2. parceiro sim deve ver assinatura, eventos ativos e limites
3. admin precisa de atalho operacional de bonificacao

## Perguntas Em Aberto

Estas perguntas precisam ser respondidas antes de endurecer o dominio:

1. O produto quer vender apenas assinatura da organizacao ou assinatura + pacote por evento?
2. O parceiro pode ter plano base e ainda comprar extras para eventos especificos?
3. O pacote avulso de evento usa o mesmo catalogo de `plans` ou precisa de catalogo proprio?
4. `client_id` em `event_purchases` e obrigatorio para regra de negocio ou apenas contexto comercial?
5. O snapshot comercial do evento deve sobrescrever ou apenas complementar o plano da organizacao?
6. Se a assinatura da organizacao cair, eventos com compra avulsa continuam operando?
7. O que exatamente significa `billing/invoices`: faturas de gateway, recibos de pagamento ou historico comercial?
8. O sistema precisara de cupom, desconto, trial, comissao de parceiro ou proration?
9. O faturamento e sempre por organizacao ou pode existir cobranca em nome do cliente final do evento?
10. Qual modulo pode ser vendido avulso: `wall`, `play`, `hub`, `whitelabel`, armazenamento extra, retention extra?

## Plano De Acao Recomendado

## Fase 0: decisoes de produto e nomenclatura

Fechar em workshop curto:

- diferenca entre `partner` e `direct_customer`;
- catalogo de `plans` versus `event_packages`;
- definicao oficial de `trial`, `bonus`, `manual_override` e `subscription_covered`;
- regra de precedencia entre assinatura e grant de evento.

Sem isso, o schema novo nasce com retrabalho.

## Fase 1: ajustar o que ja existe sem ruptura

Objetivo:

- aproveitar fundacao atual e destravar jornadas.

Implementacoes recomendadas:

1. adicionar `direct_customer` em `OrganizationType`;
2. adaptar onboarding OTP para aceitar origem de jornada;
3. remover dependencia de ir sempre para `/plans` apos cadastro;
4. adicionar `commercial_mode` e `current_entitlements_json` em `events`.

Resultado esperado:

- o sistema passa a suportar parceiro e cliente direto no mesmo backbone.

## Fase 2: criar o motor de ativacao do evento

Objetivo:

- separar acesso efetivo de pagamento.

Implementacoes recomendadas:

1. criar tabela `event_access_grants`;
2. criar `EntitlementResolverService`;
3. recalcular snapshot do evento sempre que assinatura ou grant mudar;
4. expor endpoint `GET /events/{id}/commercial-status`.

Resultado esperado:

- trial, bonus e compra avulsa passam a usar o mesmo motor.

## Fase 3: separar catalogo avulso do catalogo recorrente

Objetivo:

- parar de usar `plans` para vender tudo.

Implementacoes recomendadas:

1. criar `event_packages`;
2. criar `event_package_prices`;
3. criar `event_package_features`;
4. migrar novos fluxos de compra avulsa para esse catalogo;
5. manter compatibilidade de leitura com `event_purchases` por transicao.

Resultado esperado:

- linguagem comercial e regras tecnicas ficam alinhadas.

## Fase 4: abrir as tres jornadas comerciais

Objetivo:

- reduzir atrito real na venda.

Implementacoes recomendadas:

1. jornada publica `Teste gratis em um evento`;
2. jornada publica `Contratar meu evento`;
3. jornada admin `Criacao rapida / Bonificar evento`.

APIs recomendadas:

- `POST /api/v1/public/trial-events`
- `GET /api/v1/public/event-packages`
- `POST /api/v1/public/event-checkouts`
- `POST /api/v1/public/event-checkouts/{billingOrder:uuid}/confirm`
- `GET /api/v1/events/{id}/commercial-status`
- `POST /api/v1/admin/quick-events`
- `POST /api/v1/admin/events/{id}/grants`

Resultado esperado:

- o produto deixa de depender apenas do funil de assinatura.

## Fase 5: endurecer o financeiro

Objetivo:

- transformar billing em modulo produtivo.

Implementacoes recomendadas:

1. criar `billing_orders`, `billing_order_items`, `payments`, `invoices`;
2. separar invoice de purchase;
3. restringir `billing/checkout` a assinatura recorrente;
4. integrar gateway via `BillingGatewayInterface`;
5. processar webhooks, retries e estados financeiros reais.

Status atualizado:

- `billing_orders` e `billing_order_items` ja existem e sustentam o checkout publico simplificado de evento;
- `payments` e `invoices` minimos ja existem para assinatura e compra avulsa;
- `BillingGatewayInterface`, `ManualBillingGateway` e `POST /api/v1/webhooks/billing/{provider}` ja existem como fundacao;
- adapter externo real, retries e conciliacao continuam pendentes.

Resultado esperado:

- cobranca e ativacao ficam desacopladas, mas coerentes.

## Fase 6: limpar leituras acopladas ao plano da organizacao

Objetivo:

- evitar que tudo continue parecendo `billing por conta`.

Implementacoes recomendadas:

1. revisar `ClientResource` e telas que mostram `plan_name` automaticamente;
2. revisar dashboards que hoje tratam `event_purchases` como receita final unica;
3. revisar regras de suporte para enxergar `commercial_mode` do evento.

Resultado esperado:

- a operacao passa a enxergar evento como unidade comercial real.

Status atual:

- `clients`, dashboard/KPIs e telas de evento ja foram ajustados nas frentes principais do `M6-T1`;
- o sistema agora comunica melhor quando um dado comercial pertence a conta e quando pertence ao evento;
- as proximas evolucoes podem focar em refinamentos de UX e enforcement operacional, nao mais em desambiguacao basica do dominio.

## O Que Eu Faria Agora

## Fase 1: fechar regra de negocio

Definir formalmente:

- o modelo comercial alvo;
- a diferenca entre plano recorrente e pacote avulso;
- a precedencia entre assinatura da organizacao e compra do evento.

Sem isso, qualquer implementacao financeira corre risco de retrabalho.

## Fase 2: endurecer o backend atual

Implementar:

- actions reais para checkout;
- request/resource dedicados;
- uso correto de `plan_prices`;
- endurecer renovacao, cancelamento e estados financeiros;
- adapter externo real, retries e conciliacao;
- frontend de billing em contratos reais.

## Fase 3: criar a camada de entitlements

Antes de expandir o produto comercial, criar:

- `EntitlementResolverService`
- testes de precedencia
- enforcement de limites no dominio de eventos e modulos

## Fase 4: alinhar o frontend

Trocar mocks da pagina `/plans` por:

- listagem real de planos;
- assinatura atual real;
- historico financeiro real;
- fluxo de contratar e trocar plano;
- visualizacao clara de extras por evento, se o modelo hibrido for confirmado.

## Fase 5: integrar gateway

So depois da regra fechada:

- escolher gateway principal;
- implementar adapters;
- processar webhook;
- registrar audit trail;
- automatizar retry e renovacao.

## Recomendacao Final

Minha recomendacao e esta:

1. manter a assinatura na `organizacao`;
2. formalizar o evento como unidade comercial propria;
3. adicionar `event_access_grants` como motor oficial de ativacao;
4. criar catalogo separado para `event_packages`;
5. separar invoice de purchase;
6. criar um resolver de entitlements;
7. reutilizar OTP e organizacao como base, sem reescrever o backbone;
8. migrar o frontend de billing para jornadas reais, nao so para espelhar estrutura interna.

O schema atual ja aponta para um produto hibrido. O trabalho agora nao e reinventar billing. E transformar a intencao que ja existe em regra oficial de dominio.
