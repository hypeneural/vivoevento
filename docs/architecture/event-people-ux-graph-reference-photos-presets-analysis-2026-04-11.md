# EventPeople UX, graph, reference photos and presets analysis - 2026-04-11

## Objetivo

Consolidar a melhor abordagem de UX/UI e logica de produto para a pagina `events/{id}/people`, com foco em quatro perguntas:

1. qual a melhor abordagem para visualizar pessoas e relacoes em grafo;
2. qual a melhor forma de mostrar avatar, relacoes e fotos da pessoa em uma pagina visual;
3. onde deve entrar o upload ou selecao de `Fotos de referencia`;
4. como estruturar `Sugestoes prontas` por tipo de evento sem ficar preso so ao casamento.

Este documento complementa:

- `docs/architecture/event-people-identity-relations-aws-strategy-2026-04-10.md`
- `docs/architecture/event-people-identity-relations-execution-plan-2026-04-10.md`
- `docs/architecture/event-people-governance-groups-coverage-moments-execution-plan-2026-04-11.md`

---

## Veredito executivo

### 1. A melhor abordagem para o grafo hoje e `React Flow`, nao uma lib nova

O projeto ja tem `@xyflow/react` instalado e em uso real em `apps/web/src/modules/events/journey/JourneyFlowCanvas.tsx`.

Isso muda a decisao.

No estado atual da stack, a melhor abordagem para o `Mapa de relacoes` e:

- reaproveitar `React Flow`;
- criar nodes customizados com avatar, nome, papel e indicadores;
- manter CRUD, filtros e detalhes fora do canvas, em painel lateral;
- tratar o grafo como visualizacao e navegacao, nao como formulario principal.

### 2. O grafo deve ser uma visualizacao secundaria da pagina de pessoas, nao a tela principal

`Pessoas` continua sendo a superficie operacional principal:

- nomear;
- confirmar;
- ajustar papel;
- editar relacoes;
- escolher foto principal;
- revisar sugestoes.

`Mapa de relacoes` entra como vista visual complementar para:

- entender nucleos;
- abrir detalhe da pessoa;
- enxergar proximidade e vinculacao;
- navegar por relacoes importantes.

Se o produto tentar transformar o grafo em tela principal cedo demais, ele piora a operacao do gestor.

### 3. `Fotos de referencia` hoje nao sobem nessa pagina

Validacao local do codigo:

- a pagina `apps/web/src/modules/event-people/EventPeoplePage.tsx` so exibe `selectedPerson.representative_faces`;
- `apps/web/src/modules/event-people/api.ts` nao tem endpoint de upload ou selecao manual de foto de referencia;
- `StoreEventPersonRequest` e `UpdateEventPersonRequest` nao aceitam `avatar_media_id` nem `avatar_face_id`.

Na pratica:

- hoje nao existe upload manual de foto de referencia em `events/{id}/people`;
- o card atual mostra so referencias derivadas das faces ja confirmadas no acervo;
- o avatar tambem nao tem seletor manual na pagina, apesar do dominio ja ter `avatar_media_id` e `avatar_face_id`.

### 4. `Sugestoes prontas` precisam deixar de ser uma lista rasa e virar `modelo do tipo de evento`

Hoje o catalogo de presets esta em `apps/api/app/Modules/EventPeople/Services/EventPeoplePresetCatalog.php`.

O estado atual e:

- casamento tem preset rico;
- corporativo e raso;
- `birthday`, `fifteen` e `graduation` caem todos no mesmo preset `social()`;
- `15 anos` ainda nao tem semantica propria de produto;
- `proprietario`, `socio`, `debutante`, `aniversariante` e outros papeis importantes nao cabem bem no enum atual so com `type`.

Conclusao:

- a pagina nao deve continuar com um card generico de atalho;
- ela precisa mostrar `Modelo do evento`;
- o backend precisa separar `papel de negocio` de `tipo tecnico`.

---

## O que foi validado localmente

## Pagina atual `events/{id}/people`

Validacao direta em `apps/web/src/modules/event-people/EventPeoplePage.tsx`:

- existe lista de pessoas a esquerda;
- existe formulario central de cadastro e relacoes;
- existe card `Sugestoes prontas`;
- existe card `Fotos de referencia`;
- o card `Fotos de referencia` e somente leitura;
- nao existe CTA para `Escolher da galeria`;
- nao existe CTA para `Enviar foto de referencia`;
- nao existe CTA para `Definir foto principal`.

## Backend atual

Validacao direta em `apps/api/app/Modules/EventPeople`:

- `avatar_media_id` e `avatar_face_id` existem no modelo `EventPerson`;
- `ConfirmEventPersonFaceAction` ja define avatar automaticamente na primeira confirmacao util;
- `MergeEventPeopleAction` reaproveita avatar da pessoa origem quando faz sentido;
- requests de store/update ainda nao expõem alteracao manual de avatar;
- nao existe recurso dedicado para `reference photos` manuais.

## Testes atuais

Validacao TDD executada em `2026-04-11`:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/EventPeoplePage.test.tsx src/modules/event-people/components/EventPeopleIdentitySheet.test.tsx src/modules/event-people/components/EventPeopleFaceOverlay.test.tsx
```

Resultado:

- `3 files passed`
- `6 tests passed`

Leitura pratica:

- a pagina dedicada existe e esta estavel;
- o fluxo guiado esta coberto;
- a recomendacao abaixo parte de uma base real, nao de uma tela imaginada.

---

## O que a documentacao oficial valida

As referencias abaixo foram revisadas em `2026-04-11`.

## 1. `React Flow` ja entrega o que a pagina visual precisa

As docs oficiais mostram:

- `custom nodes` em React;
- `NodeToolbar` para acoes contextuais;
- `group nodes` e `parentId` para subfluxos e agrupamento;
- exemplos oficiais de layout com `dagre`, `d3-hierarchy`, `d3-force` e `elkjs`;
- configuracao de acessibilidade com `ariaLabelConfig`.

Fontes oficiais:

- custom nodes: https://reactflow.dev/learn/customization/custom-nodes
- node toolbar: https://reactflow.dev/api-reference/components/node-toolbar
- auto layout: https://reactflow.dev/examples/layout/auto-layout
- sub flows: https://reactflow.dev/examples/grouping/sub-flows
- accessibility: https://reactflow.dev/learn/advanced-use/accessibility

Leitura pratica:

- o projeto nao precisa trocar de stack para ganhar uma visualizacao forte;
- o canvas pode usar nodes ricos com avatar, badge e acoes;
- grupos e nucleos podem nascer sem hack visual grosseiro;
- a equipe ja tem referencia interna no modulo de jornadas.

## 2. `Cytoscape.js` continua sendo boa alternativa, mas nao e a melhor primeira escolha aqui

As docs oficiais do Cytoscape mostram:

- foco em grafos e analise de rede;
- layouts dedicados;
- `compound nodes`;
- estilo com `background-image`;
- gestos e eventos de interacao.

Fonte oficial:

- https://js.cytoscape.org/

Leitura pratica:

- `Cytoscape.js` faz mais sentido quando o problema e primariamente `network graph`;
- ele e forte para layout de rede, analise de vizinhanca e volume maior;
- mas o nosso caso imediato e um painel React com nodes ricos, detalhes laterais, badges, filtros e acoes humanas;
- por isso `React Flow` encaixa melhor no primeiro corte.

## 3. A AWS reforca que `Fotos de referencia` devem ser poucas, boas e com uma pessoa dominante

As docs oficiais do Rekognition validam:

- `SearchUsersByImage` trabalha a partir da maior face da imagem;
- a AWS recomenda varias imagens da mesma pessoa para melhorar matching;
- a AWS recomenda pelo menos cinco imagens variadas da mesma pessoa em cenarios de busca em collection;
- qualidade de imagem, nitidez, yaw e pitch importam diretamente.

Fontes oficiais:

- `SearchUsersByImage`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_SearchUsersByImage.html
- `Guidance for indexing faces in common scenarios`: https://docs.aws.amazon.com/rekognition/latest/dg/guidance-index-faces.html
- `Recommendations for searching faces in a collection`: https://docs.aws.amazon.com/rekognition/latest/dg/recommendations-facial-input-images-search.html
- `Recommendations for facial comparison input images`: https://docs.aws.amazon.com/rekognition/latest/dg/recommendations-facial-input-images.html

Leitura pratica:

- upload manual de referencia nao deve aceitar foto de grupo;
- a UI precisa orientar para uma pessoa dominante;
- o sistema deve preferir `escolher da galeria` e usar upload manual como complemento;
- `Fotos de referencia` nao devem ser qualquer anexo, e sim um conjunto curado.

---

## Comparacao objetiva de abordagem para o grafo

| Criterio | React Flow | Cytoscape.js |
|---|---|---|
| Ja esta no projeto | Sim | Nao |
| Integra bem com componentes React ricos | Sim | Parcial |
| Nodes com avatar, badge e acoes | Muito bom | Bom, mas menos natural |
| Operacao tipo painel administrativo | Muito bom | Bom |
| Layout de rede organica puro | Bom | Muito bom |
| Edicao visual de relacoes | Muito bom | Medio |
| Reaproveitamento imediato de stack e testes | Muito alto | Baixo |

## Decisao recomendada

Escolher `React Flow` para `V1.5 / V2` do `Mapa de relacoes`.

Deixar `Cytoscape.js` como alternativa futura apenas se o produto passar a exigir:

- layout de rede muito mais automatico;
- grafo maior e mais denso;
- analise de vizinhanca e clustering como requisito principal.

---

## Melhor abordagem para a pagina visual de pessoas

## Regra principal

A pagina `events/{id}/people` nao deve virar um CRUD longo com um grafo jogado ao lado.

Ela deve virar um workspace com dois modos claros:

### 1. Modo `Pessoas`

Foco:

- cadastro;
- filtros;
- review;
- relacoes;
- fotos de referencia;
- foto principal.

Layout recomendado:

- coluna esquerda: lista, busca, filtros e status;
- coluna central: detalhe da pessoa;
- coluna direita: relacoes, fotos de referencia e sugestoes do tipo do evento.

### 2. Modo `Mapa de relacoes`

Foco:

- ver quem se conecta com quem;
- abrir pessoa;
- abrir relacao;
- navegar por nucleo;
- entender rapidamente o evento.

Layout recomendado:

- topo com filtros e busca;
- centro com canvas;
- lateral direita com detalhe da selecao;
- rodape opcional com fita de fotos quando houver pessoa ou par selecionado.

---

## Melhor abordagem para o grafo em si

## 1. Node representa pessoa, nao relacao

Cada node deve mostrar:

- avatar ou inicial;
- nome;
- papel humano da pessoa;
- indicador de importancia;
- quantidade de fotos;
- badges leves como `principal`, `sem foto principal`, `pendente`, `conflito`.

## 2. Edge representa vinculo, nao formulario

Cada edge deve carregar:

- tipo da relacao;
- se e confirmada ou sugerida;
- quantidade de fotos juntas, quando houver;
- intensidade local, quando esse dado existir.

Nao recomendar:

- colocar miniaturas de fotos em toda edge;
- renderizar texto grande em todas as conexoes;
- transformar o canvas num emaranhado de labels.

Melhor padrao:

- edge limpa por padrao;
- label curta no hover ou selecao;
- painel lateral mostra fotos do par selecionado.

## 3. O layout inicial deve ser estavel, nao fisico

Nao recomendo comecar com force layout "vivo" como padrao.

Motivo:

- grafos que ficam se mexendo pioram leitura operacional;
- o gestor perde referencia espacial;
- um layout bonito em demo pode ficar ruim no uso repetido.

Melhor abordagem:

- layout deterministico por `importance_rank`, `side` e `grupo`;
- pessoas principais mais centrais;
- familia e nucleos proximos em volta;
- fornecedores e equipe em faixa externa;
- opcao futura de `Explorar em modo dinamico` como modo secundario.

Em `React Flow`, isso pode nascer com:

- posicoes calculadas pelo proprio dominio;
- ou auto layout inicial com `elkjs` ou `d3-force`, mas persistindo o resultado;
- depois disso, sem animacao fisica continua.

## 4. Relacao selecionada deve abrir fotos, nao so texto

Ao selecionar uma pessoa:

- mostrar avatar maior;
- melhores fotos;
- fotos solo;
- pessoas relacionadas;
- acoes rapidas.

Ao selecionar uma relacao:

- mostrar os dois avatares;
- tipo da relacao;
- contagem de fotos juntos;
- melhores fotos do par;
- CTA `abrir fotos juntos`.

Esse e o ponto que transforma o grafo em pagina visual de verdade.

---

## Onde entram `Fotos de referencia`

## Resposta direta

Hoje, em `events/{id}/people`, nao existe lugar para subir imagem manual da pessoa.

O card `Fotos de referencia` atual apenas lista referencias derivadas das faces ja confirmadas no acervo.

## Melhor abordagem de produto

`Fotos de referencia` devem ficar no detalhe da pessoa, na mesma pagina, mas com dois fluxos separados:

### Fluxo A - principal

`Escolher da galeria`

Uso:

- selecionar uma face ja confirmada em foto do evento;
- marcar como `Foto principal`;
- marcar como `Usar como referencia`.

Esse deve ser o fluxo dominante porque:

- respeita o acervo real do evento;
- reaproveita faces ja detectadas;
- conversa melhor com a curadoria local;
- reduz friccao.

### Fluxo B - complementar

`Enviar foto de referencia`

Uso:

- foto ancora quando a pessoa importante ainda aparece pouco no acervo;
- caso de noiva, debutante, proprietario, palestrante, etc.;
- reforco de matching para pessoas centrais.

Regras recomendadas:

- aceitar so uma pessoa dominante;
- validar nitidez minima;
- validar enquadramento sem obstrucao forte;
- recusar foto de grupo;
- recusar arquivo sem face utilizavel.

## Como a UI deve ficar

No card `Fotos de referencia`, incluir:

- `Escolher da galeria`
- `Enviar foto de referencia`
- `Definir foto principal`
- `Remover referencia`

Separar visualmente:

- `Foto principal`
- `Fotos de referencia`

Motivo:

- avatar resolve navegacao visual;
- referencia resolve matching e curadoria;
- as duas coisas se cruzam, mas nao sao exatamente o mesmo conceito.

## Ajuste de modelagem recomendado

Nao forcar upload manual dentro de `event_person_representative_faces`.

Melhor desenho:

- manter `representative_faces` como conjunto tecnico derivado;
- criar uma camada humana de `reference_photos`;
- deixar o seletor tecnico decidir quais referencias viram representatives.

Estrutura sugerida:

- `event_person_reference_photos`

Campos iniciais:

- `id`
- `event_id`
- `event_person_id`
- `source`
- `event_media_id`
- `event_media_face_id`
- `reference_upload_media_id`
- `purpose`
- `status`
- `quality_score`
- `is_primary_avatar`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

Valores iniciais:

- `source`: `event_face`, `manual_upload`
- `purpose`: `avatar`, `matching`, `both`
- `status`: `active`, `archived`

---

## Melhor abordagem para `Sugestoes prontas`

## Problema atual

Hoje `Sugestoes prontas` funciona como atalho de cadastro.

Isso resolve pouco.

O produto precisa de um conceito mais forte:

`Modelo do tipo do evento`

## O que esse modelo precisa entregar

Nao so `pessoas`.

Tambem:

- papeis sugeridos;
- relacoes iniciais;
- grupos futuros;
- seeds para coverage importante;
- prioridade editorial.

## Decisao de modelagem que faz mais diferenca

Separar:

- `type`
- `role_key`
- `role_label`

### `type`

Continua amplo e tecnico o suficiente para logica:

- `mother`
- `father`
- `friend`
- `vendor`
- `executive`
- `speaker`

### `role_key`

Captura o papel especifico do contexto:

- `debutante`
- `mae_da_debutante`
- `pai_da_debutante`
- `proprietario`
- `socio`
- `aniversariante`
- `formando`
- `patrono`

### `role_label`

E a copy real mostrada no front:

- `Debutante`
- `Mae da debutante`
- `Proprietario`
- `Socio`

Sem essa separacao, o preset vai continuar pobre ou vai explodir o enum de `type`.

## Melhor UX para o card

Trocar `Sugestoes prontas` por:

`Modelo do evento`

Com secoes:

- `Pessoas principais`
- `Familia`
- `Corte ou amigos`
- `Equipe e fornecedores`

Cada item pode ter:

- botao `Adicionar`
- badge `Ja existe`
- explicacao curta do papel

Tambem faz sentido um CTA:

- `Aplicar pacote inicial`

Esse CTA deve:

- adicionar so quem ainda nao existe;
- evitar duplicatas por `role_key` ou nome padrao;
- respeitar o tipo do evento.

---

## Presets recomendados por tipo de evento

## Casamento

Pessoas principais:

- Noiva
- Noivo

Familia:

- Mae da noiva
- Pai da noiva
- Mae do noivo
- Pai do noivo
- Irmao(irma)

Corte e proximos:

- Madrinha
- Padrinho
- Daminha
- Pajem
- Amigo(a) proximo(a)

Equipe e fornecedores:

- Cerimonialista
- Fotografo
- Banda ou DJ

## 15 anos

Pessoas principais:

- Debutante

Familia:

- Mae da debutante
- Pai da debutante
- Irmao(irma)
- Avos

Corte e proximos:

- Madrinha
- Padrinho
- Melhor amiga
- Dama de honra
- Amigos

Equipe e fornecedores:

- Cerimonialista
- Fotografo
- DJ ou banda

## Aniversario

Pessoas principais:

- Aniversariante

Familia:

- Mae
- Pai
- Irmao(irma)
- Avos

Convidados importantes:

- Melhor amigo(a)
- Familia proxima

Equipe e fornecedores:

- Fotografo
- Decorador
- Buffet

## Corporativo

Pessoas principais:

- Proprietario
- Socio
- Diretor(a)
- Host

Papeis do evento:

- Palestrante
- Executivo
- Equipe
- Patrocinador
- Imprensa

Operacao:

- Fotografo
- Fornecedor

## Feira

Pessoas principais:

- Responsavel pelo stand
- Diretor(a)
- Socio

Papeis:

- Expositor
- Equipe comercial
- Imprensa
- Patrocinador
- Visitante VIP

## Formatura

Pessoas principais:

- Formando(a)

Familia:

- Mae
- Pai
- Irmao(irma)
- Familia proxima

Academico:

- Patrono
- Paraninfo
- Professor
- Amigos da turma

Operacao:

- Fotografo
- Cerimonial

---

## Melhor abordagem de implementacao

## UX1 - separar visualmente `Pessoa`, `Foto principal` e `Fotos de referencia`

Backlog:

- expor avatar manual no backend;
- criar CTA de selecao da galeria;
- criar CTA de upload manual;
- diferenciar `avatar` de `referencia`.

## UX2 - criar endpoint proprio para o grafo

Endpoint sugerido:

- `GET /api/v1/events/{event}/people/graph`

Payload sugerido:

- `people`
- `relations`
- `groups`
- `stats`
- `filters`

Cada pessoa deve trazer:

- `id`
- `display_name`
- `role_label`
- `type`
- `side`
- `avatar_url`
- `importance_rank`
- `media_count`
- `published_media_count`
- `status`

Cada relacao deve trazer:

- `id`
- `person_a_id`
- `person_b_id`
- `relation_type`
- `directionality`
- `source`
- `strength`
- `co_photo_count`

## UX3 - criar um canvas dedicado com `React Flow`

Componentes sugeridos:

- `EventPeopleGraphView.tsx`
- `EventPeopleGraphNode.tsx`
- `EventPeopleGraphEdge.tsx`
- `EventPeopleGraphSidebar.tsx`

Recursos recomendados:

- `ReactFlowProvider`
- custom nodes
- custom edges
- `NodeToolbar`
- `Panel`
- `fitView`
- busca e filtros locais

## UX4 - refatorar presets para pacotes por evento

Em vez de:

- `wedding()`
- `corporate()`
- `social()`
- `generic()`

Passar para:

- `wedding()`
- `fifteen()`
- `birthday()`
- `corporate()`
- `fair()`
- `graduation()`
- `generic()`

E cada pacote deve devolver:

- `people`
- `relations`
- `groups`
- `coverage_targets`

Mesmo que `groups` e `coverage_targets` ainda nao sejam usados na UI, eles ja deixam o catalogo pronto para as proximas fases.

---

## Bateria de testes recomendada

## Backend

- `EventPeoplePresetCatalogTest`
- `EventPeopleGraphQueryTest`
- `PersonReferencePhotosTest`
- `SetEventPersonAvatarActionTest`
- `UploadEventPersonReferencePhotoActionTest`

Comando sugerido:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople tests/Unit/EventPeople
```

## Frontend

- `EventPeopleGraphView.test.tsx`
- `EventPeopleGraphSidebar.test.tsx`
- `EventPeopleReferencePhotosCard.test.tsx`
- `EventPeoplePresetCard.test.tsx`

Comando sugerido:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people
```

## Criterios de aceite

- o gestor entende a diferenca entre foto principal e foto de referencia;
- a pagina permite escolher referencia sem linguagem tecnica;
- o grafo abre rapido e nao substitui o fluxo operacional principal;
- o node mostra avatar, nome e papel com leitura imediata;
- a relacao selecionada abre fotos do par, nao so metadado;
- `15 anos`, `corporativo` e `formatura` deixam de depender de preset generico;
- o front continua sem expor termos como `representatives`, `AWS user` e `hot path`.

---

## Conclusao

O melhor caminho para a UX/UI de pessoas nao e jogar uma lib de grafo em cima da pagina atual.

O caminho forte e:

1. usar `React Flow`, porque ele ja esta no projeto e encaixa melhor em nodes ricos com avatar e acoes;
2. manter `Pessoas` como superficie operacional principal e `Mapa de relacoes` como vista complementar;
3. separar `foto principal` de `fotos de referencia`;
4. adicionar upload manual de referencia como fluxo secundario, nunca como fluxo unico;
5. evoluir `Sugestoes prontas` para `Modelo do evento`, com presets reais por tipo de evento;
6. separar `role_key` de `type`, para o front falar a lingua do evento sem destruir a modelagem.

Em uma frase:

**a pagina de pessoas precisa sair de um CRUD com atalhos e virar um workspace visual com catalogo, referencia, mapa e papeis reais do evento.**
