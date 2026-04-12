# EventPeople Module

## Responsabilidade

Este modulo cria a camada de dominio de pessoas do evento acima da trilha tecnica de `FaceSearch`.

Ele e responsavel por:

- pessoa canonica do evento;
- modelagem em grafo das conexoes sociais do evento;
- atribuicao entre rosto detectado e pessoa;
- relacoes declaradas entre pessoas;
- coocorrencia inferida por evento;
- read models locais para lista, detalhe, review queue e busca por nome;
- grupos sociais locais e presets por tipo de evento;
- `coverage intelligence` para pares e grupos obrigatorios;
- colecoes relacionais prontas por pessoa, par, grupo e momento;
- trilha guest-facing tokenizada para entregas publicas por vinculo.

## Nao responsabilidade

Este modulo nao:

- detecta rostos;
- indexa faces na AWS;
- executa selfie search;
- decide moderacao ou publicacao de midia;
- coloca AWS no hot path de navegacao.

## Contrato operacional

Toda escrita humana deve seguir:

1. grava localmente;
2. espera commit;
3. projeta read models;
4. sincroniza AWS por job, quando necessario.

Filas dedicadas:

- `event-people-high` para ack operacional e review queue;
- `event-people-medium` para contadores e read models locais;
- `event-people-low` para sync AWS e tarefas de baixa urgencia.

Jobs do modulo devem usar `redis`, `afterCommit`, tags por `event_id` e, quando carregarem dados de pessoa/rosto, `ShouldBeEncrypted`.

## Essencia de produto

O valor do modulo nao esta em "achar um rosto isolado".

O valor esta em cruzar:

- identidade confirmada;
- rede de relacoes declaradas e inferidas;
- grupos e tribos do evento;
- cobertura obrigatoria;
- publicacao de midia;

para entregar galerias e momentos por vinculo emocional ou social.

Na pratica, isso significa:

- `person_best_of` para melhores fotos de uma pessoa;
- `pair_best_of` para melhores fotos de um par importante;
- `group_best_of` para tribos como familia, padrinhos, diretoria ou imprensa;
- `family_moment` para nucleos familiares;
- `must_have_delivery` para entregas prontas de cobertura obrigatoria.

## Rotas atuais

- `GET /api/v1/events/{event}/people`
- `POST /api/v1/events/{event}/people`
- `GET /api/v1/events/{event}/people/operational-status`
- `GET /api/v1/events/{event}/people/graph`
- `GET /api/v1/events/{event}/people/presets`
- `GET /api/v1/events/{event}/people/coverage`
- `POST /api/v1/events/{event}/people/coverage/refresh`
- `GET /api/v1/events/{event}/people/relational-collections`
- `POST /api/v1/events/{event}/people/relational-collections/refresh`
- `GET /api/v1/events/{event}/people/groups`
- `POST /api/v1/events/{event}/people/groups`
- `POST /api/v1/events/{event}/people/groups/apply-preset`
- `PATCH /api/v1/events/{event}/people/groups/{group}`
- `DELETE /api/v1/events/{event}/people/groups/{group}`
- `POST /api/v1/events/{event}/people/groups/{group}/members`
- `DELETE /api/v1/events/{event}/people/groups/{group}/members/{membership}`
- `GET /api/v1/events/{event}/people/{person}`
- `PATCH /api/v1/events/{event}/people/{person}`
- `GET /api/v1/events/{event}/people/{person}/reference-photo-candidates`
- `POST /api/v1/events/{event}/people/{person}/reference-photos/gallery-face`
- `POST /api/v1/events/{event}/people/{person}/reference-photos/upload`
- `POST /api/v1/events/{event}/people/{person}/reference-photos/{referencePhoto}/primary`
- `GET /api/v1/events/{event}/people/review-queue`
- `POST /api/v1/events/{event}/people/review-queue/{reviewItem}/confirm`
- `POST /api/v1/events/{event}/people/review-queue/{reviewItem}/ignore`
- `POST /api/v1/events/{event}/people/review-queue/{reviewItem}/reject`
- `POST /api/v1/events/{event}/people/review-queue/{reviewItem}/merge`
- `POST /api/v1/events/{event}/people/review-queue/{reviewItem}/split`
- `POST /api/v1/events/{event}/people/relations`
- `PATCH /api/v1/events/{event}/people/relations/{relation}`
- `DELETE /api/v1/events/{event}/people/relations/{relation}`
- `GET /api/v1/events/{event}/media/{media}/people`
- `GET /api/v1/public/people-collections/{token}`

## Representatives AWS

- a selecao de faces representativas acontece localmente no modulo;
- o sync com AWS roda em fila dedicada e nunca bloqueia a confirmacao humana;
- merge, move e split de pessoas reencaminham o sync apenas para as identidades impactadas;
- o status de cada representative fica registrado em `event_person_representative_faces`.

## Grupos sociais locais

- grupos e memberships ficam 100% locais no banco transacional;
- `Modelo do evento` pode materializar seeds de grupos sem tocar AWS;
- coverage, momentos e entregas publicas por vinculo consomem essa camada sem mudar o ownership do modulo.

## Coverage e entregas

- coverage mede pessoa, par e grupo em vez de ficar preso a rosto isolado;
- pares obrigatorios podem gerar alertas operacionais para cerimonial e fotografia;
- a trilha publica derivada nasce apenas para colecoes `public_ready` e usa `share_token`;
- a superficie publica mostra apenas itens publicados, sem depender de AWS no hot path.
