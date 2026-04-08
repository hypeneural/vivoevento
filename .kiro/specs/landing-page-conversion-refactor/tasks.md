# Plano de Implementação: Refatoração Landing Page Evento Vivo

## Visão Geral

Refatoração profunda da landing page com foco em conversão comercial, reduzindo de 16 para 12 componentes de seção, implementando estratégia CRO com variações por persona, hero orientado a resultado, prova social contextualizada, microconversões e infraestrutura para testes A/B.

**Stack:** React 18 + TypeScript + Vite + SCSS Modules + GSAP + Motion + Lenis

**Prioridades:**
1. Decisões arquiteturais estruturais (renderização, SEO, lazy loading, medição, privacidade)
2. Arquitetura de dados e contextos separados
3. SEO e meta tags (antecipado para Fase 1-2)
4. Hero orientado a resultado com trust signals
5. Variações por persona
6. Prova social contextualizada
7. Performance real (RUM + laboratório)
8. Acessibilidade integrada por componente
9. Rollout controlado e rollback

**CONFLITOS RESOLVIDOS:**
- Conteúdo sem JS vs lazy load agressivo: HTML principal renderizado, lazy load apenas para demos pesadas (Phaser, Rive), vídeos e carrosséis
- FID substituído por INP conforme Web Vitals atualizados
- LandingContext quebrado em 3 contextos menores para evitar re-renders desnecessários
- SEO antecipado para Fase 1-2 (afeta renderização, roteamento, canonical)
- Acessibilidade integrada na implementação de componentes (não auditoria final)

## Tarefas

## Fase 0 — Decisões Arquiteturais

- [x] 0. Definir decisões estruturais que afetam toda implementação
  - [x] 0.1 Decidir estratégia de renderização
    - Avaliar: SPA pura vs prerender/SSR (Vite suporta SSR)
    - Considerar: SEO, performance, complexidade
    - Documentar decisão e justificativa
    - _Requirements: 30, 40_
  
  - [x] 0.2 Definir política de carregamento de conteúdo
    - **RESOLVER CONFLITO:** Requirements 30 exige "conteúdo principal com JS desabilitado" mas Task 16.1 original propunha "lazy load todas seções abaixo da dobra"
    - **DECISÃO:** Renderizar HTML principal das seções de conteúdo. Lazy load APENAS: demos pesadas (Phaser, Rive), vídeos, carrosséis pesados, visuais interativos
    - Justificativa: Google Search Central alerta que lazy loading mal implementado esconde conteúdo para crawling/indexação
    - Documentar estratégia de lazy loading seletivo
    - _Requirements: 30, 17, 40_
  
  - [x] 0.3 Definir estratégia de SEO técnico
    - Decidir canonical para ?persona=... (páginas distintas ou canonical para base?)
    - Planejar title, meta description, structured data
    - Planejar favicon e preview social
    - Documentar estratégia de meta tags por variação
    - _Requirements: 27_
  
  - [x] 0.4 Definir plano de medição e analytics
    - Planejar analytics (eventos, conversões, UTM)
    - Planejar RUM (Real User Monitoring) para LCP/INP/CLS reais
    - Planejar infraestrutura de A/B testing
    - Documentar eventos e métricas a rastrear
    - _Requirements: 26, 39, 40_
  
  - [x] 0.5 Definir política de privacidade e transparência
    - **ATENÇÃO ANPD:** Dados biométricos (busca facial) são sensíveis com proteção reforçada
    - Planejar comunicação de transparência (IA configurável, busca facial opcional)
    - Planejar microcopys de privacidade perto de CTAs
    - Planejar revisão jurídica/técnica antes de publicar copy final
    - Documentar política de transparência
    - _Requirements: 28, 33_

## Fase 1 — Fundação Técnica

- [x] 1. Configurar arquitetura de dados e schemas
  - [x] 1.1 Expandir `apps/landing/src/data/landing.ts` com tipos completos
    - Criar tipos para Hero (HeroContent, HeroVariations), HowItWorks, CaptureChannels, ExperienceModules, AISafety, FaceRecognition, Audience, Testimonials, Pricing, FAQ, FinalCTA
    - Adicionar tipos base: PersonaId, NavItem, TrustSignal, CTAConfig
    - Implementar estrutura de variações por persona (assessora, social, corporativo)
    - _Requirements: 21, 31_
  
  - [x] 1.2 Implementar validação de dados com Zod
    - Criar schemas Zod para tipos principais: PersonaIdSchema, TrustSignalSchema, CTAConfigSchema, HeroContentSchema
    - Implementar função `validateLandingData<T>` para validação em runtime
    - Adicionar validação de campos obrigatórios e tipos
    - _Requirements: 25, 21_

- [x] 2. Implementar contextos separados (evitar re-renders desnecessários)
  - [x] 2.1 Criar `PersonaContext`
    - Gerenciar: selectedPersona, entryVariation (muda raramente)
    - Detectar variação de entrada via URL params (?persona=assessora)
    - Persistir escolha de persona em localStorage
    - _Requirements: 31_
  
  - [x] 2.2 Criar `AttributionContext`
    - Gerenciar: utmParams (estático após load)
    - Capturar parâmetros UTM na inicialização
    - _Requirements: 26_
  
  - [x] 2.3 Criar `ScrollUIContext`
    - Gerenciar: activeSection, showFloatingCTA (muda frequentemente no scroll)
    - Implementar tracking de scroll para CTA flutuante (aparece após 80%)
    - Separado dos outros contextos para evitar re-render de toda árvore
    - _Requirements: 20, 26_
  
  - [x] 2.4 Criar hooks customizados
    - Implementar `usePersonaContent<T>` para conteúdo adaptado por persona
    - Implementar `useScrollProgress` para tracking de progresso de scroll
    - Implementar `useIntersectionObserver` para detectar seção no viewport
    - Implementar `useLandingData<T>` para dados com fallback
    - _Requirements: 31, 26_

- [x] 3. Implementar utilitários de roteamento e tracking
  - [x] 3.1 Criar `apps/landing/src/utils/routing.ts`
    - Implementar getPersonaFromURL, setPersonaInURL
    - Implementar getUTMParams
    - Implementar buildCTAUrl com propagação de UTM params
    - _Requirements: 26, 37_
  
  - [x] 3.2 Criar infraestrutura de feature flags mínima
    - Preparar sistema para variações de experimentos
    - Implementar tracking de experimentos
    - _Requirements: 39_
  
  - [x] 3.3 Implementar tracking base de eventos
    - Configurar tracking de cliques em CTAs
    - Configurar tracking de scroll depth
    - Configurar tracking de interações
    - Preparar estrutura para RUM (será instrumentado na Fase 5)
    - _Requirements: 26_

- [x] 4. Implementar SEO técnico e meta tags (ANTECIPADO)
  - [x] 4.1 Configurar meta tags essenciais
    - Implementar title e meta description únicos
    - Implementar Open Graph tags completas (og:title, og:description, og:image, og:url)
    - Implementar Twitter Card tags
    - Implementar canonical tags conforme decisão 0.3
    - _Requirements: 27_
  
  - [x] 4.2 Implementar structured data
    - Adicionar JSON-LD para Organization
    - Adicionar JSON-LD para WebPage
    - Validar com Google Rich Results Test
    - _Requirements: 27_
  
  - [x] 4.3 Criar preview social
    - Criar imagem de preview 1200x630px
    - Adicionar favicon
    - Testar preview em Facebook, Twitter, LinkedIn
    - _Requirements: 27_
  
  - [x] 4.4 Implementar meta tags por variação de persona
    - Adaptar title/description para ?persona=assessora
    - Adaptar title/description para ?persona=social
    - Adaptar title/description para ?persona=corporativo
    - Garantir canonical correto conforme decisão 0.3
    - _Requirements: 27, 31_

- [x] 5. Checkpoint - Validar fundação técnica
  - ✅ Build verde (npm run build)
  - ✅ Type-check verde (npm run type-check)
  - ✅ Sem erro de console em desenvolvimento
  - ✅ Contextos separados funcionando
  - ✅ UTM params sendo capturados
  - ✅ Meta tags renderizando corretamente

## Fase 2 — Experiência Acima da Dobra

- [x] 6. Refatorar Navbar e estrutura base
  - [x] 6.1 Simplificar Navbar
    - Reduzir âncoras para 6 itens: Como funciona, Recursos, Para quem é, Depoimentos, Planos, FAQ
    - Implementar estado compacto com backdrop blur após 72px de scroll
    - Adicionar CTA primário "Agendar demonstração" e secundário WhatsApp
    - _Requirements: 2, 20_
  
  - [x] 6.2 Atualizar App.tsx com Providers
    - Envolver aplicação com `<PersonaProvider>`, `<AttributionProvider>`, `<ScrollUIProvider>`
    - Implementar lazy loading SELETIVO: HTML principal renderizado, lazy load apenas para demos pesadas (Phaser, Rive), vídeos, carrosséis
    - Configurar Suspense boundaries com fallbacks apropriados
    - _Requirements: 17, 40, 30_

- [x] 7. Refatorar Hero Section com foco em conversão
  - [x] 7.1 Criar novo HeroSection orientado a resultado
    - Implementar headline centrada em resultado: "Os convidados já estão tirando fotos. Agora elas viram experiência ao vivo no seu evento."
    - Adicionar estrutura de flowSteps (3-4 passos visuais)
    - Implementar 2 CTAs: primário "Agendar demonstração" e secundário "Falar no WhatsApp"
    - Adicionar métricas de impacto (QR + Web, Tempo real, IA ativa)
    - _Requirements: 3, 32, 20_
  
  - [x] 7.2 Implementar faixa de trust signals
    - Criar componente `TrustSignals` com 4 sinais: Sem app, Fotos/vídeos por WhatsApp/Telegram/link, Moderação por IA, Busca facial configurável
    - Posicionar logo abaixo dos CTAs no hero
    - Implementar layout responsivo para mobile
    - Adicionar microcopys de transparência conforme decisão 0.5
    - _Requirements: 3, 33, 28_
  
  - [x] 7.3 Implementar variações de hero por persona
    - Criar conteúdo específico para assessora (controle + segurança)
    - Criar conteúdo específico para social (emoção + facilidade)
    - Criar conteúdo específico para corporativo (engajamento + escala)
    - Integrar com `usePersonaContent` hook
    - _Requirements: 31, 32_
  
  - [x] 7.4 Garantir acessibilidade do Hero
    - Implementar hierarquia de headings válida (h1 único)
    - Garantir contraste WCAG AA (≥4.5:1) em todos textos
    - Garantir navegação por teclado em CTAs
    - Adicionar aria-labels apropriados
    - _Requirements: 22_

- [x] 8. Checkpoint - Validar hero e navbar
  - ✅ Build verde
  - ✅ Type-check verde
  - ✅ Hero renderizando com variações por persona
  - ✅ Trust signals visíveis
  - ✅ CTAs funcionais com UTM params
  - ✅ Navbar compacta após scroll
  - ✅ Acessibilidade: navegação por teclado, contraste, headings
  - ✅ Mobile: proposta + CTA em ≤600px altura

## Fase 3 — Seções Principais

- [x] 9. Criar novas seções didáticas
  - [x] 9.1 Criar HowItWorksSection
    - Implementar fluxo em 3-4 passos visuais: Convidados enviam → Plataforma recebe → IA modera → Aparece em galeria/telão/jogos
    - Usar layout limpo com ilustrações/ícones
    - Otimizar para caber em ≤100vh em desktop
    - Implementar acessibilidade: headings, alt text, navegação por teclado
    - _Requirements: 4, 22_
  
  - [x] 9.2 Criar CaptureChannelsSection
    - Apresentar 5 canais: WhatsApp número, WhatsApp grupo, Telegram, Link/QR Code, Upload web
    - Usar cards grandes ou grid visual
    - Mostrar exemplo visual de uso para cada canal
    - Otimizar para 60-80 vh em desktop
    - Implementar acessibilidade: headings, alt text, contraste
    - _Requirements: 5, 22_

- [x] 10. Fusionar módulos de experiência
  - [x] 10.1 Criar ExperienceModulesSection unificada
    - Fundir DynamicGallerySection, InteractiveGamesSection, DynamicWallSection em componente único
    - Implementar sistema de tabs ou alternância visual
    - Apresentar 4 módulos: Galeria ao vivo, Telão dinâmico, Jogos interativos, Busca facial
    - **CLARIFICAÇÃO:** Prioridade é de MÓDULOS (galeria, telão, jogos, busca facial), não de SEÇÕES
    - _Requirements: 6, 14_
  
  - [x] 10.2 Implementar conteúdo de módulos com priorização por persona
    - Definir ordem prioritária para assessora: Galeria + IA + Busca facial
    - Definir ordem prioritária para social: Jogos + Busca facial + Galeria
    - Definir ordem prioritária para corporativo: Telão + Jogos + IA
    - Implementar ModulePriority no landing.ts
    - _Requirements: 6, 31_
  
  - [x] 10.3 Implementar visual de módulos
    - Criar ModuleMedia para visual principal (imagem/vídeo/demo)
    - Criar ModuleFeatures para até 3 pontos de apoio textuais
    - Criar ModuleCTA para ação contextual
    - Otimizar para 100-120vh em desktop
    - _Requirements: 6, 1_
  
  - [x] 10.4 Garantir acessibilidade de tabs/módulos
    - Implementar padrão APG WAI para Tabs
    - Garantir navegação por teclado (Arrow keys, Home, End)
    - Implementar aria-selected, aria-controls, role="tablist"
    - Garantir foco visível
    - _Requirements: 22_

- [x] 11. Refatorar seções de IA e segurança
  - [x] 11.1 Criar AISafetySection focada em segurança operacional
    - Fundir ModerationAISection em nova seção focada em moderação e segurança
    - Focar em: moderação, aprovação inteligente, respostas por IA, operação segura
    - Evitar jargão técnico (embeddings, pipeline, threshold, vetores)
    - Apresentar demo visual de moderação em ação
    - Implementar acessibilidade: headings, alt text, contraste
    - _Requirements: 7, 14, 15, 22_
  
  - [x] 11.2 Refatorar FaceRecognitionSection com apelo emocional
    - Manter como seção dedicada com foco emocional
    - Comunicar: "Encontre as fotos do noivo, debutante, aniversariante ou de um convidado em segundos"
    - Mostrar fluxo visual: selfie → resultados
    - Usar linguagem orientada a benefício
    - Implementar acessibilidade: headings, alt text, contraste
    - _Requirements: 8, 14, 22_

- [x] 12. Checkpoint - Validar seções principais
  - ✅ Build verde
  - ✅ Type-check verde
  - ✅ Todas seções renderizando corretamente
  - ✅ Variações por persona funcionando
  - ✅ Acessibilidade: tabs com padrão APG WAI, navegação por teclado, headings válidos
  - ✅ Mobile: conteúdo legível e funcional
  - ✅ Screenshot/visual review aprovado

## Fase 4 — Conversão

- [x] 13. Refatorar seções de conversão
  - [x] 13.1 Refatorar AudienceSection com cards escaneáveis
    - **CLARIFICAÇÃO:** São 3 cards principais: Assessoras/Cerimonialistas, Noivas/Debutantes/Famílias, Promotores/Produtores/Corporativos
    - Cada card responde: promessa principal, módulos prioritários, objeções principais
    - Implementar hierarquia clara para escaneamento em <10s
    - Otimizar para 80-100vh em desktop
    - Implementar acessibilidade: headings, contraste, navegação por teclado
    - _Requirements: 9, 19, 22_
  
  - [x] 13.2 Implementar PersonaSelector interativo
    - Criar componente para seleção de persona antes da seção de módulos
    - Oferecer 3 opções com copy claro
    - Persistir escolha durante navegação
    - Adaptar headline secundária, prova social, ordem de módulos e CTA contextual
    - Implementar acessibilidade: navegação por teclado, aria-labels
    - _Requirements: 35, 31, 22_

- [-] 14. Refatorar prova social contextualizada
  - [x] 14.1 Refatorar TestimonialsSection com contexto
    - Organizar em 3 blocos: Casamentos e debutantes, Assessoria e cerimonial, Eventos e ativações
    - Incluir em cada depoimento: contexto de evento, volume de fotos, resultado mensurável
    - Adicionar foto real do evento relacionado
    - Apresentar 3-5 depoimentos reais
    - Implementar acessibilidade: headings, alt text para fotos de eventos
    - _Requirements: 10, 34, 22_
  
  - [x] 14.2 Implementar adaptação de prova social por persona
    - Priorizar depoimentos de casamento para persona social
    - Priorizar depoimentos de assessoria para persona assessora
    - Priorizar depoimentos corporativos para persona corporativo
    - Integrar com `usePersonaContent` hook
    - _Requirements: 34, 31_
  
  - [x] 14.3 Criar fallbacks SEGUROS para depoimentos
    - **ATENÇÃO:** Depoimentos fallback com cara de real em produção é arriscado
    - Em produção, usar: placeholder visual de layout, case temporariamente oculto, ou bloco "exemplo ilustrativo" bem marcado
    - Implementar FALLBACK_TESTIMONIALS com mínimo 3 depoimentos
    - Criar fallback visual para fotos de eventos ausentes
    - Implementar `useLandingData` para usar dados com fallback
    - Fallbacks realistas APENAS em desenvolvimento
    - _Requirements: 29_

- [ ] 15. Refatorar seções comerciais finais
  - [x] 15.1 Refatorar PricingSection com clareza comercial
    - Separar 3 categorias: evento avulso, parceiros recorrentes, operação enterprise
    - Destacar visualmente plano mais comum
    - Reduzir ruído visual em 40%
    - Apresentar público-alvo, preço e diferencial sem scroll
    - Implementar acessibilidade: headings, contraste, navegação por teclado
    - _Requirements: 11, 19, 22_
  
  - [x] 15.2 Refatorar FAQSection enxuta
    - Reduzir para 7-10 perguntas comerciais e de objeção
    - Incluir: precisa de app?, aceita vídeo?, funciona com muito volume?, moderação?, busca facial?, serve para casamento/formatura?, branding?
    - Implementar accordion com apenas uma pergunta aberta por vez
    - Respostas concisas (≤3 linhas)
    - Implementar acessibilidade: padrão APG WAI para Accordion, navegação por teclado
    - _Requirements: 12, 22_
  
  - [x] 15.3 Refatorar FinalCTASection
    - Título forte e subtítulo curto
    - Exatamente 2 botões: "Agendar demonstração" e "Falar no WhatsApp"
    - Fundo limpo e contrastante
    - Otimizar para 50-70vh em desktop
    - Implementar acessibilidade: contraste, navegação por teclado
    - _Requirements: 13, 20, 22_

- [x] 16. Implementar microconversões
  - [x] 16.1 Adicionar CTAs de microconversão
    - Implementar "Ver evento exemplo", "Ver como funciona em 30s", "Abrir demonstração visual"
    - Posicionar próximo ao CTA principal
    - Implementar tracking de microconversão
    - _Requirements: 36_
  
  - [x] 16.2 Implementar CTA flutuante
    - Criar componente CTAFloating que aparece após 80% de scroll
    - Implementar lógica no ScrollUIContext
    - Design discreto e não intrusivo
    - Implementar acessibilidade: não bloquear conteúdo, permitir fechar
    - _Requirements: 20, 36, 22_

- [x] 17. Implementar modelo de conversão adaptado
  - [x] 17.1 Criar CTAs específicos por persona
    - Para social: priorizar "Falar no WhatsApp" com mensagem pré-preenchida
    - Para assessora: priorizar "Agendar demonstração" com formulário qualificado
    - Para corporativo: priorizar "Agendar demonstração" com formulário multi-step
    - _Requirements: 37_
  
  - [x] 17.2 Implementar buildCTAUrl com UTM params
    - Criar função em `apps/landing/src/utils/routing.ts`
    - Capturar e propagar parâmetros UTM em todos CTAs
    - Implementar getUTMParams, setPersonaInURL, getPersonaFromURL
    - _Requirements: 26, 37_

- [x] 18. Checkpoint - Validar conversão e CTAs
  - ✅ Build verde
  - ✅ Type-check verde
  - ✅ Todos CTAs funcionais com UTM params
  - ✅ Variações por persona funcionando
  - ✅ Prova social contextualizada
  - ✅ Fallbacks seguros implementados
  - ✅ Acessibilidade: accordion e tabs com padrões APG WAI
  - ✅ Mobile: CTAs e formulários funcionais

## Fase 5 — Não Funcionais Integrados

- [x] 19. Implementar copy premium e hierarquia visual
  - [x] 19.1 Revisar e otimizar copy de todas seções
    - Reduzir texto por bloco em 40%
    - Priorizar benefício antes de tecnologia
    - Evitar jargão técnico
    - Frases concisas (≤20 palavras)
    - _Requirements: 15_
  
  - [x] 19.2 Implementar hierarquia visual clara
    - Garantir 1 mensagem principal por dobra
    - Melhorar contraste entre título, corpo e elementos auxiliares
    - Aumentar espaço em branco em 50%
    - Alternar padrões visuais entre seções consecutivas
    - _Requirements: 19, 38_
  
  - [x] 19.3 Implementar alternância visual emocional/funcional/comercial
    - Alternar entre fotografia real de evento, UI do produto e layout limpo
    - Evitar mais de 2 seções consecutivas com mesmo padrão
    - Usar fotografia real em ≥3 seções
    - Usar UI do produto em ≥2 seções
    - _Requirements: 38, 1_

- [x] 20. Otimizar performance REAL (não apenas lazy load agressivo)
  - [x] 20.1 Implementar lazy loading SELETIVO (conforme decisão 0.2)
    - Carregar imediato: Navbar, HeroSection, TrustSignals, HTML principal das seções
    - Lazy load APENAS: demos pesadas (PhaserGameDemo, RiveAnimation), vídeos, carrosséis pesados
    - Configurar Suspense boundaries apropriados
    - _Requirements: 17, 40, 30_
  
  - [x] 20.2 Otimizar imagens e assets
    - Implementar preload para imagens acima da dobra
    - Converter imagens para WebP quando possível
    - Implementar dimensões corretas e srcset responsivo
    - Adicionar loading="lazy" para imagens abaixo da dobra
    - _Requirements: 17, 40_
  
  - [x] 20.3 Otimizar Core Web Vitals (INP, não FID)
    - **ATUALIZAÇÃO:** Web Vitals substituiu FID por INP
    - Reduzir peso visual acima da dobra em 30%
    - Otimizar para LCP ≤2.5s, INP ≤200ms (não FID), CLS ≤0.1
    - Implementar code splitting por seção
    - Minimizar JavaScript acima da dobra
    - _Requirements: 40_
  
  - [x] 20.4 Instrumentar RUM (Real User Monitoring)
    - **NOVO:** Implementar envio de LCP/INP/CLS reais para analytics
    - Diferenciar métricas de laboratório (Lighthouse) vs campo (RUM)
    - Rastrear Web Vitals em produção para experiência real
    - Configurar alertas para degradação de performance
    - _Requirements: 26, 40_

- [x] 21. Implementar motion e animações
  - [x] 21.1 Adicionar microinterações premium
    - Implementar hover states sutis
    - Adicionar transições em tabs e carrosséis
    - Implementar animações de entrada em viewport
    - Duração entre 200ms-600ms em 90% dos casos
    - _Requirements: 18_
  
  - [x] 21.2 Implementar suporte a prefers-reduced-motion
    - Detectar preferência do usuário
    - Desabilitar animações decorativas quando ativo
    - Manter transições funcionais essenciais
    - _Requirements: 18, 30_

- [x] 22. Implementar estados de erro e fallbacks
  - [x] 22.1 Implementar degradação graciosa
    - Exibir conteúdo principal com JS desabilitado (conforme decisão 0.2)
    - Implementar fallback para imagens que falharem
    - Implementar fallback para vídeos indisponíveis
    - Manter CTAs funcionais com falha de componentes interativos
    - _Requirements: 30, 29_
  
  - [x] 22.2 Implementar fallbacks de conteúdo
    - Criar FALLBACK_IMAGES para todas imagens críticas
    - Criar FALLBACK_TESTIMONIALS (mínimo 3, seguros para produção)
    - Implementar estados de loading apropriados
    - _Requirements: 29_

- [ ] 23. Implementar privacidade e transparência (conforme decisão 0.5)
  - [ ] 23.1 Adicionar comunicação de privacidade
    - **ATENÇÃO ANPD:** Indicar que IA e busca facial são configuráveis
    - Explicar níveis de moderação (sem, manual, IA)
    - Adicionar microcopys de transparência perto dos CTAs
    - Revisar copy com perspectiva jurídica/técnica
    - _Requirements: 28, 33_
  
  - [ ] 23.2 Adicionar links de privacidade
    - Incluir link para política de privacidade no footer
    - Incluir link para termos de uso no footer
    - Posicionar link de privacidade perto de CTAs principais
    - _Requirements: 28, 33_

- [ ] 24. Remover componentes obsoletos
  - [ ] 24.1 Remover seções antigas
    - Remover DynamicGallerySection.tsx e .module.scss
    - Remover InteractiveGamesSection.tsx e .module.scss
    - Remover DynamicWallSection.tsx e .module.scss
    - Remover ModerationAISection.tsx e .module.scss
    - Remover TechnicalTrustSection.tsx e .module.scss
    - Remover ComparisonSection.tsx e .module.scss
    - _Requirements: 14, 1_
  
  - [ ] 24.2 Atualizar App.tsx com nova estrutura
    - Atualizar imports para novos componentes
    - Garantir ordem correta: Hero → HowItWorks → CaptureChannels → ExperienceModules → AISafety → FaceRecognition → Audience → Testimonials → Pricing → FAQ → FinalCTA
    - Validar que total de seções é ≤12
    - _Requirements: 1, 14_

- [ ] 25. Checkpoint - Validar implementação completa
  - ✅ Build verde
  - ✅ Type-check verde
  - ✅ Sem erros de console
  - ✅ Performance: LCP ≤2.5s, INP ≤200ms, CLS ≤0.1
  - ✅ RUM instrumentado e enviando métricas
  - ✅ Acessibilidade: navegação por teclado, contraste, headings, ARIA
  - ✅ Mobile-first: paridade de conteúdo
  - ✅ Fallbacks seguros implementados
  - ✅ Privacidade e transparência comunicadas

## Fase 6 — Publicação Controlada

- [ ] 26. Testes e validação final
  - [ ] 26.1 Validar TypeScript e build
    - Executar `npm run type-check` sem erros
    - Executar `npm run build` com sucesso
    - Validar que não há erros de console em desenvolvimento
    - _Requirements: 24_
  
  - [ ] 26.2 Testar responsividade e mobile-first
    - Testar em mobile (iOS Safari, Chrome Android)
    - Testar em tablet
    - Testar em desktop (Chrome, Firefox, Safari, Edge últimas 2 versões)
    - Validar que hero mobile mantém proposta + CTA em ≤600px altura
    - **MOBILE-FIRST:** Garantir paridade de conteúdo (Google usa mobile-first indexing)
    - _Requirements: 16, 24_
  
  - [ ] 26.3 Executar auditorias Lighthouse (laboratório)
    - Executar Lighthouse mobile em ambiente de homologação
    - Validar Performance ≥85
    - Validar Accessibility ≥90
    - Validar FCP ≤1.5s
    - _Requirements: 17, 24, 40_
  
  - [ ] 26.4 Validar SEO e structured data
    - Executar Google Rich Results Test
    - Validar preview social (Facebook, Twitter, LinkedIn)
    - Validar canonical tags
    - Validar meta tags por variação de persona
    - Testar Open Graph debugger
    - _Requirements: 27_
  
  - [ ] 26.5 Validar navegação e interações
    - Testar navegação por teclado em todos elementos
    - Testar todos CTAs (primário, secundário, microconversões)
    - Testar PersonaSelector e adaptação de conteúdo
    - Testar FAQ accordion (padrão APG WAI)
    - Testar tabs/carrossel de módulos (padrão APG WAI)
    - _Requirements: 22, 24_
  
  - [ ] 26.6 Validar tracking e analytics
    - Validar captura de UTM params
    - Validar tracking de cliques em CTAs
    - Validar tracking de scroll depth
    - Validar tracking de interações (planos, FAQ, módulos, persona)
    - Validar RUM enviando métricas reais
    - _Requirements: 26_

- [ ] 27. Preparar rollout controlado
  - [ ] 27.1 Configurar preview deploy
    - Deploy em ambiente de staging
    - Gerar URL de preview para QA
    - Validar todas funcionalidades em staging
    - _Requirements: 24_
  
  - [ ] 27.2 Implementar feature flag para landing nova/antiga
    - Criar flag para alternar entre landing antiga e nova
    - Testar alternância sem quebrar experiência
    - Preparar rollback simples
    - _Requirements: 39_
  
  - [ ] 27.3 Configurar monitoramento pós-publicação
    - Configurar alertas para erros JavaScript
    - Configurar alertas para degradação de performance (RUM)
    - Configurar alertas para queda de conversão
    - Planejar monitoramento 24-72h pós-lançamento
    - _Requirements: 26, 40_

- [ ] 28. Checkpoint final - Preparar para produção
  - ✅ Build verde em staging
  - ✅ Lighthouse: Performance ≥85, Accessibility ≥90
  - ✅ RUM instrumentado e funcionando
  - ✅ SEO: Rich Results Test aprovado, preview social validado
  - ✅ Acessibilidade: navegação por teclado, padrões APG WAI, contraste
  - ✅ Mobile-first: paridade de conteúdo, funcional em iOS/Android
  - ✅ Feature flag funcionando
  - ✅ Rollback testado
  - ✅ Monitoramento configurado
  - ✅ QA aprovado

## Notas

- Tasks marcadas com `*` são opcionais e podem ser puladas para MVP mais rápido
- Cada task referencia requisitos específicos para rastreabilidade
- Checkpoints garantem validação incremental com gates objetivos
- Prioridade: decisões arquiteturais → fundação técnica → hero → seções principais → conversão → não funcionais → rollout
- Total de seções após refatoração: 12 (redução de 16 para 12)
- Foco em conversão real: variações por persona, trust signals, prova social contextualizada, microconversões

## Matriz de Rastreabilidade (Requirements 31-40)

| Requirement | Tasks |
|-------------|-------|
| 31 - Variação por Persona | 0.3, 1.1, 2.1, 2.4, 4.4, 7.3, 10.2, 13.2, 14.2 |
| 32 - Hero Orientado a Resultado | 7.1, 7.3 |
| 33 - Trust Layer e Transparência | 0.5, 7.2, 23.1, 23.2 |
| 34 - Prova Social Contextualizada | 14.1, 14.2 |
| 35 - Seletor de Persona Interativo | 13.2 |
| 36 - Microconversões | 16.1, 16.2 |
| 37 - Modelo de Conversão Adaptado | 3.1, 17.1, 17.2 |
| 38 - Alternância Visual | 19.3 |
| 39 - Plano de Otimização Contínua | 0.4, 3.2, 27.2 |
| 40 - Performance como Prioridade | 0.1, 0.2, 0.4, 6.2, 20.1, 20.2, 20.3, 20.4, 27.3 |

## Correções Críticas Aplicadas

1. ✅ **Fase 0 adicionada** - Decisões arquiteturais antes da implementação
2. ✅ **Lazy loading corrigido** - HTML principal renderizado, lazy load apenas demos pesadas
3. ✅ **LandingContext quebrado** - 3 contextos separados (Persona, Attribution, ScrollUI)
4. ✅ **FID → INP** - Métricas atualizadas conforme Web Vitals
5. ✅ **SEO antecipado** - Movido para Fase 1-2
6. ✅ **Acessibilidade integrada** - Por componente, não auditoria final
7. ✅ **Mobile-first** - Paridade de conteúdo garantida
8. ✅ **Inconsistências resolvidas** - Prioridade de módulos clarificada, 3 cards de audience confirmados
9. ✅ **Checkpoints fortalecidos** - Gates objetivos ao invés de vagos
10. ✅ **Experimentos antecipados** - Infraestrutura antes das refatorações
11. ✅ **RUM explícito** - Métricas reais além de laboratório
12. ✅ **Biometria e transparência** - ANPD considerada, revisão jurídica planejada
13. ✅ **Fallbacks seguros** - Depoimentos realistas apenas em desenvolvimento
14. ✅ **Rollout e rollback** - Fase 6 completa adicionada
