# Settings, Branding and Team Invite Analysis

## Escopo

Esta analise cobre:

- branding de organizacao em `/settings`;
- o que esse branding ja controla hoje;
- o que ele ainda nao controla no contexto geral do Evento Vivo;
- equipe da organizacao em `/settings`;
- o gap entre "adicionar membro" e um fluxo real de convite com link + WhatsApp.

## Resumo executivo

Hoje o modulo de `settings` ja consegue:

- editar dados basicos da organizacao;
- editar cores primarias/secundarias;
- fazer upload real do logo da organizacao;
- persistir preferencias do usuario autenticado;
- listar, adicionar e remover membros da equipe da organizacao atual.

Mas ainda ha uma separacao forte entre:

- branding da organizacao no painel;
- branding do evento nas superficies publicas.

Em outras palavras:

- o branding da organizacao ja muda a identidade do painel autenticado;
- as experiencias publicas continuam, em grande parte, dependendo do branding do evento;
- o fluxo de "convidar membro" ainda nao e um convite real, e sim um upsert imediato de usuario + membership.

## O que foi validado hoje

Arquivos principais revisados:

- `apps/web/src/modules/settings/SettingsPage.tsx`
- `apps/web/src/app/providers/BrandingProvider.tsx`
- `apps/web/src/shared/auth/labels.ts`
- `apps/api/app/Modules/Organizations/Http/Controllers/OrganizationController.php`
- `apps/api/app/Modules/Organizations/Actions/InviteCurrentOrganizationTeamMemberAction.php`
- `apps/api/app/Modules/Organizations/Actions/UploadCurrentOrganizationLogoAction.php`
- `apps/api/app/Modules/Users/Http/Controllers/MeController.php`
- `apps/api/app/Modules/Auth/Http/Resources/MeResource.php`
- `apps/api/app/Modules/Billing/Services/OrganizationEntitlementResolverService.php`
- `apps/api/app/Modules/WhatsApp/Services/WhatsAppMessagingService.php`
- `apps/api/app/Modules/Auth/Services/AuthOtpDeliveryService.php`

Testes executados nesta analise:

- `php artisan test tests/Feature/Organizations/OrganizationTest.php`
- `php artisan test tests/Feature/WhatsApp/WhatsAppInstanceManagementTest.php`
- `npx vitest run src/modules/settings/SettingsPage.test.tsx`
- `npm run type-check`

Resultado:

- backend: verde
- frontend: verde
- type-check: verde

## O que foi validado adicionalmente em 2026-04-09

Testes adicionados para fechar as ultimas duvidas do fluxo:

- `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php`
- `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php`
- `npx vitest run src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx`

Com isso ficou comprovado que:

- o fluxo atual de `POST /organizations/current/team` nao cria convite pendente; ele cria membership `active` imediatamente;
- o fluxo atual permite criar proprietario adicional pela mesma tela generica de equipe, sem um processo dedicado de transferencia de ownership;
- o fluxo atual de cadastro via OTP por WhatsApp cria uma organizacao nova ao concluir o cadastro;
- o envio de OTP atual usa a instancia de autenticacao configurada em `whatsapp.auth.instance_id`, e nao uma instancia escopada pela organizacao do futuro convite.

Esses quatro pontos removem a principal ambiguidade de arquitetura:

- o convite de equipe nao pode ser implementado como um pequeno ajuste no action atual;
- o aceite do convite nao pode reaproveitar diretamente `RegisterWithWhatsAppOtpAction`;
- a entrega por WhatsApp do convite nao pode reaproveitar diretamente `AuthOtpDeliveryService`;
- ownership precisa sair do fluxo generico de "Adicionar membro".

## Branding: o que faz hoje

### 1. Branding da organizacao no painel autenticado

Hoje o branding de organizacao ja abastece a sessao via `/auth/me`.

Dados expostos:

- `organization.logo_url`
- `organization.branding.primary_color`
- `organization.branding.secondary_color`
- `organization.branding.subdomain`
- `organization.branding.custom_domain`

No frontend, `BrandingProvider` injeta CSS vars globais:

- `--brand-primary`
- `--brand-secondary`

Isso significa que o painel autenticado ja tem base para:

- colorir botoes e componentes;
- refletir identidade da organizacao logada;
- renderizar logo da organizacao onde a sessao usar `meOrganization.logo_url`.

### 2. Branding operacional disponivel em `/settings`

Hoje `/settings` ja faz persistencia real para:

- nome da organizacao;
- slug;
- custom domain;
- cor primaria;
- cor secundaria;
- upload do logo.

O logo e normalizado para WebP e salvo em:

- `organizations/branding/{organization_id}/logo/...`

### 3. Branding ligado ao entitlement

O resolver de entitlements ja calcula:

- `branding.white_label`
- `branding.custom_domain`

Isso e importante porque o produto ja reconhece, no backend, que white-label e custom domain dependem do plano.

### 4. Branding publico do produto

As superficies publicas mais maduras hoje usam branding do evento, nao da organizacao:

- upload publico
- face search publico
- play publico
- hub
- parte da wall

Essas superficies leem, em geral:

- `event.logo_path` / `event.logo_url`
- `event.primary_color`
- `event.secondary_color`

Conclusao pratica:

- o branding de organizacao ainda nao "desce" automaticamente para os eventos;
- cada evento continua tendo seu proprio branding.

## Branding: o que ainda nao faz

### 1. Nao existe heranca clara organizacao -> evento

Hoje nao existe uma regra explicita do tipo:

- novo evento nasce com branding padrao da organizacao;
- logo/cor da organizacao servem como fallback quando o evento nao tem branding proprio.

Isso gera duplicacao operacional.

### 2. O painel nao usa entitlement para guiar UX de branding

Hoje o backend sabe se a organizacao tem:

- white-label
- custom domain

Mas `/settings` ainda nao usa isso para:

- bloquear ou esconder campos sem entitlement;
- mostrar avisos de upgrade;
- explicar por que custom domain esta indisponivel.

### 3. Branding de organizacao ainda e "logo + 2 cores"

Ainda faltam ativos importantes:

- favicon;
- logo reduzido;
- versao clara/escura;
- watermark padrao;
- imagem social / OG;
- cover institucional;
- assets por superficie.

### 4. Custom domain ainda nao e um fluxo completo

Hoje existe persistencia do campo, mas ainda falta o fluxo operacional de dominio:

- validacao semantica mais forte;
- verificacao DNS;
- status de verificacao;
- onboarding SSL / proxy;
- diagnostico quando o dominio nao aponta corretamente.

### 5. Wall ainda tem branding proprio

Na wall, o overlay ainda trabalha com:

- `partner_logo_url` da configuracao da wall;
- marca "Evento Vivo";
- toggles especificos (`show_branding`, `show_qr`, etc.).

Isso nao esta unificado com o branding da organizacao.

## Branding: o que faz sentido evoluir

### V1 recomendada de evolucao

- usar branding da organizacao como valor padrao ao criar evento;
- permitir "herdar branding da organizacao" no evento;
- exibir no settings um preview real da marca no painel;
- bloquear visualmente custom domain se o plano nao permitir;
- adicionar reset de branding para voltar ao padrao.

### V2 recomendada

- ativos adicionais: favicon, watermark, logo claro/escuro;
- preview por superficie:
  - painel
  - upload publico
  - hub
  - play
  - wall
- estrategia de white-label por nivel:
  - organizacao
  - evento
  - superficie

### V3 recomendada

- verificacao completa de dominio customizado;
- diagnostico DNS/SSL;
- rollout seguro por status:
  - `draft`
  - `pending_verification`
  - `verified`
  - `active`
  - `error`

## Equipe em `/settings`: estado atual

Hoje a aba `Equipe` ja usa API real:

- `GET /organizations/current/team`
- `POST /organizations/current/team`
- `DELETE /organizations/current/team/{member}`

O que ela faz hoje:

- lista membros da organizacao atual;
- mostra papel do membro;
- permite adicionar membro;
- permite remover membro nao-owner.

O que foi endurecido nesta analise:

- labels de papel ficaram mais consistentes em portugues no frontend;
- remocao agora exige confirmacao em modal;
- `Nome`, `WhatsApp` e `Perfil` passaram a ser obrigatorios na UX;
- backend passou a exigir `user.phone`;
- email deixou de ser obrigatorio no request de equipe;
- se nao houver email, o backend consegue criar usuario com email tecnico interno baseado no telefone.

## O problema real do convite hoje

Hoje "Adicionar membro" ainda nao e um convite real.

O fluxo atual:

1. recebe nome/email/telefone/perfil;
2. procura ou cria `users`;
3. atribui role;
4. cria `organization_members`;
5. marca membership como ativo;
6. registra activity log.

Consequencia pratica:

- hoje a interface fala em "convite", mas o backend faz provisionamento imediato;
- isso enfraquece auditoria, aceite explicito, rastreabilidade de entrega e seguranca de ownership;
- por isso o caminho correto e criar um dominio proprio de convite, nao continuar estendendo `InviteCurrentOrganizationTeamMemberAction`.

Ou seja:

- nao existe token de convite;
- nao existe link de aceite;
- nao existe expiracao;
- nao existe status de convite enviado/pendente/aceito;
- nao existe disparo de WhatsApp;
- nao existe onboarding do convidado;
- `joined_at` e preenchido imediatamente, como se a pessoa ja tivesse aceitado.

Isso precisa mudar para atender o requisito pedido.

## Convite real: o que precisa ser implementado

### 1. Novo conceito de dominio

Criar uma entidade explicita de convite.

Sugestao:

```text
organization_member_invitations
- id
- organization_id
- inviter_user_id
- invited_user_id nullable
- role_key
- invitee_name
- invitee_email nullable
- invitee_phone
- invite_token unique
- invitation_url
- delivery_channel nullable        // whatsapp | email | manual
- delivery_status nullable         // pending | queued | sent | failed
- delivery_error nullable
- whatsapp_instance_id nullable
- whatsapp_message_id nullable
- accepted_membership_id nullable
- status                           // pending | accepted | expired | revoked
- expires_at
- accepted_at nullable
- last_sent_at nullable
- created_at / updated_at
```

### 2. Nao criar membership ativo imediatamente

Fluxo recomendado:

- criar o convite;
- criar membership apenas quando o convite for aceito;
- ou, se preferir criar antes, usar `status = pending` e `joined_at = null`.

Hoje o sistema faz o contrario, e isso enfraquece auditoria e onboarding.

### 3. Link de convite

O backend precisa gerar um link real de onboarding.

Exemplo de superficie:

- `/convite/equipe/{token}`

Esse link deve permitir:

- mostrar organizacao e papel esperado;
- informar quem convidou;
- seguir para login/cadastro;
- concluir aceite da membership.

### 4. Reaproveitar a jornada de OTP por WhatsApp

O sistema ja tem cadastro por WhatsApp OTP.

O caminho mais coerente e:

1. usuario abre link de convite;
2. frontend consulta token e carrega contexto do convite;
3. se nao estiver autenticado:
   - preenche nome/telefone;
   - usa o fluxo existente de `/auth/register/request-otp`;
   - valida o OTP;
4. ao concluir, aceita o convite;
5. membership vira `active` e `joined_at` recebe `now()`.

Importante:

- convite de equipe nao deve criar uma organizacao nova;
- o fluxo atual de `RegisterWithWhatsAppOtpAction` cria organizacao nova por padrao;
- portanto o aceite do convite precisa de um ramo proprio de onboarding.

### 5. Check "Enviar convite pelo WhatsApp"

O frontend de `Equipe` deve ganhar:

- checkbox `Enviar convite pelo WhatsApp`;
- feedback do link gerado;
- estado do envio:
  - link gerado
  - WhatsApp enviado
  - falha no envio

### 6. Resolver a instancia WhatsApp correta

Esse ponto e critico.

Para convite de equipe, o envio deve usar:

- a instancia conectada da propria organizacao;
- idealmente a default instance da organizacao;
- fallback opcional para a unica instancia conectada/ativa da organizacao.

Nao usar:

- instancia global do sistema;
- instancia do fluxo de OTP de autenticacao;
- qualquer instancia de outra organizacao.

Isso evita vazamento de identidade operacional.

Validacao adicional:

- o transporte ja existe;
- o problema atual nao e Z-API nem fila;
- o problema atual e exclusivamente de scoping, modelagem do convite e aceite.

### 7. Reaproveitar a infraestrutura ja existente de envio

O modulo WhatsApp ja tem:

- `WhatsAppMessagingService::sendText(...)`
- envio assÃ­ncrono por job
- suporte a Z-API

Portanto o que falta nao e o transporte.

O que falta e:

- resolver a instancia por organizacao;
- modelar o convite;
- disparar a mensagem com template certo;
- auditar status.

### 8. Template da mensagem

Sugestao inicial de mensagem:

```text
Evento Vivo

Voce foi convidado(a) para entrar na equipe de {organizacao} como {perfil}.

Abra este link para concluir seu cadastro:
{invitation_url}

Se voce nao esperava este convite, ignore esta mensagem.
```

### 9. Endpoints recomendados

#### autenticados

- `POST /organizations/current/team/invitations`
- `POST /organizations/current/team/invitations/{invitation}/resend`
- `POST /organizations/current/team/invitations/{invitation}/revoke`
- `GET /organizations/current/team/invitations`

#### publicos

- `GET /public/team-invitations/{token}`
- `POST /public/team-invitations/{token}/request-otp`
- `POST /public/team-invitations/{token}/verify-otp`
- `POST /public/team-invitations/{token}/accept`

### 10. Regras de validacao recomendadas

- `name`: obrigatorio
- `phone`: obrigatorio, normalizado para WhatsApp BR
- `role_key`: obrigatorio
- `email`: opcional na V1 do convite por WhatsApp
- remover `partner-owner` do fluxo generico de convite; ownership deve ir para fluxo proprio
- nao permitir roles globais nesse fluxo:
  - `super-admin`
  - `platform-admin`
- deduplicar convite pendente por:
  - `organization_id`
  - `invitee_phone`
  - `role_key`

## Quick wins ja executados hoje

Itens pequenos que ja foram implementados nesta analise:

- labels mais consistentes em portugues no frontend;
- confirmacao antes de remover membro;
- validacao obrigatoria de `Nome`, `WhatsApp` e `Perfil` no dialog;
- backend exigindo `user.phone` para adicionar membro;
- suporte backend a membro sem email explicito.

Esses itens melhoram a UX agora, mas nao fecham o requisito de convite.

## O que ainda falta de fato

### Branding

- ligar entitlement na UX de `/settings`;
- definir heranca organizacao -> evento;
- adicionar preview real por superficie;
- suportar mais ativos alem de um logo;
- fechar fluxo operacional de custom domain.

### Equipe / convite

- criar dominio de convite persistido;
- gerar link real de onboarding;
- aceite do convite sem criar nova organizacao;
- envio opcional por WhatsApp com Z-API;
- resolver instancia WhatsApp por organizacao;
- status de convite e resend/revoke;
- auditoria e rastreabilidade do convite.
- separar transferencia de ownership do CRUD generico de equipe.

## Testes que devem ser criados quando esse trabalho comecar

### Backend

- convite cria registro `pending` sem ativar membership imediatamente;
- convite gera token e URL validos;
- convite escolhe a instancia WhatsApp correta da organizacao;
- convite nao usa instancia global por engano;
- convite envia `sendText` com o link;
- convite sem instancia conectada ainda retorna link manual;
- aceite do convite cria/ativa membership;
- convite expirado e rejeitado;
- convite revogado e rejeitado;
- parceiro/manager nao consegue convidar para role global;
- telefone invalido e rejeitado;
- duplicidade de convite pendente e tratada.

### Frontend

- dialog de equipe bloqueia envio sem nome/telefone/perfil;
- remocao exige confirmacao;
- checkbox de envio por WhatsApp altera payload;
- resposta com `invitation_url` aparece para copia manual;
- sucesso/falha de disparo WhatsApp aparece na UI;
- token de convite abre onboarding correto;
- usuario autenticado aceita convite sem criar conta nova;
- usuario nao autenticado segue para OTP e depois aceita convite.

## Ordem recomendada de implementacao

1. Criar dominio `OrganizationMemberInvitation`.
2. Criar resolver de instancia WhatsApp por organizacao.
3. Criar endpoint de criacao de convite com link.
4. Disparar envio opcional por WhatsApp.
5. Criar rota/public page de aceite do convite.
6. Integrar aceite ao fluxo de OTP ja existente.
7. Ajustar activity logs e lista de equipe para mostrar convites pendentes.
8. Voltar ao branding para heranca organizacao -> evento e entitlement-aware UI.

## Leitura final

O branding de organizacao ja e funcional, mas ainda e administrativo e nao comanda o ecossistema publico inteiro.

O fluxo de equipe ja e funcional, mas ainda nao e um fluxo de convite.

Para atender o requisito pedido de verdade, o proximo passo correto nao e apenas mexer no dialog do frontend. O proximo passo correto e criar um dominio explicito de convite com token, aceite, dispatch por WhatsApp e scoping rigoroso por organizacao.

Plano de execucao detalhado gerado em:

- `docs/execution-plans/settings-branding-team-invite-execution-plan-2026-04-09.md`
