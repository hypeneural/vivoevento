# Documento de Requisitos

## Introdução

Refatoração profunda da landing page do Evento Vivo com foco em conversão comercial baseada em estratégias CRO (CXL, NN/g, web.dev). A landing atual apresenta excesso de informação competindo simultaneamente, densidade visual uniforme, muitos cards e microblocos causando fadiga, tecnologia aparecendo mais que benefício emocional, fluxo pouco didático, CTAs mal distribuídos e falta de storytelling comercial direto.

A refatoração visa criar uma experiência premium, clean, com hierarquia clara, alternância entre seções explicativas/emocionais/comerciais, e foco em conversão real para demonstração/agendamento. A estratégia de CRO incorpora: message match entre anúncio/intenção e landing, clareza de audiência e "most wanted action", prova social contextualizada por persona, microconversões além da macro, trust signals na parte alta, e otimização contínua via testes A/B.

## Glossário

- **Landing_Page**: Página web única de entrada para visitantes interessados na plataforma Evento Vivo
- **Hero_Section**: Seção principal acima da dobra que apresenta proposta de valor
- **CTA**: Call-to-action, botão ou link que direciona para ação de conversão
- **Conversion_Flow**: Jornada do visitante desde entrada até agendamento de demonstração
- **Visual_Hierarchy**: Organização de elementos visuais por importância e impacto
- **Section_Module**: Componente React que representa uma seção completa da landing
- **Mobile_First**: Abordagem de design que prioriza experiência mobile
- **Lazy_Load**: Técnica de carregamento sob demanda para otimizar performance
- **Premium_Design**: Estética sofisticada, elegante e de alto padrão visual
- **Social_Proof**: Evidências de credibilidade através de depoimentos e casos reais
- **Storytelling_Comercial**: Narrativa que guia visitante através de benefícios até conversão
- **Experience_Modules**: Conjunto de funcionalidades do produto (galeria, telão, jogos, busca facial)
- **AI_Safety**: Recursos de moderação, aprovação e segurança operacional por IA
- **Face_Recognition**: Funcionalidade de busca facial para encontrar fotos de convidados específicos
- **Persona**: Perfil de visitante com necessidades e objeções específicas (Assessoras/Cerimonialistas, Noivas/Debutantes/Famílias, Promotores/Produtores/Corporativos)
- **Message_Match**: Alinhamento entre promessa do anúncio/origem e conteúdo da landing
- **Trust_Signal**: Elemento que reduz ansiedade e aumenta confiança (sem app, moderação IA, busca facial configurável)
- **Microconversão**: Ação de baixo risco que precede conversão macro (ver exemplo, assistir demo, explorar funcionalidade)
- **Prova_Social**: Evidência de credibilidade através de casos reais organizados por contexto de evento
- **CRO**: Conversion Rate Optimization, otimização baseada em dados para aumentar taxa de conversão
- **A/B_Test**: Experimento controlado comparando duas variações para identificar qual converte melhor

## Requisitos

## Grupo 1: Requisitos de Negócio e Conversão

### Requisito 1: Estrutura e Hierarquia da Landing

**User Story:** Como visitante interessado, quero navegar por uma landing page clara e bem organizada, para que eu entenda rapidamente o valor da plataforma e tome decisão de agendar demonstração.

#### Acceptance Criteria

1. THE Landing_Page SHALL ser estruturada em até 12 blocos principais, priorizando narrativa enxuta e progressiva, alternando entre seções explicativas, emocionais e comerciais
2. WHEN um visitante rola a página, THE Landing_Page SHALL alternar entre seções clean explicativas, visuais emocionais e comerciais robustas
3. THE Visual_Hierarchy SHALL garantir que cada seção tenha exatamente uma mensagem principal clara
4. THE Landing_Page SHALL reduzir densidade de microcards em pelo menos 60% comparado à versão atual
5. WHEN uma seção é renderizada, THE Section_Module SHALL ter contraste visual claro com seções adjacentes

### Requisito 2: Navbar Simplificada

**User Story:** Como visitante, quero uma navegação limpa e direta, para que eu encontre informações relevantes sem sobrecarga visual.

#### Acceptance Criteria

1. THE Navbar SHALL conter 6 âncoras de navegação: Como funciona, Recursos, Para quem é, Depoimentos, Planos, FAQ
2. THE Navbar SHALL exibir logo da marca
3. THE Navbar SHALL exibir CTA principal "Agendar demonstração" com destaque visual
4. THE Navbar SHALL exibir CTA secundário discreto para WhatsApp
5. WHEN o visitante rola mais de 72px, THE Navbar SHALL aplicar estado compacto com backdrop blur

### Requisito 3: Hero Section Premium

**User Story:** Como visitante que acabou de chegar, quero entender imediatamente o que a plataforma faz, para que eu decida se vale continuar explorando.

#### Acceptance Criteria

1. THE Hero_Section SHALL comunicar acima da dobra que fotos e vídeos enviados pelos convidados se transformam em galeria ao vivo, telão, jogos e memórias encontráveis
2. THE Hero_Section SHALL apresentar headline centrada em resultado percebido: "Os convidados já estão tirando fotos. Agora elas viram experiência ao vivo no seu evento."
3. THE Hero_Section SHALL conter 2 CTAs: primário "Agendar demonstração" e secundário "Falar no WhatsApp"
4. THE Hero_Section SHALL apresentar faixa de trust signals logo abaixo dos CTAs: Sem app, Fotos e vídeos por WhatsApp/Telegram/link, Moderação por IA, Busca facial configurável
5. WHEN renderizada em mobile, THE Hero_Section SHALL manter proposta de valor e CTA primário acima de 600px de altura

### Requisito 4: Seção Como Funciona Didática

**User Story:** Como visitante interessado, quero entender o funcionamento básico da plataforma, para que eu visualize como seria a implementação no meu evento.

#### Acceptance Criteria

1. THE Como_Funciona_Section SHALL apresentar fluxo em 3-4 passos visuais
2. THE Como_Funciona_Section SHALL usar layout limpo com fundo menos carregado que Hero
3. THE Como_Funciona_Section SHALL explicar: Convidados enviam → Plataforma recebe e organiza → IA modera e pode responder → Conteúdo aparece em galeria/telão/jogos/busca facial
4. WHEN renderizada, THE Como_Funciona_Section SHALL priorizar conteúdo visível em uma dobra (≤100vh) em desktop
5. THE Como_Funciona_Section SHALL usar ilustrações ou ícones ao invés de texto denso

### Requisito 5: Seção Canais de Entrada

**User Story:** Como organizador de evento, quero entender quais canais de captura estão disponíveis, para que eu escolha o mais adequado ao meu tipo de evento.

#### Acceptance Criteria

1. THE Canais_Section SHALL apresentar visualmente 5 canais: Número WhatsApp, Grupo WhatsApp, Telegram, Link/QR Code, Upload web
2. THE Canais_Section SHALL explicar que organizador escolhe canal ideal para seu evento
3. THE Canais_Section SHALL usar cards grandes ou grid visual ao invés de lista textual
4. WHEN um canal é destacado, THE Canais_Section SHALL mostrar exemplo visual de uso
5. THE Canais_Section SHALL ocupar aproximadamente 60-80vh em desktop, priorizando leitura sem scroll excessivo

### Requisito 6: Seção Módulos de Experiência Unificada

**User Story:** Como decisor comercial, quero entender todos os módulos da plataforma de forma organizada, para que eu avalie o valor completo da solução.

#### Acceptance Criteria

1. THE Experience_Modules_Section SHALL apresentar o ecossistema do produto de forma compacta, podendo usar tabs, cards ou alternância visual, sem replicar múltiplas mini-seções independentes
2. THE Experience_Modules_Section SHALL apresentar 4 módulos principais: Galeria ao vivo, Telão dinâmico, Jogos interativos, Busca facial (como parte do ecossistema)
3. WHEN um módulo é selecionado, THE Experience_Modules_Section SHALL exibir uma mídia principal com proporção destacada e até 3 pontos de apoio textuais
4. THE Experience_Modules_Section SHALL reduzir repetição de padrões visuais entre módulos
5. THE Experience_Modules_Section SHALL ocupar aproximadamente 100-120vh em desktop

### Requisito 7: Seção IA e Segurança Operacional

**User Story:** Como organizador preocupado com segurança, quero entender como a IA protege meu evento, para que eu confie na operação da plataforma.

#### Acceptance Criteria

1. THE AI_Safety_Section SHALL focar em moderação, aprovação inteligente, respostas por IA personalizáveis e segurança operacional
2. THE AI_Safety_Section SHALL evitar termos técnicos como embeddings, pipeline, indexação, inferência, threshold, vetores no texto público
3. THE AI_Safety_Section SHALL explicar: moderação para evitar conteúdo impróprio, aprovação inteligente, respostas por IA personalizáveis, operação segura
4. THE AI_Safety_Section SHALL apresentar demo visual de moderação em ação
5. WHEN renderizada, THE AI_Safety_Section SHALL priorizar conteúdo visível em uma dobra (≤100vh) em desktop

### Requisito 8: Seção Reconhecimento Facial com Apelo Emocional

**User Story:** Como convidado de evento, quero entender como posso encontrar minhas fotos facilmente, para que eu me interesse em usar a funcionalidade.

#### Acceptance Criteria

1. THE Face_Recognition_Section SHALL apresentar experiência emocional e comercial da busca facial como seção dedicada
2. THE Face_Recognition_Section SHALL comunicar mensagem "Encontre as fotos do noivo, debutante, aniversariante ou de um convidado em segundos"
3. THE Face_Recognition_Section SHALL mostrar fluxo visual: selfie de referência → resultados encontrados
4. THE Face_Recognition_Section SHALL usar linguagem orientada a benefício, evitando jargão de reconhecimento facial
5. WHEN renderizada em mobile, THE Face_Recognition_Section SHALL manter demo visual funcional

### Requisito 9: Seção Para Quem É com Clareza Comercial

**User Story:** Como visitante de perfil específico, quero identificar rapidamente se a plataforma serve para mim, para que eu não perca tempo com solução inadequada.

#### Acceptance Criteria

1. THE Audience_Section SHALL apresentar 3 cards principais de público: Assessoras/Cerimonialistas, Noivas/Debutantes/Famílias, Promotores/Produtores/Corporativos
2. WHEN um card é exibido, THE Audience_Section SHALL responder: promessa principal, módulos prioritários, objeções principais
3. THE Audience_Section SHALL usar cards com hierarquia clara ao invés de tabs
4. THE Audience_Section SHALL ocupar aproximadamente 80-100vh em desktop
5. THE Audience_Section SHALL permitir escaneamento rápido em menos de 10 segundos

### Requisito 10: Seção Depoimentos com Prova Social Forte

**User Story:** Como visitante cético, quero ver evidências de resultados reais, para que eu confie na eficácia da plataforma.

#### Acceptance Criteria

1. THE Testimonials_Section SHALL incluir contexto de evento, volume de fotos e resultado mensurável em cada depoimento
2. THE Testimonials_Section SHALL organizar prova social em 3 contextos: Casamentos e debutantes, Assessoria e cerimonial, Eventos e ativações
3. WHEN um depoimento é exibido, THE Testimonials_Section SHALL incluir foto real do evento relacionado
4. THE Testimonials_Section SHALL apresentar entre 3 e 5 depoimentos reais
5. THE Testimonials_Section SHALL incluir tipo de evento, volume processado e resultado percebido em cada caso

### Requisito 11: Seção Planos com Leitura Comercial Fácil

**User Story:** Como decisor comercial, quero entender opções de contratação rapidamente, para que eu identifique qual plano se adequa ao meu caso.

#### Acceptance Criteria

1. THE Pricing_Section SHALL separar 3 categorias: evento avulso, parceiros recorrentes, operação enterprise/corporativo
2. THE Pricing_Section SHALL destacar visualmente plano mais comum
3. THE Pricing_Section SHALL reduzir ruído visual em pelo menos 40% comparado à versão atual
4. WHEN um plano é exibido, THE Pricing_Section SHALL apresentar público-alvo, preço e diferencial principal visíveis sem scroll
5. THE Pricing_Section SHALL evitar excesso de features listadas por plano

### Requisito 12: FAQ Enxuto e Escaneável

**User Story:** Como visitante com objeções, quero encontrar respostas rápidas para dúvidas comuns, para que eu remova barreiras à conversão.

#### Acceptance Criteria

1. THE FAQ_Section SHALL conter entre 7 e 10 perguntas comerciais e de objeção real
2. THE FAQ_Section SHALL incluir perguntas: precisa de app?, aceita vídeo?, funciona com muito volume?, como funciona moderação?, como funciona busca facial?, serve para casamento e formatura?, posso usar com meu branding?
3. THE FAQ_Section SHALL permitir escaneamento de todas perguntas em menos de 15 segundos
4. WHEN uma pergunta é expandida, THE FAQ_Section SHALL exibir resposta concisa (idealmente ≤3 linhas)
5. THE FAQ_Section SHALL usar accordion com apenas uma pergunta aberta por vez

### Requisito 13: CTA Final Forte e Simples

**User Story:** Como visitante convencido, quero ação clara para próximo passo, para que eu converta sem fricção.

#### Acceptance Criteria

1. THE Final_CTA_Section SHALL apresentar título forte e subtítulo curto
2. THE Final_CTA_Section SHALL exibir exatamente 2 botões: "Agendar demonstração" e "Falar no WhatsApp"
3. THE Final_CTA_Section SHALL usar fundo limpo e contrastante com seção anterior
4. THE Final_CTA_Section SHALL ocupar aproximadamente 50-70vh em desktop
5. WHEN renderizada, THE Final_CTA_Section SHALL ser última seção antes do footer

### Requisito 20: Conversão e CTAs Estratégicos

**User Story:** Como visitante pronto para converter, quero CTAs claros e bem posicionados, para que eu tome ação sem confusão.

#### Acceptance Criteria

1. THE Landing_Page SHALL priorizar presença do CTA primário "Agendar demonstração" no header, hero, seção de planos e fechamento final
2. THE Landing_Page SHALL priorizar presença do CTA secundário WhatsApp no header, hero e fechamento final
3. THE Landing_Page SHALL evitar mais de 2 CTAs visíveis simultaneamente em qualquer seção
4. WHEN visitante rola 80% da página sem clicar CTA, THE Landing_Page SHALL apresentar CTA flutuante discreto
5. THE Landing_Page SHALL usar cores contrastantes para CTAs primários com pelo menos 4.5:1 de contraste

### Requisito 26: Analytics e Conversão

**User Story:** Como gestor de marketing, quero medir comportamento do visitante, para que eu otimize conversão baseado em dados.

#### Acceptance Criteria

1. THE Landing_Page SHALL rastrear cliques em CTA primário e secundário
2. THE Landing_Page SHALL rastrear scroll depth por faixas (25%, 50%, 75%, 100%)
3. THE Landing_Page SHALL rastrear interação com planos, FAQ e módulos
4. THE Landing_Page SHALL permitir identificação de origem por parâmetros UTM
5. THE Landing_Page SHALL registrar seção de maior abandono

## Grupo 2: Requisitos de Experiência do Usuário

### Requisito 15: Copy Premium e Direta

**User Story:** Como visitante, quero textos claros e diretos, para que eu entenda benefícios sem esforço cognitivo.

#### Acceptance Criteria

1. THE Landing_Page SHALL reduzir texto por bloco em pelo menos 40% comparado à versão atual
2. THE Landing_Page SHALL priorizar benefício antes de tecnologia em todas seções
3. THE Landing_Page SHALL evitar jargão técnico exposto ao visitante
4. THE Landing_Page SHALL priorizar frases concisas (idealmente ≤20 palavras) para facilitar escaneamento
5. WHEN tecnologia é mencionada, THE Landing_Page SHALL apresentá-la como reforço de confiança

### Requisito 19: Hierarquia Visual e Espaçamento

**User Story:** Como visitante, quero hierarquia clara de informação, para que eu processe conteúdo sem fadiga visual.

#### Acceptance Criteria

1. THE Landing_Page SHALL garantir que cada dobra apresenta exatamente 1 mensagem principal
2. THE Landing_Page SHALL melhorar contraste entre título, corpo e elementos auxiliares
3. THE Landing_Page SHALL aumentar espaço em branco em pelo menos 50% comparado à versão atual
4. THE Landing_Page SHALL evitar usar 3-4 CTAs fortes no mesmo bloco
5. THE Landing_Page SHALL alternar padrões visuais entre seções consecutivas

### Requisito 27: SEO e Compartilhamento Social

**User Story:** Como visitante que encontrou a landing via busca ou rede social, quero preview atrativo e informações claras, para que eu decida se vale visitar.

#### Acceptance Criteria

1. THE Landing_Page SHALL definir title e meta description únicos e descritivos
2. THE Landing_Page SHALL possuir Open Graph tags completas (og:title, og:description, og:image, og:url)
3. THE Landing_Page SHALL possuir Twitter Card tags (twitter:card, twitter:title, twitter:description, twitter:image)
4. THE Landing_Page SHALL ter hierarquia de headings válida (h1 único, h2-h6 aninhados corretamente)
5. THE Landing_Page SHALL possuir preview social atrativo (imagem 1200x630px)

### Requisito 28: Privacidade e Comunicação Responsável

**User Story:** Como visitante preocupado com privacidade, quero entender como IA e reconhecimento facial funcionam, para que eu confie na plataforma.

#### Acceptance Criteria

1. THE Landing_Page SHALL indicar que recursos de IA e busca facial são configuráveis por evento
2. THE Landing_Page SHALL explicar que moderação tem níveis (sem moderação, manual, IA assistida)
3. THE Landing_Page SHALL incluir link para política de privacidade no footer
4. THE Landing_Page SHALL incluir link para termos de uso no footer
5. THE Landing_Page SHALL comunicar que dados de reconhecimento facial são processados apenas quando habilitado

## Grupo 3: Requisitos de Conteúdo e Estratégia

### Requisito 29: Estratégia de Conteúdo e Fallbacks

**User Story:** Como desenvolvedor, quero estratégia clara para conteúdo real vs placeholder, para que eu implemente estados apropriados.

#### Acceptance Criteria

1. THE Landing_Page SHALL usar depoimentos reais quando disponíveis, com fallback para placeholders contextualizados
2. THE Landing_Page SHALL usar fotos reais de eventos quando disponíveis
3. THE Landing_Page SHALL definir mínimo de 3 depoimentos aprovados antes de publicação
4. THE Landing_Page SHALL ter fallback visual para imagens ausentes
5. THE Landing_Page SHALL ter fallback para vídeos indisponíveis ou autoplay bloqueado

### Requisito 30: Estados de Erro e Degradação Graciosa

**User Story:** Como visitante com conexão lenta ou JS desabilitado, quero experiência funcional mesmo com limitações, para que eu ainda consiga converter.

#### Acceptance Criteria

1. THE Landing_Page SHALL exibir conteúdo principal mesmo com JS desabilitado
2. THE Landing_Page SHALL respeitar prefers-reduced-motion desabilitando animações decorativas
3. THE Landing_Page SHALL ter fallback para imagens que falharem ao carregar
4. THE Landing_Page SHALL ter fallback para vídeos que não carregarem
5. THE Landing_Page SHALL manter CTAs funcionais mesmo com falha de carregamento de componentes interativos

## Grupo 6: Requisitos de Estratégia CRO

### Requisito 31: Estratégia de Variação por Persona

**User Story:** Como visitante de perfil específico, quero landing que fale diretamente comigo, para que eu perceba relevância imediata.

#### Acceptance Criteria

1. THE Landing_Page SHALL suportar 3 variações de entrada: Assessoras/Cerimonialistas, Noivas/Debutantes/Famílias, Promotores/Produtores/Corporativos
2. WHEN uma variação é carregada, THE Landing_Page SHALL adaptar hero, prova social, ordem de módulos e CTAs para a persona
3. THE Landing_Page SHALL manter estrutura base consistente entre variações
4. THE Landing_Page SHALL permitir identificação de variação por parâmetro de URL
5. THE Landing_Page SHALL rastrear conversão por variação de persona

### Requisito 32: Hero Orientado a Resultado

**User Story:** Como visitante que acabou de chegar, quero entender o resultado que vou obter, para que eu me interesse imediatamente.

#### Acceptance Criteria

1. THE Hero_Section SHALL apresentar headline centrada em resultado percebido no evento, não descrição funcional
2. THE Hero_Section SHALL comunicar transformação: "fotos dos convidados → experiência ao vivo no evento"
3. THE Hero_Section SHALL incluir faixa de trust signals logo abaixo dos CTAs
4. THE Hero_Section SHALL apresentar 4 sinais de confiança: Sem app, WhatsApp/Telegram/link, Moderação IA, Busca facial configurável
5. THE Hero_Section SHALL priorizar emoção e benefício antes de explicação técnica

### Requisito 33: Trust Layer e Transparência

**User Story:** Como visitante preocupado com IA e dados, quero transparência upfront, para que eu confie na plataforma.

#### Acceptance Criteria

1. THE Landing_Page SHALL incluir microcopys de transparência perto dos CTAs: "configurável por evento", "sem app", "busca facial apenas quando habilitada"
2. THE Landing_Page SHALL explicar que moderação é ajustável ao perfil do evento
3. THE Landing_Page SHALL posicionar trust signals acima da dobra
4. THE Landing_Page SHALL incluir selo ou indicação de conformidade com privacidade
5. THE Landing_Page SHALL ter link para privacidade visível no footer e perto de CTAs principais

### Requisito 34: Prova Social Contextualizada por Persona

**User Story:** Como visitante, quero ver casos de eventos similares ao meu, para que eu me veja usando a plataforma.

#### Acceptance Criteria

1. THE Testimonials_Section SHALL organizar prova social em 3 blocos: Casamentos e debutantes, Assessoria e cerimonial, Eventos e ativações
2. WHEN um bloco é exibido, THE Testimonials_Section SHALL incluir tipo de evento, volume de mídia, problema resolvido e resultado percebido
3. THE Testimonials_Section SHALL priorizar casos atuais e específicos
4. THE Testimonials_Section SHALL incluir foto real do evento relacionado
5. THE Testimonials_Section SHALL adaptar prova social exibida conforme variação de persona

### Requisito 35: Seletor de Persona Interativo

**User Story:** Como visitante, quero indicar meu perfil, para que a landing mostre conteúdo mais relevante para mim.

#### Acceptance Criteria

1. THE Landing_Page SHALL apresentar seletor de persona antes da seção de módulos
2. THE Persona_Selector SHALL oferecer 3 opções: "Sou assessora/cerimonialista", "Sou noiva/debutante/família", "Sou produtor/promotor/corporativo"
3. WHEN uma persona é selecionada, THE Landing_Page SHALL adaptar headline secundária, prova social, ordem de módulos e CTA contextual
4. THE Persona_Selector SHALL persistir escolha durante navegação
5. THE Landing_Page SHALL rastrear interação com seletor de persona

### Requisito 36: Microconversões e Redução de Fricção

**User Story:** Como visitante que ainda não está pronto para falar com comercial, quero ação de baixo risco, para que eu explore sem compromisso.

#### Acceptance Criteria

1. THE Landing_Page SHALL oferecer microconversão além da macro: "Ver evento exemplo", "Ver como funciona em 30s", "Abrir demonstração visual"
2. THE Landing_Page SHALL posicionar microconversão próxima ao CTA principal
3. THE Landing_Page SHALL rastrear taxa de microconversão
4. WHEN visitante completa microconversão, THE Landing_Page SHALL apresentar CTA macro de forma não intrusiva
5. THE Landing_Page SHALL usar microconversão como caminho alternativo para visitantes com alta ansiedade

### Requisito 37: Modelo de Conversão Adaptado por Persona

**User Story:** Como visitante de perfil específico, quero caminho de conversão adequado ao meu nível de fricção, para que eu converta sem barreiras desnecessárias.

#### Acceptance Criteria

1. FOR Noiva/Debutante/Família, THE Landing_Page SHALL priorizar "Falar no WhatsApp" com mensagem pré-preenchida
2. FOR Assessora/Cerimonialista, THE Landing_Page SHALL priorizar "Agendar demonstração" com formulário qualificado (nome, empresa, tipo de evento, volume mensal)
3. FOR Produtor/Promotor/Corporativo, THE Landing_Page SHALL priorizar "Agendar demonstração" com formulário multi-step (nome, empresa, cargo, tipo de evento, volume, necessidades)
4. THE Landing_Page SHALL evitar formulário pesado para público social
5. THE Landing_Page SHALL rastrear taxa de conversão por tipo de CTA e persona

### Requisito 38: Alternância Visual Emocional/Funcional/Comercial

**User Story:** Como visitante, quero experiência visual variada, para que eu não sofra fadiga de padrão repetitivo.

#### Acceptance Criteria

1. THE Landing_Page SHALL alternar entre seções emocionais (fotografia real de evento), funcionais (UI do produto) e comerciais (layout limpo)
2. THE Landing_Page SHALL evitar mais de 2 seções consecutivas com mesmo padrão visual
3. THE Landing_Page SHALL usar fotografia real de eventos em pelo menos 3 seções
4. THE Landing_Page SHALL usar UI do produto em pelo menos 2 seções
5. THE Landing_Page SHALL manter ritmo visual que guia com clareza e reflete identidade da marca

### Requisito 39: Plano de Otimização Contínua

**User Story:** Como gestor de produto, quero plano de testes A/B, para que eu otimize conversão baseado em dados reais.

#### Acceptance Criteria

1. THE Landing_Page SHALL ter plano de experimentos pós-lançamento documentado
2. THE Landing_Page SHALL priorizar testes: hero por persona, ordem de seções, texto de CTA, tipo de prova social, CTA flutuante, WhatsApp vs formulário, headline resultado vs funcionalidade, trust signals posição e conteúdo
3. THE Landing_Page SHALL ter infraestrutura para testes A/B (variações, rastreamento, análise)
4. THE Landing_Page SHALL definir métrica primária de sucesso (taxa de conversão macro)
5. THE Landing_Page SHALL definir métricas secundárias (microconversão, scroll depth, tempo na página, bounce rate)

### Requisito 40: Performance como Prioridade de Conversão

**User Story:** Como visitante com conexão limitada, quero carregamento rápido, para que eu não abandone antes de ver a proposta de valor.

#### Acceptance Criteria

1. THE Landing_Page SHALL carregar acima da dobra com mínimo indispensável: headline forte, subheadline curta, 1 visual principal, 2 CTAs, faixa de prova
2. THE Landing_Page SHALL priorizar Core Web Vitals como métrica de negócio
3. THE Landing_Page SHALL lazy load todo conteúdo abaixo da dobra
4. THE Landing_Page SHALL otimizar imagens acima da dobra (WebP, dimensões corretas, preload)
5. THE Landing_Page SHALL atingir LCP ≤2.5s, FID ≤100ms, CLS ≤0.1 em ambiente de produção

## Grupo 4: Requisitos Técnicos

### Requisito 14: Refatoração de Componentes

**User Story:** Como desenvolvedor, quero componentes organizados e reutilizáveis, para que eu mantenha código limpo e performático.

#### Acceptance Criteria

1. THE Landing_Page SHALL criar novo componente CaptureChannelsSection para canais de entrada
2. THE Landing_Page SHALL fundir DynamicGallerySection, InteractiveGamesSection e DynamicWallSection em ExperienceModulesSection
3. THE Landing_Page SHALL fundir ModerationAISection em AISafetySection focada em moderação e segurança operacional
4. THE Landing_Page SHALL manter FaceRecognitionSection separada com foco emocional e comercial
5. THE Landing_Page SHALL reduzir número total de componentes de seção de 16 para até 12

### Requisito 21: Arquitetura de Dados da Landing

**User Story:** Como desenvolvedor, quero dados centralizados e tipados, para que eu mantenha consistência e facilite atualizações.

#### Acceptance Criteria

1. THE Landing_Page SHALL manter arquivo apps/landing/src/data/landing.ts como fonte única de dados
2. THE Landing_Page SHALL exportar tipos TypeScript para todas estruturas de dados
3. THE Landing_Page SHALL organizar dados por seção com nomenclatura clara
4. THE Landing_Page SHALL validar tipos em tempo de compilação
5. WHEN dados são atualizados, THE Landing_Page SHALL refletir mudanças em todos componentes dependentes

### Requisito 23: Integração com Sistema de Design

**User Story:** Como desenvolvedor, quero componentes consistentes com design system, para que eu mantenha coesão visual.

#### Acceptance Criteria

1. THE Landing_Page SHALL usar SCSS Modules para estilos de componentes
2. THE Landing_Page SHALL aplicar tokens de design via CSS variables
3. THE Landing_Page SHALL reutilizar componentes base quando aplicável
4. THE Landing_Page SHALL manter consistência de espaçamento usando escala definida
5. THE Landing_Page SHALL aplicar tipografia com hierarquia clara usando classes utilitárias

### Requisito 25: Validação de Dados da Landing

**User Story:** Como desenvolvedor, quero validar estrutura de dados em runtime, para que eu detecte erros cedo e mantenha consistência.

#### Acceptance Criteria

1. THE Landing_Page SHALL validar estrutura obrigatória de dados em runtime
2. THE Landing_Page SHALL detectar campos ausentes ou malformados
3. THE Landing_Page SHALL garantir consistência entre nav, seções e CTAs
4. THE Landing_Page SHALL emitir erros legíveis no build quando dados estão inválidos
5. THE Landing_Page SHALL usar Zod ou similar para validação de tipos em runtime

## Grupo 5: Requisitos Não Funcionais

### Requisito 16: Mobile First Excellence

**User Story:** Como visitante mobile, quero experiência excelente e não adaptação, para que eu navegue com mesma qualidade de desktop.

#### Acceptance Criteria

1. THE Landing_Page SHALL priorizar leitura fácil em mobile com cards maiores e menos elementos simultâneos
2. THE Landing_Page SHALL evitar blocos que viram poluição vertical infinita em mobile
3. WHEN Hero é renderizada em mobile, THE Landing_Page SHALL mostrar proposta de valor e CTA sem excesso acima de 600px
4. THE Landing_Page SHALL usar tabs/carrosséis com boa usabilidade no toque
5. WHEN Landing_Page é carregada em mobile com throttling 3G Fast (1.6Mbps), THE Landing_Page SHALL carregar conteúdo acima da dobra em ≤3s

### Requisito 17: Performance e Lazy Load

**User Story:** Como visitante com conexão limitada, quero página rápida, para que eu não abandone por lentidão.

#### Acceptance Criteria

1. THE Landing_Page SHALL preservar lazy load para imagens abaixo da dobra
2. THE Landing_Page SHALL carregar demos pesadas apenas quando necessário
3. THE Landing_Page SHALL reduzir peso visual acima da dobra em pelo menos 30%
4. THE Landing_Page SHALL manter sensação premium sem sacrificar velocidade
5. WHEN Landing_Page é carregada, THE Landing_Page SHALL atingir First Contentful Paint ≤1.5s em teste Lighthouse mobile (ambiente de homologação)

### Requisito 18: Motion Sutil e Premium

**User Story:** Como visitante, quero animações que reforcem qualidade, para que eu perceba produto de alto nível sem distração.

#### Acceptance Criteria

1. THE Landing_Page SHALL usar motion sutil e premium ao invés de espalhafatoso
2. THE Landing_Page SHALL aplicar microinterações em hover, tabs, entrada em viewport e carrossel
3. THE Landing_Page SHALL garantir que animação nunca compete com clareza do conteúdo
4. WHEN usuário tem prefers-reduced-motion ativo, THE Landing_Page SHALL desabilitar animações decorativas
5. THE Landing_Page SHALL usar duração de animação entre 200ms e 600ms em 90% dos casos

### Requisito 22: Acessibilidade e Semântica

**User Story:** Como visitante com necessidades especiais, quero landing acessível, para que eu navegue com tecnologias assistivas.

#### Acceptance Criteria

1. THE Landing_Page SHALL usar tags semânticas HTML5 corretas: header, nav, main, section, article, footer
2. THE Landing_Page SHALL fornecer textos alternativos descritivos para todas imagens
3. THE Landing_Page SHALL garantir navegação por teclado funcional em todos elementos interativos
4. THE Landing_Page SHALL manter contraste de cores WCAG AA em todos textos
5. WHEN seções são navegadas, THE Landing_Page SHALL atualizar foco de forma lógica e previsível

### Requisito 24: Testes e Validação

**User Story:** Como desenvolvedor, quero garantir qualidade da refatoração, para que eu não introduza regressões.

#### Acceptance Criteria

1. THE Landing_Page SHALL passar em validação TypeScript sem erros
2. THE Landing_Page SHALL renderizar todas seções sem erros de console
3. THE Landing_Page SHALL atingir Performance ≥85 e Accessibility ≥90 em teste Lighthouse mobile (ambiente de homologação, sem scripts de terceiros)
4. THE Landing_Page SHALL funcionar em Chrome, Firefox, Safari e Edge nas últimas 2 versões
5. WHEN Landing_Page é testada em mobile, THE Landing_Page SHALL funcionar em iOS Safari e Chrome Android
