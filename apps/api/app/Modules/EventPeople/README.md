# EventPeople Module

## Responsabilidade

Este modulo cria a camada de dominio de pessoas do evento acima da trilha tecnica de `FaceSearch`.

Ele e responsavel por:

- pessoa canonica do evento;
- atribuicao entre rosto detectado e pessoa;
- relacoes declaradas entre pessoas;
- coocorrencia inferida por evento;
- read models locais para lista, detalhe, review queue e busca por nome;
- base futura para grupos sociais, cobertura importante e entregas por relacao.

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

## Rotas iniciais

- `GET /api/v1/events/{event}/people`
- `GET /api/v1/events/{event}/people/{person}`
- `GET /api/v1/events/{event}/people/review-queue`
- `GET /api/v1/events/{event}/media/{media}/people`
