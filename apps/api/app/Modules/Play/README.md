# Play Module

## Responsabilidade

Motor de jogos publicos por evento, com catalogo, configuracao por evento, assets, sessoes, ranking, analytics e realtime.

O modulo hoje atende:

- manager administrativo por evento;
- manifest publico do `Play`;
- sessoes publicas de jogo;
- ranking por jogo;
- analytics por sessao e por jogo;
- ciclo inicial de `heartbeat`, `resume` e `abandoned`.

## Stack do modulo

- Laravel 12
- PostgreSQL
- Redis
- broadcasting compativel com Reverb/Pusher

## Entidades principais

- `PlayGameType`
- `PlayEventGame`
- `PlayGameAsset`
- `PlayGameSession`
- `PlayGameMove`
- `PlayGameRanking`
- `EventPlaySetting`

## Regras atuais do launch

- `memory` vai primeiro para producao;
- `puzzle` entra em seguida;
- launch publico `portrait-first`;
- score oficial calculado no backend;
- fallback publico usa apenas fotos `published`;
- `memory` limitado a `pairsCount` `6`, `8` e `10`;
- `puzzle` limitado a `gridSize` `2x2` e `3x3`.

## Endpoints principais

### Administrativo

- `GET /api/v1/play/catalog`
- `GET /api/v1/events/{event}/play`
- `GET /api/v1/events/{event}/play/analytics`
- `GET /api/v1/events/{event}/play/settings`
- `PATCH /api/v1/events/{event}/play/settings`
- `POST /api/v1/events/{event}/play/games`
- `PATCH /api/v1/events/{event}/play/games/{playGame}`
- `DELETE /api/v1/events/{event}/play/games/{playGame}`
- `GET /api/v1/events/{event}/play/games/{playGame}/assets`
- `POST /api/v1/events/{event}/play/games/{playGame}/assets`

### Publico

- `GET /api/v1/public/events/{event:slug}/play`
- `GET /api/v1/public/events/{event:slug}/play/{gameSlug}`
- `GET /api/v1/public/events/{event:slug}/play/{gameSlug}/ranking`
- `GET /api/v1/public/events/{event:slug}/play/{gameSlug}/last-plays`
- `POST /api/v1/public/events/{event:slug}/play/{gameSlug}/sessions`
- `POST /api/v1/public/play/sessions/{sessionUuid}/moves`
- `POST /api/v1/public/play/sessions/{sessionUuid}/heartbeat`
- `POST /api/v1/public/play/sessions/{sessionUuid}/resume`
- `GET /api/v1/public/play/sessions/{sessionUuid}/analytics`
- `POST /api/v1/public/play/sessions/{sessionUuid}/finish`

## Estrutura interna relevante

- `Actions/`
  Operacoes de escrita de jogos e assets.
- `DTOs/`
  DTOs de start, moves, finish, heartbeat e resume.
- `Http/Controllers/`
  Controllers administrativos e publicos.
- `Http/Requests/`
  Validacao de payloads por endpoint.
- `Http/Resources/`
  Contratos de resposta da API.
- `Models/`
  Models das entidades de runtime e configuracao.
- `Services/`
  Catalogo, assets, sessoes, ranking, analytics, score e validacao de settings.
- `Support/`
  Validadores por tipo de jogo e calculadoras de score.
- `routes/api.php`
  Rotas do modulo.

## Estado atual

Ja entregue:

- validacao de `settings` por tipo de jogo;
- score autoritativo no backend;
- analytics por jogo e por sessao;
- leaderboard em tempo real;
- `heartbeat`, `resume` e `abandoned`;
- payload publico com `resumeToken`, `sessionSeed` e `restore`.

Pendencias principais:

- refino final de UX do `memory`;
- calibracao final do `puzzle` em devices reais;
- replay/debug mais profundo de sessao no admin;
- analytics adicionais de conectividade e PWA.

## Referencia de produto

O direcionamento arquitetural e o backlog executavel do launch estao em:

- `docs/architecture/play-games-discovery.md`
