# Hub/Gallery AI Builder Analysis

## Objetivo

Documentar o benchmark encontrado em `C:\Users\Usuario\Desktop\i.a`, explicar como ele realmente funciona na criacao de sites por IA, como trata "agentes", preview e publicacao, e avaliar o que faz sentido importar para o `Hub` e para a `Gallery` do Evento Vivo.

## Escopo analisado

- `C:\Users\Usuario\Desktop\i.a\Builder`
- `C:\Users\Usuario\Desktop\i.a\Install`
- Estado atual dos modulos `Hub`, `Gallery` e `Events` em `apps/api` e `apps/web`

## Resumo executivo

O sistema analisado nao e um "swarm" de varios agentes independentes construindo um site em paralelo. O nucleo e um agente principal orientado a ferramentas, rodando em um builder server em Go, com um painel/orquestrador em Laravel + React.

Ele funciona assim:

1. o painel Laravel recebe prompt, template, historico, configuracao do provedor de IA e capacidades do projeto;
2. o builder em Go cria ou reutiliza um workspace local;
3. um agente principal chama ferramentas para ler, criar e editar arquivos do projeto;
4. o builder valida build/integracao, resume contexto antigo quando necessario e persiste memoria do site;
5. o resultado buildado vira um artefato estatico servido como preview;
6. "publicar" significa expor esse artefato por subdominio ou dominio customizado, nao fazer deploy separado em outra plataforma.

Para o Evento Vivo, a principal conclusao e esta:

- o benchmark e forte como referencia de orquestracao, validacao, memoria, revisao e preview/publish;
- mas a estrategia mais aderente para nossa stack nao e deixar uma IA editar arquivos React arbitrarios do monorepo;
- o melhor encaixe e usar IA para gerar e ajustar configuracao tipada de layout, copy e blocos do `Hub`, e depois aplicar a mesma ideia a uma `Gallery` orientada a blocos.

## Arquitetura real do benchmark

### 1. Plano de controle

Fica em `Install`, com Laravel e React.

Responsabilidades principais:

- autenticar usuario e plano;
- selecionar builder server;
- selecionar provedor/modelo de IA;
- armazenar historico da conversa;
- receber webhooks de progresso e persistir eventos;
- pedir build do workspace;
- extrair o artefato gerado para preview;
- servir preview e site publicado;
- administrar subdominios e dominios customizados.

Arquivos centrais observados:

- `Install/app/Services/BuilderService.php`
- `Install/app/Http/Controllers/BuilderProxyController.php`
- `Install/app/Http/Controllers/BuilderWebhookController.php`
- `Install/app/Http/Controllers/ProjectPublishController.php`
- `Install/app/Http/Controllers/ProjectCustomDomainController.php`
- `Install/app/Http/Controllers/PreviewController.php`
- `Install/app/Http/Controllers/PublishedProjectController.php`
- `Install/app/Console/Commands/ProvisionCustomDomainSsl.php`
- `Install/app/Models/Project.php`
- `Install/app/Models/Builder.php`
- `Install/app/Models/AiProvider.php`

### 2. Plano de execucao

Fica em `Builder`, escrito em Go.

Responsabilidades principais:

- manter workspaces locais por projeto;
- inicializar template;
- rodar o loop do agente;
- expor ferramentas para editar arquivos;
- validar build e integracao;
- gerar revisoes, undo e redo;
- resumir historico longo;
- transmitir eventos em realtime e por webhook.

Arquivos centrais observados:

- `Builder/src/internal/api/router.go`
- `Builder/src/internal/api/handlers.go`
- `Builder/src/internal/agent/runner.go`
- `Builder/src/internal/agent/session.go`
- `Builder/src/internal/agent/tools.go`
- `Builder/src/internal/executor/executor.go`
- `Builder/src/internal/executor/file.go`
- `Builder/src/internal/executor/build.go`
- `Builder/src/internal/executor/integration.go`
- `Builder/src/internal/revision/manager.go`
- `Builder/src/internal/summarizer/summarizer.go`
- `Builder/src/prompts/system.md`
- `Builder/src/prompts/compact.md`

### 3. Templates

O sistema trabalha com templates zipados, baixados do Laravel pelo builder em Go.

Cada template carrega um `template.json` com metadados como:

- estrutura de arquivos;
- paginas disponiveis;
- componentes customizados;
- componentes `shadcn`;
- padrao de rotas;
- exemplos de uso;
- informacoes de estilo e composicao.

O ponto importante aqui nao e so "ter template". O importante e que o agente nao parte do zero: ele recebe um contrato declarativo do template antes de editar arquivos.

## Como os "agentes" realmente funcionam

### O que existe

Existe um agente principal por sessao/workspace.

Esse agente:

- recebe prompt do usuario;
- recebe historico;
- recebe capacidades disponiveis;
- usa ferramentas para agir sobre o projeto;
- escreve arquivos;
- valida build;
- decide quando pedir build final.

### O que nao existe

Nao foi identificado um sistema real de varios agentes especialistas com ownership separado do mesmo workspace, por exemplo:

- um planner independente;
- um designer independente;
- um coder independente;
- um reviewer independente;
- um publisher independente.

### O que existe como componentes auxiliares

O benchmark tem pecas auxiliares que, do lado de produto, podem parecer "outros agentes", mas tecnicamente sao funcoes de suporte:

- summarizer para compactar historico longo;
- hybrid streamer para eventos realtime;
- webhook notifier para persistencia;
- revision manager para snapshots;
- circuit breaker e recovery para sessao instavel;
- verificadores de build e integracao.

### Conclusao sobre agentes

O benchmark deve ser entendido como um sistema agentic de agente unico com ferramentas, memoria, revisoes e recuperacao. Isso e muito mais proximo de um "coding agent controlado" do que de um "multi-agent system".

## Como ele cria o site

Fluxo de ponta a ponta:

1. o usuario cria um projeto no painel Laravel;
2. o frontend chama o proxy do builder;
3. o Laravel monta o contexto da execucao:
   - prompt do usuario;
   - historico compactado ou completo;
   - configuracao do modelo;
   - template selecionado;
   - tema base;
   - capacidades do projeto;
   - webhook de retorno;
4. o builder em Go cria ou reaproveita um workspace identificado pelo projeto;
5. se o workspace estiver vazio, ele baixa e extrai o template inicial;
6. o runner monta o prompt do sistema e inicia o loop do agente;
7. o agente chama ferramentas como:
   - `listFiles`
   - `readFile`
   - `createFile`
   - `editFile`
   - `searchFiles`
   - `verifyBuild`
   - `verifyIntegration`
   - `writeDesignIntelligence`
   - `updateSiteMemory`
8. o executor aplica mudancas nos arquivos do workspace e tenta preservar seguranca estrutural;
9. o builder envia eventos de progresso e mensagens para o Laravel;
10. ao final, se necessario, o builder roda `npm run build`, compacta `dist/` e expoe o artefato para download;
11. o Laravel baixa o zip, extrai para a pasta de preview e injeta configuracoes runtime no `index.html`;
12. o preview passa a ser servido pelo proprio app Laravel.

## Ferramentas e guardrails do benchmark

Pontos fortes observados:

- edicao de arquivo com validacao de TypeScript via `esbuild`;
- arquivos protegidos que nao devem ser mexidos livremente;
- verificacao de integracao entre paginas, imports e rotas;
- revisoes com limite de snapshots e suporte a undo/redo;
- memoria persistente do site em JSON;
- inteligencia de design persistente em JSON;
- compactacao automatica do historico quando a janela cresce;
- monitoramento de credito/token durante a execucao;
- tentativa de recovery em erros conhecidos de infraestrutura.

Esses guardrails sao mais importantes do que o "chat" em si. Eles reduzem o risco de a IA degradar o projeto ao longo de varias iteracoes.

## Como preview e publicacao funcionam

### Preview

O build final fica salvo como artefato estatico em storage.

Padrao identificado:

- preview autenticado: `/preview/{project}`
- preview limpo da app: `/app/{project}`

O arquivo `index.html` do build recebe ajuste de `base href` para funcionar dentro da rota de preview.

### Publicacao

No benchmark, publicar nao significa subir para Vercel, Netlify ou rodar um pipeline CI/CD separado.

Publicar significa:

1. marcar o projeto como publicado;
2. resolver um host publico;
3. servir o artefato buildado pelo mesmo backend.

Existem dois modos:

- subdominio da plataforma;
- dominio customizado.

### Dominio customizado

O fluxo de dominio customizado observado e:

1. usuario informa dominio;
2. sistema pede configuracao de DNS;
3. backend verifica A record;
4. um comando agendado provisiona SSL com `certbot`;
5. o sistema escreve configuracao Nginx;
6. Nginx passa a apontar esse host para o artefato publicado.

### Conclusao sobre publish

O modelo e "serve static artifact by host routing", nao "deploy application to a separate runtime".

## Diferenca estrutural para o Evento Vivo

Essa diferenca e a mais importante de toda a analise.

No benchmark:

- cada projeto vira uma mini aplicacao React buildada em um workspace proprio;
- preview e publicacao dependem de artefato estatico gerado;
- a IA trabalha sobre arquivos do projeto.

No Evento Vivo:

- `Hub` e `Gallery` ja sao superficies publicas dentro da app principal;
- as rotas publicas sao estaveis e previsiveis:
  - `apps/web/src/App.tsx` expoe `/e/:slug` e `/e/:slug/gallery`;
  - `apps/api/app/Modules/Events/Models/Event.php` gera `publicHubUrl()` e `publicGalleryUrl()`;
- o `Hub` ja e orientado a configuracao tipada;
- a `Gallery` publica ainda e uma pagina fixa consumindo midias aprovadas/publicadas;
- publicacao hoje esta ligada a modulo habilitado, status do evento e estado das midias, nao a build de workspace.

## Estado atual do Hub no Evento Vivo

O `Hub` atual ja tem uma base muito boa para IA orientada a configuracao.

Pontos observados:

- backend modular em `apps/api/app/Modules/Hub`;
- configuracao persistida em `EventHubSetting.builder_config_json`;
- normalizacao tipada em `HubBuilderPresetRegistry`;
- payload admin/publico montado por `HubPayloadFactory`;
- renderer dedicado em `apps/web/src/modules/hub/HubRenderer.tsx`;
- editor admin em `apps/web/src/modules/hub/HubPage.tsx`;
- rotas publicas e tracking ja existentes;
- presets de layout, tema, blocos e tokens ja definidos em `apps/web/src/modules/hub/hub-builder.ts`.

Em termos praticos, o `Hub` ja tem o equivalente conceitual do `template.json` do benchmark:

- contrato de layout;
- tipos de bloco conhecidos;
- tokens de tema;
- limites do que pode ou nao ser renderizado.

Isso e excelente para uma integracao de IA segura.

## Estado atual da Gallery no Evento Vivo

A `Gallery` publica atual e bem mais simples.

Pontos observados:

- backend modular em `apps/api/app/Modules/Gallery`;
- endpoint publico que lista midias aprovadas/publicadas;
- pagina publica em `apps/web/src/modules/gallery/PublicGalleryPage.tsx`;
- layout publico ainda fixo, basicamente hero simples + masonry de fotos;
- nao existe hoje um `builder_config` tipado equivalente ao do `Hub`.

Conclusao:

- o `Hub` esta pronto para IA orientada a configuracao;
- a `Gallery` ainda precisa primeiro virar um produto orientado a layout/preset/bloco antes de receber IA de forma consistente.

## O que vale importar do benchmark

### 1. Geracao orientada a contrato, nao orientada a arquivo arbitrario

Para nossa stack, o principio certo e:

- IA recebe um schema conhecido;
- IA devolve JSON validado;
- backend normaliza;
- frontend renderiza com componentes ja existentes.

Isso reaproveita o melhor do benchmark sem importar o risco de um builder solto editando o monorepo.

### 2. Memoria estruturada

O benchmark guarda `memory.json` e `design-intelligence.json`.

Equivalente util para nos:

- preferencias visuais do evento;
- tom de comunicacao;
- publico esperado;
- restricoes da marca;
- CTAs priorizados;
- estilo de galeria preferido;
- referencias aprovadas.

Isso pode viver em JSON por evento ou por organizacao.

### 3. Preview vs publish

O conceito e otimo, mas a implementacao precisa mudar para nossa arquitetura.

Em vez de preview de artefato, o Evento Vivo pode trabalhar com:

- `draft_builder_config_json`
- `published_builder_config_json`
- preview do draft no admin
- publish copiando draft para a versao publica

Isso traz o beneficio do benchmark sem exigir pipeline de build.

### 4. Revisoes, undo e redo

Vale muito a pena para mudancas de layout feitas por IA ou por humano.

No nosso caso, revisao deve versionar:

- config do layout;
- copy;
- ordem de blocos;
- lista de CTA;
- tokens de tema.

### 5. Streaming de progresso

Tem aderencia alta com a stack atual porque ja usamos realtime em outras frentes.

Uma experiencia boa para o editor seria:

- "analisando evento"
- "montando proposta visual"
- "gerando copy"
- "validando blocos"
- "pronto para revisar"

### 6. Validacao deterministica apos IA

Essa e obrigatoria.

Nada do retorno da IA deve ir direto para producao sem:

- validacao de schema;
- normalizacao de enums;
- filtros de URL e assets;
- checagem de blocos permitidos;
- checagem de modulos habilitados no evento.

### 7. Capabilities explicitas

No benchmark, o builder recebe `project_capabilities`.

Para o Evento Vivo, a IA deve receber algo equivalente:

- modulo `live` ativo ou nao;
- `wall` ativo ou nao;
- `play` ativo ou nao;
- `find_me` ativo ou nao;
- patrocinadores disponiveis ou nao;
- data/local do evento;
- identidade visual do evento;
- quantidade de midias publicadas;
- blocos permitidos para aquela superficie.

Isso evita sugestoes incoerentes.

## O que nao vale importar diretamente

### 1. Builder server separado em Go, agora

Para nosso problema atual, isso adicionaria complexidade operacional cedo demais:

- mais um servico;
- mais um runtime;
- workspaces temporarios;
- pipeline de build de apps;
- problemas extras de storage e limpeza.

### 2. IA editando arquivos do monorepo do produto

Isso e arriscado para `Hub` e `Gallery`, porque nossa necessidade hoje e personalizacao controlada de superficies existentes, nao geracao livre de aplicacoes inteiras.

### 3. Publicacao por artefato estatico por evento

Nao conversa bem com a forma como o Evento Vivo ja publica suas superficies publicas.

Hoje nossa URL publica e um contrato do produto, nao o resultado de um build isolado.

### 4. Complexidade de billing/credits/byok agora

O benchmark tem logica de creditos, modelos por usuario e selecao de builder.

Isso so faz sentido se formos vender um builder generico como produto. Nao e prerequisito para elevar `Hub` e `Gallery`.

## Recomendacao de arquitetura para a stack atual

### Direcao recomendada

Implementar um "AI-assisted layout composer" orientado a configuracao, com ownership principal dentro dos modulos `Hub` e `Gallery`, e infraestrutura comum de IA em `Shared`.

### Principio-chave

A IA nao deve "criar um site" no sentido do benchmark.

Ela deve:

- compor layout;
- sugerir copy;
- priorizar CTAs;
- preencher blocos;
- ajustar tema;
- produzir variacoes;
- sempre devolvendo dados tipados que os renderers atuais entendem.

### Como isso encaixa no Hub

Primeira fase recomendada:

1. adicionar uma acao tipo `GenerateHubAiDraftAction`;
2. montar contexto do evento a partir de `Event`, `EventPublicLinksService` e `HubPayloadFactory`;
3. enviar para um modelo a descricao do evento e o schema do `builder_config`;
4. receber:
   - `headline`
   - `subheadline`
   - `welcome_text`
   - `button_style`
   - `builder_config`
   - ordem de CTAs preset
   - sugestoes de itens sociais e patrocinadores
5. validar tudo no backend;
6. salvar em draft;
7. renderizar preview imediato com `HubRenderer`.

### Como isso encaixa na Gallery

Para a `Gallery`, a recomendacao nao e pular direto para IA.

Antes disso, precisamos de um modulo de layout publico da galeria com contrato proprio, por exemplo:

- `hero`
- `featured_strip`
- `masonry_grid`
- `upload_cta`
- `find_me_cta`
- `stats_bar`
- `sponsor_strip`

Depois disso, a IA pode sugerir:

- ordem dos blocos;
- tema visual;
- copy de hero;
- densidade do grid;
- destaque para upload ou busca facial;
- enfatizar patrocinadores ou branding do evento.

## Estrutura sugerida por modulo

### Backend compartilhado

Em `apps/api/app/Shared`, faz sentido introduzir algo como:

- `Shared/AI/Contracts`
- `Shared/AI/Services`
- `Shared/AI/DTOs`
- `Shared/AI/Support`

Responsabilidades:

- adaptador de provedor de IA;
- schema/response coercion;
- logging de geracao;
- retries e timeouts;
- filtros de seguranca.

### Modulo Hub

Responsabilidades do modulo `Hub`:

- montar contexto de negocio do hub;
- definir schema permitido;
- validar retorno;
- salvar draft e publicar;
- versionar revisoes.

Classes candidatas:

- `Actions/GenerateHubAiDraftAction.php`
- `Actions/PublishHubDraftAction.php`
- `Queries/BuildHubAiContextQuery.php`
- `Services/HubAiPromptService.php`
- `Services/HubRevisionService.php`
- `Http/Controllers/EventHubAiController.php`

### Modulo Gallery

Responsabilidades do modulo `Gallery`:

- definir o contrato da galeria publica configuravel;
- montar contexto com estatisticas e superficies publicas;
- aplicar IA sobre esse contrato;
- manter publicacao isolada.

Classes candidatas:

- `Models/EventGallerySetting.php`
- `Actions/GenerateGalleryAiDraftAction.php`
- `Actions/PublishGalleryDraftAction.php`
- `Support/GalleryBuilderPresetRegistry.php`
- `Services/GalleryRevisionService.php`

## Draft e publish recomendados para nos

O benchmark acerta no conceito de separar area de edicao da area publicada. Isso tambem vale aqui.

Modelo recomendado:

1. admin edita ou gera rascunho;
2. preview mostra o draft;
3. publicacao troca o snapshot publico;
4. rollback volta para revisao anterior.

Campos possiveis:

- `draft_builder_config_json`
- `published_builder_config_json`
- `draft_content_json`
- `published_content_json`
- `published_revision_id`

Isso vale mais para o `Hub` no curto prazo e para a `Gallery` quando ela ganhar builder proprio.

## Agentes de IA que fariam sentido para nos

Se a pergunta for "precisamos de varios agentes?", a resposta curta e: nao no inicio.

Para o Evento Vivo, o desenho mais solido no curto prazo e:

- 1 chamada principal de geracao;
- 1 etapa deterministica de validacao;
- 1 persistencia em draft;
- 1 preview;
- 1 publish explicito.

Se futuramente quisermos elevar isso, os papeis mais uteis seriam:

- agente de composicao visual;
- agente de copy;
- validador de contrato;
- refinador de variacoes.

Mesmo assim, eu manteria apenas um agente criativo e deixaria a validacao como logica deterministica de backend.

## Roadmap recomendado

### Fase 1 - Hub AI draft

- gerar rascunho de `Hub` a partir de contexto do evento;
- salvar em draft;
- preview no editor;
- publicar manualmente;
- versionar revisoes.

### Fase 2 - Chat de ajuste do Hub

- "deixe mais premium"
- "troque para casamento romantico"
- "destaque galeria e upload"
- "reduza texto e aumente patrocinadores"

Cada pedido continua gerando JSON validado, nao codigo.

### Fase 3 - Gallery builder

- criar contrato tipado da galeria publica;
- criar renderer configuravel;
- separar draft/publicado;
- so entao adicionar IA.

### Fase 4 - Streaming e memoria

- progresso em tempo real;
- preferencias persistentes do evento/organizacao;
- historico resumido de ajustes;
- biblioteca de presets gerados/aprovados.

## Riscos a evitar

- transformar `Hub` ou `Gallery` em CMS generico;
- misturar geracao de layout com deploy de aplicacao inteira;
- permitir que IA escreva codigo livre do frontend principal;
- pular schema e validacao forte;
- levar automaticamente toda mudanca de IA para a URL publica;
- introduzir builder multi-servico antes de provar valor com geracao de config.

## Conclusao

O benchmark de `C:\Users\Usuario\Desktop\i.a` e tecnicamente interessante, mas o valor maior dele para o Evento Vivo nao esta em copiar o builder em Go nem a publicacao por artefato.

O valor real esta em importar os principios:

- IA com contexto forte;
- contrato explicito;
- memoria estruturada;
- preview separado de publish;
- revisao/versionamento;
- streaming de progresso;
- validacao dura apos a geracao.

Aplicado a nossa stack atual, o caminho mais aderente e:

1. tratar o `Hub` como primeira superficie AI-assisted;
2. usar a IA para gerar/configurar blocos e copy, nao para editar codigo arbitrario;
3. adicionar draft/publish/revisao no `Hub`;
4. depois modelar a `Gallery` como builder tipado e repetir o padrao.

Essa abordagem preserva a arquitetura por modulos do monorepo, reduz risco operacional e aproveita o que o Evento Vivo ja tem de melhor hoje.

## Validacao adicional - editor simples com IA guiada

### Direcao validada

A direcao proposta para o produto esta correta: o editor deve ser simples para usuario leigo, com presets prontos e um modo conversacional opcional, mas sempre preso a blocos e contratos existentes.

Isso significa que o usuario pode dizer:

- "quero um fundo verde escuro com dourado, vibe casamento elegante"
- "deixa mais clean e corporativo"
- "usa esta imagem como referencia de cores"
- "quero a galeria em estilo masonry"
- "destaca o botao de enviar fotos"

Mas a IA nao pode:

- criar uma pagina fora do `HubRenderer` ou do futuro `GalleryRenderer`;
- inventar bloco que nao existe;
- trocar o papel da galeria por uma landing page generica;
- gerar CSS/React livre dentro do monorepo;
- publicar direto sem revisao.

### Modelo de UX recomendado

O editor deve ter tres caminhos principais, nessa ordem:

1. Presets prontos.
2. Ajuste guiado por formulario simples.
3. Ajuste por conversa com IA.

Presets devem ser o caminho padrao para a maioria dos usuarios. O chat deve ser um acelerador para linguagem natural, nao a unica forma de configurar.

Fluxo ideal:

1. usuario escolhe um preset visual;
2. preview mostra o resultado imediatamente;
3. usuario pode ajustar texto, botoes e blocos por controles simples;
4. se quiser, abre "Ajustar com IA";
5. usuario descreve a intencao ou anexa uma imagem de referencia;
6. IA gera um rascunho;
7. sistema mostra diff/resumo: cores alteradas, blocos ligados/desligados, copy sugerida;
8. usuario aplica no rascunho;
9. usuario publica explicitamente.

### Imagem de referencia

O benchmark analisado ja tem uma boa ideia de UX para anexos:

- upload pelo chat;
- arrastar e soltar;
- badges de arquivos anexados;
- mencao a arquivos existentes;
- envio de `file_ids` junto com a mensagem;
- backend resolve arquivos e injeta URLs no contexto da IA.

Para o Evento Vivo, a adaptacao precisa ser mais restrita:

- aceitar apenas imagens como referencia visual no editor de layout;
- extrair paleta, contraste, tom visual e possivel textura;
- nao usar a imagem como fonte para gerar layout livre;
- nao transformar a imagem em asset publico automaticamente;
- exigir confirmacao se a imagem for aplicada como hero/capa;
- bloquear tipos e tamanhos fora da politica de upload.

Referencia visual deve influenciar somente campos permitidos, por exemplo:

- `theme_tokens`
- escolha de preset visual
- intensidade de overlay
- arredondamento/densidade visual
- familia tipografica pre-aprovada
- estilo do componente de foto
- copy/tom editorial

### Blocos fixos por superficie

Para o `Hub`, a IA deve operar sobre o contrato existente:

- `hero`
- `meta_cards`
- `welcome`
- `countdown`
- `info_grid`
- `cta_list`
- `social_strip`
- `sponsor_strip`

Para a `Gallery`, antes da IA, precisamos criar um contrato proprio. Candidatos:

- `hero`
- `gallery_grid`
- `featured_strip`
- `upload_cta`
- `find_me_cta`
- `stats_bar`
- `sponsor_strip`

O estilo pode variar. A funcao da superficie nao.

Exemplos permitidos para `gallery_grid`:

- masonry;
- grid editorial;
- carrossel de destaques acima do masonry;
- cards com legenda;
- cards mais minimalistas;
- bordas/raio/sombra variando por preset.

Exemplos nao permitidos:

- remover a grade de fotos da galeria publica;
- transformar a galeria em pagina de vendas;
- criar formulario arbitrario;
- esconder midias publicadas sem regra de negocio;
- criar CTA para modulo desabilitado.

### Contrato de IA recomendado

A resposta da IA deve ser um JSON pequeno e validavel, nunca codigo.

Exemplo conceitual:

```json
{
  "summary": "Ajustei para um visual casamento elegante com fundo claro rosado e botoes dourados.",
  "surface": "hub",
  "changes": {
    "headline": "Bem-vindos ao nosso dia",
    "subheadline": "Fotos, recados e momentos ao vivo",
    "builder_config": {},
    "button_style": {}
  },
  "warnings": [
    "O botao do Play nao foi ativado porque o modulo play nao esta habilitado para este evento."
  ]
}
```

O backend deve validar:

- `surface` dentro do permitido;
- enums de tema/layout/bloco;
- cores em formato aceito;
- URLs somente para campos que aceitam URL;
- CTAs somente para modulos habilitados;
- limite de itens por bloco;
- texto em tamanho razoavel;
- ausencia de CSS/JS arbitrario.

### Validacao tecnica executada

Comandos executados:

- `cd apps/api && php artisan test tests/Feature/Hub tests/Feature/Gallery tests/Feature/Analytics/AnalyticsTrackingTest.php`
- `cd apps/api && php artisan test tests/Feature/MediaProcessing/ModerationMediaTest.php --filter=gallery`
- `cd apps/web && npm run type-check`
- `cd apps/web && npm run test`
- `cd apps/web && npx vitest run src/modules/wall/pages/EventWallManagerPage.test.tsx src/modules/billing/PublicEventCheckoutPage.test.tsx --testTimeout=10000`

Resultados:

- backend `Hub`, `Gallery` e analytics publico: 23 testes passaram, 193 assertions;
- backend public gallery ordenando midias fixadas: 1 teste passou, 10 assertions;
- frontend `type-check`: passou;
- Vitest completo: 32 arquivos passaram e 2 falharam por timeout de 5s;
- reexecucao dos 2 arquivos com `--testTimeout=10000`: passou, 2 arquivos e 9 testes;
- teste frontend existente ligado a galeria, `GallerySenderActions`, passou dentro da suite.

Interpretacao:

- a base backend atual de `Hub` esta saudavel para settings, presets, uploads, insights, tracking e payload publico;
- a base backend atual de `Gallery` esta saudavel para workflow admin, disponibilidade publica e ordenacao publica essencial;
- o frontend compila com tipos validos;
- a suite frontend tem dois testes lentos/flaky sob timeout padrao, mas eles nao indicam regressao de `Hub` ou `Gallery`;
- nao ha ainda testes frontend especificos para `HubRenderer`, `PublicHubPage`, `PublicGalleryPage` ou futuro editor AI-assisted.

### O que precisamos melhorar antes de implementar

1. Criar contrato de draft/publicacao para o `Hub`.
2. Criar cobertura frontend para `HubRenderer` com blocos ligados/desligados, CTA tracking e preview.
3. Criar testes de normalizacao para retorno de IA rejeitando blocos desconhecidos e campos arbitrarios.
4. Criar um renderer/config tipado para a `Gallery` antes de colocar IA nela.
5. Criar testes publicos da `Gallery` para variacoes de layout, mantendo sempre a grade de fotos.
6. Criar um servico de referencia visual que aceite imagem e devolva somente tokens/decisoes permitidas.
7. Ajustar a suite Vitest ou testes lentos para evitar timeouts falsos no CI.

### Recomendacao final apos validacao

O MVP correto nao e "criador de site por IA". O MVP correto e "editor guiado por IA para superficies publicas padronizadas".

Ordem recomendada:

1. `Hub` com presets + chat que gera draft JSON.
2. `Hub` com imagem de referencia convertida em tokens controlados.
3. `Hub` com revisoes, diff e publish manual.
4. `Gallery` com contrato de builder proprio.
5. `Gallery` com presets de grade e IA limitada ao contrato.

## Duvidas abertas - stack, galeria mobile e logica

### Stack e performance de imagem

Pontos ja confirmados na stack atual:

- backend gera variantes de imagem no modulo `MediaProcessing`;
- variantes atuais incluem `fast_preview`, `thumb`, `gallery` e `wall`;
- `thumb` tem alvo de 480x480 WebP;
- `gallery` tem alvo de 1600x1600 WebP;
- `wall` tem alvo de 1920x1920 WebP;
- API publica da galeria retorna `thumbnail_url`, `preview_url`, `original_url`, `width`, `height` e `orientation`;
- pagina publica da galeria hoje usa `thumbnail_url` no grid;
- foi adicionado carregamento `loading="lazy"` e `decoding="async"` no grid publico.

Duvidas que ainda precisam ser decididas:

1. Vamos servir imagens sempre pelo storage local atras do Nginx/Cloudflare ou teremos CDN dedicada para midias publicas?
2. A API publica deve expor um mapa de variantes, por exemplo `variants.thumb`, `variants.gallery`, `variants.wall`, para o frontend montar `srcset`?
3. O `preview_url` publico deve continuar preferindo `fast_preview` para imagens ou, em contexto de galeria/lightbox, deve preferir `gallery` para qualidade visual?
4. Teremos lightbox/tela cheia? Se sim, o grid deve carregar `thumb`, mas o lightbox deve carregar `gallery`, nunca `original` por padrao.
5. Queremos blur placeholder, dominant color ou skeleton por imagem para melhorar percepcao no mobile?
6. Qual limite de itens por pagina no mobile: manter 30, reduzir para 20 ou usar infinite scroll com cursor?
7. Precisamos preservar acesso offline/PWA da galeria ou isso fica somente para `Hub`/`Play`?
8. Videos entram na galeria publica com thumbnail e player, ou a primeira versao do builder de galeria sera somente foto?
9. O Nginx/proxy de producao esta configurado com cache headers fortes para `/storage/events/.../variants/...`?
10. Precisamos gerar AVIF alem de WebP ou WebP e suficiente para o publico-alvo?

### Tipos de galeria que fazem sentido

Todos os tipos abaixo continuam sendo uma galeria de fotos. O que muda e ritmo, densidade e apresentacao.

1. `masonry-live`: padrao recomendado para mobile, com duas colunas no celular, carregamento leve e foco nas fotos recentes.
2. `editorial-grid`: mistura cards grandes e pequenos, bom para casamento, formatura e evento premium.
3. `featured-first`: faixa de destaques no topo e grid abaixo, bom quando a curadoria usa `is_featured`.
4. `compact-feed`: lista/grade mais densa, boa para evento com alto volume de fotos e internet instavel.
5. `cover-album`: hero visual + secoes de fotos, bom para evento social com menos volume e mais narrativa.
6. `brand-wall`: visual mais corporativo, com sponsor/branding e fotos em blocos mais regulares.

Campos que provavelmente entram no futuro `gallery_builder_config_json`:

- `layout_key`
- `theme_key`
- `theme_tokens`
- `block_order`
- `blocks.hero`
- `blocks.gallery_grid`
- `blocks.featured_strip`
- `blocks.upload_cta`
- `blocks.find_me_cta`
- `blocks.stats_bar`
- `blocks.sponsor_strip`
- `image_loading_strategy`
- `grid_density`
- `card_style`
- `caption_mode`
- `lightbox_mode`

### Duvidas de logica de produto

1. A galeria publica deve ficar acessivel depois que o evento sair de `active`? Hoje o backend retorna `410` para evento nao ativo.
2. O `Hub` deve exibir o botao de galeria quando a galeria esta vazia ou deve trocar para "Enviar fotos" como CTA principal?
3. O builder de galeria deve ter publicacao separada da publicacao das fotos? Exemplo: tema em rascunho, fotos publicadas.
4. Quem pode alterar o layout publico: apenas `gallery.manage`, tambem `events.update`, ou uma permissao nova como `gallery.design`?
5. Presets devem ser por plataforma, por organizacao, por cliente ou por evento?
6. Patrocinadores e marcas precisam ter area fixa na galeria ou isso fica so no `Hub`?
7. A busca facial `find_me` deve aparecer como bloco da galeria quando habilitada?
8. Fotos fixadas (`sort_order > 0`) devem sempre vencer qualquer preset visual?
9. Fotos destacadas (`is_featured`) devem alimentar automaticamente o `featured_strip`?
10. O usuario pode esconder legenda/nome do remetente no preset ou isso tem regra de privacidade propria?
11. O usuario final pode baixar foto em alta ou somente visualizar em qualidade otimizada?
12. A galeria deve ter compartilhamento por foto individual ou somente compartilhamento da pagina?
13. Como lidar com duplicadas visuais: esconder automaticamente, agrupar, ou deixar para curadoria?
14. Como tratar fotos sem `thumb`: mostrar placeholder, usar `gallery`, ou nao retornar no endpoint publico ate variante existir?
15. A IA pode sugerir ligar/desligar blocos com base em modulos habilitados, mas quem confirma mudanca: sempre o operador?

### Testes adicionais criados nesta rodada

Backend:

- `PublicGalleryAvailabilityTest` agora valida bloqueio quando evento nao esta ativo;
- `PublicGalleryAvailabilityTest` agora valida que a galeria publica retorna apenas midias aprovadas e publicadas;
- o mesmo teste valida metadados uteis para layout responsivo: `thumbnail_url`, `preview_url`, `width`, `height` e `orientation`.

Frontend:

- novo `PublicGalleryPage.test.tsx`;
- valida que a galeria chama a API pelo `slug`;
- valida que o grid usa `thumbnail_url`;
- valida `loading="lazy"` e `decoding="async"` nas imagens;
- valida estado vazio explicito para galeria publica.
