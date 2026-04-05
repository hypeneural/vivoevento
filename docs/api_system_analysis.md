# Análise Completa da API - Evento Vivo

Este documento apresenta uma análise profunda do repositório da API do **Evento Vivo**, destacando sua arquitetura, diferenciais e os módulos core que sustentam suas funcionalidades avançadas, como I.A. e o Telão Realtime (Wall).

---

## 1. Visão Geral e Diferenciais Técnicos

O backend foi construído com foco em **escalabilidade, processamento assíncrono e arquitetura modular**. Em vez de um monolito tradicional emaranhado, o sistema utiliza uma abordagem orientada a domínios rígida, tornando-o um sistema "Enterprise-ready".

### Diferenciais do Sistema:
- **Arquitetura Modular (Domain-Driven):** Separação clara de domínios (Modules) como `MediaProcessing`, `FaceSearch`, `Wall`, `WhatsApp`, etc. Cada módulo é dono de suas tabelas, filas, eventos e serviços. Isso evita "código espaguete" e acoplamento.
- **Processamento Assíncrono Ultra-Resiliente:** Operações pesadas (download, resize, reconhecimento facial, moderação de IA) nunca bloqueiam o request do usuário. São orquestradas em múltiplas filas do Redis (`media-download`, `media-safe`, `face-index`, `media-publish`), permitindo paralelismo.
- **Provider-Agnostic Design:** O sistema de WhatsApp, Moderação de Conteúdo e Busca Facial são desenhados com o padrão "Adapter" (Interfaces). É possível trocar a OpenAI por outro motor de moderação ou mudar a engine do WhatsApp de Z-API para Evolution API quase sem alterar a regra de negócio.
- **Real-Time de Alta Performance (Reverb):** Emissão de eventos broadcast em tempo real para painéis administrativos, telas físicas e moderação sem poling (WebSockets nativos).
- **Idempotência no Inbound:** Garante que a mesma foto não será reprocessada mesmo se as APIs de origem enviarem os webhooks de recebimento em duplicidade.

---

## 2. Multi-Entradas (Inbound Media & WhatsApp)

Uma grande força da plataforma é não depender de um único aplicativo. Os convidados enviam mídia de onde já estão habituados: seus aplicativos de mensagens.

### Como a "Multi-Entrada" funciona:
1. **Recepção Agnóstica (`InboundMedia`):** Toda mídia que chega (seja Telegram, WhatsApp ou upload manual) entra como um webhook. Este webhook é armazenado na íntegra de imediato para garantir que nenhum dado se perca (log bruto).
2. **Normalização:** Um job (`NormalizeInboundMessageJob`) converte esse payload nativo em uma estrutura de dados universal (`InboundMessage`). O sistema interno não precisa saber de onde veio a foto para processá-la.
3. **Módulo WhatsApp Avançado:**
   - Suporta múltiplos Providers simultâneos (Z-API, Evolution API).
   - Gerencia estado das conexões (QR Code), bindings (vincular um grupo remoto de WhatsApp a um Evento interno).
   - Tem gestão remota de chats e grupos (criação, edição de capa do grupo, etc.) direto pelo sistema.
   - Idempotência severa: O webhook garante que `instance_id + direction + provider_message_id` seja único, lidando brilhantemente com alta concorrência em eventos com muita gente postando ao mesmo tempo.

---

## 3. Reconhecimento Facial (Face Search)

O sistema de busca por reconhecimento facial do Evento Vivo é projetado com robustez desde o gerenciamento do vetor até a pipeline da imagem.

### Estrutura Atual e Papel da I.A.
- **Extração via Job Autônomo:** Logo após as variantes da mídia serem geradas na entrada, o job `IndexMediaFacesJob` entra na fila `face-index`.
- **Quality Gate:** A I.A. obedece configurações de qualidade do evento. Ela não salva rostos minúsculos num fundo distorcido; há limiares paramétricos como `min_face_size_px` e `min_quality_score`. Se a face passar, ela sofre um "crop privado" guardado de forma segura e gera um **embedding** (Vetor Numérico de I.A.).
- **Armazenamento Vetorial (`pgvector`):** Utiliza armazenamento nativo vetorial em banco, permitindo cálculos de similaridade ("Busque quem tem a matriz matemática de rosto mais parecida com essa selfie submetida").
- **Limitação de Responsabilidade:** O componente é "burro e especialista". Ele **não** modera se a foto é boa, se tem nudez, não censura ou aprova (estes papéis são de outros módulos). Apenas extrai coordenadas do rosto (`bbox`), gera o Embedding e associa à foto original (`event_media`).
- **Casos de Uso Atuais:**
  1. Busca privada no backoffice para moderação/organização.
  2. Busca pública (`/public/events/{slug}/face-search`), na qual um convidado faz upload da sua selfie temporária, o sistema extrai o embedding dela, compara instantaneamente com a base do evento usando `pgvector`, e colapsa as respostas retornando exatamante as mídias daquela pessoa.

---

## 4. Moderação por I.A. (Content Moderation)

O tráfego orgânico num telão público envolve riscos sérios de abuso. Para escalar, o sistema utiliza Moderação Multimodal de I.A.

### O Funcionamento Oculto:
- Quando o evento opta pelo `moderation_mode=ai`, a checagem manual por humanos deixa de ser a única barreira.
- **Modulação Exata (`AnalyzeContentSafetyJob`):** Ocorre assincronamente assim que a foto chega.
- **O Motor e Parâmetros:** O módulo consulta o adaptador atual (neste caso, *OpenAI* usando `omni-moderation-latest`). A imagem e qualquer legenda passam por análise. A I.A. gera um score por categoria:
  - Nudez / Pornografia
  - Violência / Armas
  - Hate speech ou Risco à audiência
- **Critérios Customizados por Evento:** A I.A. não decide cegamente. O evento tem "Thresholds" (limites de tolerância). Se a foto exceder o threshold no modelo, o status de safety (`safety_status`) da foto muda.
- A decisão flui para a fila de moderação principal. Uma vez alertada pela moderação de I.A, a foto pode ser imediatamente rejeitada e isolada, livrando a moderação humana do peso de revisar conteúdos explicitamente perigosos, agilizando todo o tempo-até-o-telão (Time-to-Wall).

---

## 5. Funcionalidades do Telão (Wall) e Seus Diferenciais

O conceito de **Telão do Evento Vivo** vai além de um simples slideshow visual. Ele atua como um sistema realtime corporativo robusto.

### Arquitetura de Comunicação (Under the Hood):
- **Laravel Reverb:** O evento possui canais de WebSockets privados e públicos (`wall.{wallCode}`). Todo o tráfego não depende do navegador perguntar "tem foto nova?". O servidor **empurra** (broadcasts) as mudanças no exato bilionésimo de segundo que ocorrem via `WallBroadcasterService`.
- **Elegibilidade Inteligente:** O player consome eventos como `MediaPublished`. Mas não aceita tudo cegamente. O `WallEligibilityService` processa rapidamente o estado do Wall e da mídia para decidir se realmente o player precisa saber desta alteração.

### Controles Avançados & Diferenciais
1. **Layouts Dinâmicos e Transições Avançadas:**
   Em vez de um formato imutável, o backend entrega opções de orquestração como `auto`, `polaroid`, `fullscreen`, `split`, `cinematic`. Isso significa que um único envio altera o comportamento visual em todos os telões que estiverem ativos fisicamente no local do evento.
   Transições customizadas (`fade`, `slide`, `zoom`, `flip`) podem ser injetadas on-the-fly.
2. **Ciclo de Estado Complexo (`WallStatus`):**
   Não é apenas On/Off. O gerenciamento de tempo real prevê status cruciais:
   - `live`: Fotos rodando, entradas instantâneas.
   - `paused`: Telão trava na última foto - ótimo para quando um apresentador no palco precisa de atenção ou para fazer um sorteio visual.
   - `stopped` e `full-stop`: Corta conteúdo sensível imediatamente, blindando o evento de incidentes com um clique do operador no iPad/Celular.
   - `expired`: O link "wall_code" de segurança encerra. O player local "morre". Previne que um computador esquecido ligado, ou URL vazada, exiba dados após o término do evento.
3. **Gerador de Interatividade Publica:**
   Operações remotas permitem mandar o Telão resetar inteiramente o fluxo, upar fundos (`upload-background`) ou logomarcas (`upload-logo`) em realtime via admin, atualizando simultaneamente para as centenas/milhares de pessoas olhando na pista.

---

## 6. Módulo de Games (Play): Puzzle e Jogo da Memória

A plataforma transcende a exibição passiva ao injetar **PWA Games** (Jogos Interativos) integrados ao evento.

A arquitetura do módulo "Play" divide responsabilidades com maestria: usa **Laravel** como cérebro de regras e rankeamento, **React/PWA** (Progressive Web Apps) para roteamento público e gestão de sessões, e **Phaser 3** para a engine gráfica (física de arrastar peças de quebra-cabeça, virar cartas, responder a toques do usuário).

### Diferenciais dos Jogos usando Fotos do Evento:
1. **Curadoria Automática de Fotos (Assets Visuais):**
   Diferente de um jogo genérico, o Puzzle (Quebra-Cabeça) e o Jogo da Memória utilizam as próprias **fotos enviadas no telão** como peças e cartas do jogo. O sistema realiza uma curadoria inteligente ("fallback automático"), buscando ativamente no banco mídias de preferência verticais ("Portrait"), de boa resolução e recém-aprovadas na moderação. Isso proporciona uma **hiper-contextualização**: o convidado pode montar um quebra-cabeça de uma foto tirada há 5 minutos atrás na pista de dança.
2. **Score Autoritativo (Anti-Fraude):**
   Como existe um Ranking entre os convidados, a segurança é prioridade. O navegador (cliente) não dita as regras. O jogo manda os dados (`moves`, `mistakes`, tempo), e todo o \`Score\` final é **recalculado no Backend**. O sistema sabe exatamente o segundo (`server_started_at`) em que a foto do Puzzle foi "desmembrada" em peças contra o `finish`, prevenindo qualquer trapaça ou envio malicioso por usuários leigos, garantindo a integridade do "Leaderboard" e das experiências de gamificação.
3. **Engenharia de UX Mobile-First:**
   Os jogos não adaptam interfaces de computador para celular; são desenhados inerentemente de forma "portrait-first" usando escalas e "Safe Areas" do celular. A "Bandeja Inferior" do Puzzle e as cartas espaçadas do Memory Game cabem na "Thumb zone", otimizadas para jogar rapidamente com apenas o polegar de uma mão na festa.
4. **Ciclo de Sessão Ultrarresiliente e PWA:**
   Sendo o "Evento Vivo" ambiente sensível a redes (4G oscilante), o jogo emprega cache profundo graças a \`Service Workers\`. Conta com ciclo fechado de sessão (`heartbeat` e `abandoned`) e reidratação (`resume`). Se alguém jogar o Quebra-Cabeça, sair do app para responder o WhatsApp e voltar depois, o \`resumeToken\` invoca o tabuleiro precisamente igual aos encaixes interrompidos, sem perder o progresso ou os segundos valiosos da pontuação.
