// ============================================
// TIPOS BASE
// ============================================

export type PersonaId = 'assessora' | 'social' | 'corporativo';

export type NavItem = {
  id: string;
  label: string;
};

export type TrustSignal = {
  id: string;
  icon: string;
  text: string;
  detail?: string;
};

export type CTAConfig = {
  primary: {
    text: string;
    url: string;
    icon?: string;
  };
  secondary: {
    text: string;
    url: string;
    icon?: string;
  };
};

export type EventImage = {
  src: string;
  alt: string;
};

export type VisualModule = {
  id: string;
  title: string;
  eyebrow: string;
  benefit: string;
  detail: string;
  accent: "cyan" | "warm" | "blue" | "green" | "coral";
};

// ============================================
// HERO
// ============================================

export type HeroContent = {
  eyebrow: string;
  headline: {
    lead: string;
    accent: string[];
  };
  subheadline: string;
  flowSteps: Array<{
    number: number;
    title: string;
    detail: string;
  }>;
  ctas: CTAConfig;
  trustSignals: TrustSignal[];
  metrics: Array<{
    value: string;
    label: string;
  }>;
};

export type HeroVariations = {
  assessora: HeroContent;
  social: HeroContent;
  corporativo: HeroContent;
};

// ============================================
// COMO FUNCIONA
// ============================================

export type HowItWorksStep = {
  id: string;
  number: number;
  title: string;
  description: string;
  icon: string;
  visual?: string;
};

export type HowItWorksContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  steps: HowItWorksStep[];
};

// ============================================
// CANAIS DE CAPTURA
// ============================================

export type CaptureChannel = {
  id: string;
  name: string;
  description: string;
  icon: string;
  visual: string;
  features: string[];
  useCases: string[];
};

export type CaptureChannelsContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  channels: CaptureChannel[];
};

// ============================================
// MÓDULOS DE EXPERIÊNCIA
// ============================================

export type ExperienceModule = {
  id: 'gallery' | 'wall' | 'games' | 'face';
  name: string;
  eyebrow: string;
  title: string;
  benefit: string;
  features: string[];
  visual: {
    type: 'image' | 'video' | 'demo';
    src: string;
    alt: string;
    poster?: string;
  };
  cta?: {
    text: string;
    action: string;
  };
};

export type ModulePriority = {
  assessora: ExperienceModule['id'][];
  social: ExperienceModule['id'][];
  corporativo: ExperienceModule['id'][];
};

export type ExperienceModulesContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  modules: ExperienceModule[];
  priority: ModulePriority;
};

// ============================================
// IA E SEGURANÇA
// ============================================

export type AISafetyFeature = {
  id: string;
  title: string;
  description: string;
  icon: string;
  benefit: string;
};

export type AISafetyContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  features: AISafetyFeature[];
  demo: {
    type: 'visual' | 'interactive';
    src: string;
    alt: string;
  };
  trustNote: string;
};

// ============================================
// RECONHECIMENTO FACIAL
// ============================================

export type FaceRecognitionContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  emotionalHook: string;
  flowSteps: Array<{
    number: number;
    title: string;
    visual: string;
  }>;
  benefits: string[];
  demo: {
    selfie: string;
    matches: Array<{
      src: string;
      confidence: number;
    }>;
  };
};

// ============================================
// AUDIÊNCIA (PERSONAS)
// ============================================

export type AudienceProfile = {
  id: PersonaId;
  name: string;
  icon: string;
  promise: string;
  priorityModules: string[];
  objections: Array<{
    question: string;
    answer: string;
  }>;
  cta: {
    text: string;
    url: string;
  };
};

export type AudienceContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  profiles: AudienceProfile[];
};

// ============================================
// DEPOIMENTOS
// ============================================

export type TestimonialContext = 'casamento' | 'assessoria' | 'corporativo';

export type Testimonial = {
  id: string;
  context: TestimonialContext;
  quote: string;
  author: {
    name: string;
    role: string;
    photo?: string;
  };
  event: {
    type: string;
    volume: string;
    photo: string;
  };
  highlight: string;
  result: string;
};

export type TestimonialsContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  testimonials: Testimonial[];
  contextGroups: {
    casamento: Testimonial[];
    assessoria: Testimonial[];
    corporativo: Testimonial[];
  };
};

// ============================================
// PLANOS
// ============================================

export type PricingPlan = {
  id: string;
  name: string;
  audience: string;
  price: string;
  badge?: string;
  highlight: boolean;
  features: string[];
  cta: {
    text: string;
    url: string;
  };
};

export type PricingContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  plans: PricingPlan[];
  note: string;
};

// ============================================
// FAQ
// ============================================

export type FAQItem = {
  id: string;
  question: string;
  answer: string;
  category?: 'comercial' | 'tecnico' | 'operacional';
};

export type FAQContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  items: FAQItem[];
};

// ============================================
// CTA FINAL
// ============================================

export type FinalCTAContent = {
  title: string;
  subtitle: string;
  ctas: CTAConfig;
  trustSignals: TrustSignal[];
};

export const navItems: NavItem[] = [
  { id: "como-funciona", label: "Como funciona" },
  { id: "recursos", label: "Recursos" },
  { id: "para-quem-e", label: "Para quem é" },
  { id: "depoimentos", label: "Depoimentos" },
  { id: "planos", label: "Planos" },
  { id: "faq", label: "FAQ" },
];

export const eventImages = {
  heroMobile: {
    src: "https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=720&q=80",
    alt: "Convidados fotografando um evento com o celular",
  },
  galleryFeatured: {
    src: "https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=900&q=80",
    alt: "Grupo de convidados celebrando em uma pista de dança",
  },
  gallerySecondary: {
    src: "https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=80",
    alt: "Casal em evento com luzes quentes",
  },
  galleryThird: {
    src: "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=900&q=80",
    alt: "Público levantando os braços em um show",
  },
  galleryFourth: {
    src: "https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=900&q=80",
    alt: "Evento corporativo com pessoas interagindo",
  },
  galleryFifth: {
    src: "https://images.unsplash.com/photo-1520854221256-17451cc331bf?auto=format&fit=crop&w=900&q=80",
    alt: "Convidados sorrindo em uma festa",
  },
  gallerySixth: {
    src: "https://images.unsplash.com/photo-1545239351-1141bd82e8a6?auto=format&fit=crop&w=900&q=80",
    alt: "Plateia com luzes de palco ao fundo",
  },
  gallerySeventh: {
    src: "https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?auto=format&fit=crop&w=900&q=80",
    alt: "Celebração em um evento premium",
  },
  wallHero: {
    src: "https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=1400&q=80",
    alt: "Noivos em destaque em um telão de evento",
  },
  faceSelfie: {
    src: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=720&q=80",
    alt: "Mulher posando para selfie",
  },
  faceMatchOne: {
    src: "https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=720&q=80",
    alt: "Convidada em pista de dança",
  },
  faceMatchTwo: {
    src: "https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=720&q=80",
    alt: "Convidada em evento corporativo",
  },
  faceMatchThree: {
    src: "https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=720&q=80",
    alt: "Convidados reunidos em selfie de grupo",
  },
};

export const heroBullets = [
  "Galeria dinâmica com destaque automático para as fotos recém-chegadas",
  "Puzzle e jogo da memória alimentados pelas imagens reais do evento",
  "Telão premium com layouts vivos e atualização contínua em tempo real",
  "Moderação por IA e busca facial sem depender de fluxos manuais",
];

export const heroMetrics = [
  { value: "QR + Web", label: "entrada sem app" },
  { value: "Tempo real", label: "galeria, jogo e telão sincronizados" },
  { value: "IA ativa", label: "moderação e busca facial no mesmo fluxo" },
];

// ============================================
// HERO VARIATIONS BY PERSONA
// ============================================

export const heroVariations: HeroVariations = {
  assessora: {
    eyebrow: "Plataforma premium de experiências ao vivo",
    headline: {
      lead: "Controle total.",
      accent: ["Moderação inteligente,", "operação segura."],
    },
    subheadline: "Receba por QR Code, aprove com IA e transforme o mesmo envio em galeria dinâmica, jogos interativos, telão ao vivo e busca facial em segundos.",
    flowSteps: [
      {
        number: 1,
        title: "Convidado envia",
        detail: "QR + web, sem app",
      },
      {
        number: 2,
        title: "IA decide",
        detail: "aprova, bloqueia e indexa",
      },
      {
        number: 3,
        title: "Publica experiência",
        detail: "galeria, jogos, telão e match",
      },
    ],
    ctas: {
      primary: {
        text: "Agendar demonstração",
        url: "",
      },
      secondary: {
        text: "Falar no WhatsApp",
        url: "",
      },
    },
    trustSignals: [
      {
        id: "no-app",
        icon: "Smartphone",
        text: "Sem app",
        detail: "Convidados entram por QR Code e web",
      },
      {
        id: "ai-moderation",
        icon: "ShieldCheck",
        text: "Moderação por IA",
        detail: "Aprovação inteligente antes de publicar",
      },
      {
        id: "multi-channel",
        icon: "MessageSquare",
        text: "Fotos e vídeos por WhatsApp/Telegram/link",
      },
      {
        id: "face-search",
        icon: "ScanFace",
        text: "Busca facial configurável",
        detail: "Ative ou desative conforme o evento",
      },
    ],
    metrics: [
      { value: "QR + Web", label: "entrada sem app" },
      { value: "Moderação IA", label: "aprovação inteligente" },
      { value: "Tempo real", label: "galeria, jogo e telão sincronizados" },
    ],
  },
  social: {
    eyebrow: "Plataforma premium de experiências ao vivo",
    headline: {
      lead: "Os convidados já estão tirando fotos.",
      accent: ["Agora elas viram experiência", "ao vivo no seu evento."],
    },
    subheadline: "Transforme fotos e vídeos dos convidados em galeria ao vivo, jogos interativos, telão dinâmico e memórias encontráveis por selfie.",
    flowSteps: [
      {
        number: 1,
        title: "Convidado envia",
        detail: "QR + web, sem app",
      },
      {
        number: 2,
        title: "IA organiza",
        detail: "modera e indexa automaticamente",
      },
      {
        number: 3,
        title: "Vira experiência",
        detail: "galeria, jogos, telão e busca facial",
      },
    ],
    ctas: {
      primary: {
        text: "Agendar demonstração",
        url: "",
      },
      secondary: {
        text: "Falar no WhatsApp",
        url: "",
      },
    },
    trustSignals: [
      {
        id: "no-app",
        icon: "Smartphone",
        text: "Sem app",
        detail: "Convidados entram por QR Code e web",
      },
      {
        id: "multi-channel",
        icon: "MessageSquare",
        text: "Fotos e vídeos por WhatsApp/Telegram/link",
      },
      {
        id: "face-search",
        icon: "ScanFace",
        text: "Busca facial configurável",
        detail: "Encontre suas fotos com uma selfie",
      },
      {
        id: "ai-moderation",
        icon: "ShieldCheck",
        text: "Moderação por IA",
        detail: "Segurança automática",
      },
    ],
    metrics: [
      { value: "QR + Web", label: "entrada sem app" },
      { value: "Tempo real", label: "galeria, jogo e telão sincronizados" },
      { value: "Busca facial", label: "encontre suas fotos com selfie" },
    ],
  },
  corporativo: {
    eyebrow: "Plataforma premium de experiências ao vivo",
    headline: {
      lead: "Engajamento em escala.",
      accent: ["Galeria, jogos e telão", "em tempo real."],
    },
    subheadline: "Arquitetura preparada para alto volume: receba por múltiplos canais, modere com IA e publique em galeria, jogos, telão e busca facial simultaneamente.",
    flowSteps: [
      {
        number: 1,
        title: "Múltiplos canais",
        detail: "QR, WhatsApp, Telegram, link",
      },
      {
        number: 2,
        title: "IA em escala",
        detail: "moderação e indexação automática",
      },
      {
        number: 3,
        title: "Publicação realtime",
        detail: "galeria, jogos, telão e busca",
      },
    ],
    ctas: {
      primary: {
        text: "Agendar demonstração",
        url: "",
      },
      secondary: {
        text: "Falar no WhatsApp",
        url: "",
      },
    },
    trustSignals: [
      {
        id: "no-app",
        icon: "Smartphone",
        text: "Sem app",
        detail: "Entrada por QR Code e web",
      },
      {
        id: "multi-channel",
        icon: "MessageSquare",
        text: "Fotos e vídeos por WhatsApp/Telegram/link",
      },
      {
        id: "ai-moderation",
        icon: "ShieldCheck",
        text: "Moderação por IA",
        detail: "Aprovação inteligente em escala",
      },
      {
        id: "face-search",
        icon: "ScanFace",
        text: "Busca facial configurável",
      },
    ],
    metrics: [
      { value: "Alto volume", label: "arquitetura preparada para picos" },
      { value: "Tempo real", label: "galeria, jogo e telão sincronizados" },
      { value: "IA ativa", label: "moderação e busca facial no mesmo fluxo" },
    ],
  },
};

export const ecosystemModules: VisualModule[] = [
  {
    id: "gallery",
    title: "Galeria Dinâmica",
    eyebrow: "Mosaico vivo",
    benefit: "As fotos entram, ganham destaque e reorganizam a experiência automaticamente.",
    detail: "Mosaico responsivo, destaque inteligente e sensação de evento vivo em vez de grade estática.",
    accent: "cyan",
  },
  {
    id: "games",
    title: "Jogos Interativos",
    eyebrow: "Engajamento real",
    benefit: "Transforme cada nova foto em puzzle, memória e ranking mobile-first.",
    detail: "Jogos feitos para festa: rápidos, acessíveis no celular e pensados para thumb zone.",
    accent: "warm",
  },
  {
    id: "wall",
    title: "Telão Dinâmico",
    eyebrow: "Palco premium",
    benefit: "Layouts fullscreen, split, polaroid e cinematic que performam ao vivo.",
    detail: "O telão vira um módulo da plataforma, não o único produto vendável.",
    accent: "blue",
  },
  {
    id: "moderation",
    title: "Moderação Inteligente",
    eyebrow: "Segurança operacional",
    benefit: "Nossa IA filtra conteúdo impróprio antes de publicar, reduzindo risco sem travar o fluxo.",
    detail: "Escolha entre sem moderação, manual ou IA assistida conforme o tipo de evento.",
    accent: "coral",
  },
  {
    id: "face",
    title: "Busca Facial com IA",
    eyebrow: "Memórias acionáveis",
    benefit: "Uma selfie encontra as melhores fotos do convidado em segundos.",
    detail: "Entrega premium no pós-evento, sem rolagem infinita e sem dor para o convidado.",
    accent: "green",
  },
];

export const galleryImages: EventImage[] = [
  eventImages.galleryFeatured,
  eventImages.gallerySecondary,
  eventImages.galleryThird,
  eventImages.galleryFourth,
  eventImages.galleryFifth,
  eventImages.gallerySixth,
  eventImages.gallerySeventh,
  eventImages.heroMobile,
];

export const testimonials = [
  {
    quote:
      "Substituiu o velho telão de fotos por uma experiência que realmente ocupou a pista. O puzzle com fotos recém-enviadas virou assunto da festa inteira.",
    author: "Rodrigo Almeida",
    role: "Produtor de formatura",
    eventType: "Formatura premium",
    volume: "4.200 fotos em uma noite",
    highlight: "Jogos com fotos do próprio evento",
    result: "Mais permanência dos convidados e percepção clara de tecnologia premium.",
  },
  {
    quote:
      "A IA segurou o que precisava segurar sem eu parar a operação. Para casamento, isso muda tudo: menos risco e mais tranquilidade para a equipe.",
    author: "Mariana Costa",
    role: "Cerimonialista",
    eventType: "Casamento",
    volume: "1.800 fotos e selfies",
    highlight: "Moderação inteligente antes do telão",
    result: "Operação segura, noivos encantados e pós-evento mais organizado.",
  },
  {
    quote:
      "No corporativo, a busca facial virou o diferencial que ninguém esperava. O time tirou selfie e recebeu as melhores memórias do evento em segundos.",
    author: "Thiago Pontes",
    role: "Coordenador de endomarketing",
    eventType: "Evento corporativo",
    volume: "2.600 fotos em ativação interna",
    highlight: "Busca facial e galeria baixável",
    result: "Maior engajamento interno e percepção de plataforma proprietária.",
  },
];

// Audience Section - 3 main profiles with scannable cards
export const audienceContent = {
  eyebrow: "Para quem é",
  title: "Cada perfil enxerga valor em módulos diferentes",
  subtitle: "Identifique rapidamente se a plataforma serve para você",
  profiles: [
    {
      id: "assessora" as const,
      name: "Assessoras e Cerimonialistas",
      icon: "shield-check",
      promise: "Controle total e segurança operacional para encantar clientes sem riscos",
      priorityModules: [
        "Moderação por IA configurável",
        "Galeria organizada e profissional",
        "Busca facial para encontrar fotos de convidados",
      ],
      objections: [
        {
          question: "E se aparecer conteúdo impróprio?",
          answer: "Moderação por IA filtra automaticamente antes de publicar",
        },
        {
          question: "Consigo personalizar com a identidade do evento?",
          answer: "Sim, layouts e branding totalmente personalizáveis",
        },
        {
          question: "Funciona para casamentos e debutantes?",
          answer: "Perfeitamente. É o uso mais comum da plataforma",
        },
      ],
      cta: {
        text: "Agendar demonstração",
        url: "https://wa.me/5511999999999?text=Olá,%20sou%20assessora%20e%20quero%20conhecer%20a%20plataforma",
      },
    },
    {
      id: "social" as const,
      name: "Noivas, Debutantes e Famílias",
      icon: "heart",
      promise: "Transforme fotos dos convidados em experiência inesquecível no seu evento",
      priorityModules: [
        "Galeria ao vivo com fotos e vídeos",
        "Jogos interativos com ranking",
        "Busca facial: encontre suas fotos por selfie",
      ],
      objections: [
        {
          question: "Precisa instalar app?",
          answer: "Não! Convidados enviam por WhatsApp, Telegram ou link",
        },
        {
          question: "Funciona com muitas pessoas enviando ao mesmo tempo?",
          answer: "Sim, suporta centenas de convidados simultaneamente",
        },
        {
          question: "Consigo baixar todas as fotos depois?",
          answer: "Sim, galeria completa organizada e disponível para download",
        },
      ],
      cta: {
        text: "Falar no WhatsApp",
        url: "https://wa.me/5511999999999?text=Olá,%20quero%20usar%20no%20meu%20evento",
      },
    },
    {
      id: "corporativo" as const,
      name: "Promotores, Produtores e Corporativos",
      icon: "building-2",
      promise: "Engajamento em escala com segurança de marca e operação robusta",
      priorityModules: [
        "Telão dinâmico com layouts profissionais",
        "Moderação e controle de marca",
        "Arquitetura preparada para alto volume",
      ],
      objections: [
        {
          question: "Aguenta pico de uso em ativações grandes?",
          answer: "Sim, arquitetura preparada para milhares de envios simultâneos",
        },
        {
          question: "Consigo garantir que só apareça conteúdo adequado?",
          answer: "Moderação por IA + aprovação manual quando necessário",
        },
        {
          question: "Posso usar com branding do cliente?",
          answer: "Sim, layouts totalmente personalizáveis com identidade visual",
        },
      ],
      cta: {
        text: "Agendar demonstração",
        url: "https://wa.me/5511999999999?text=Olá,%20sou%20produtor%20e%20quero%20conhecer%20a%20plataforma",
      },
    },
  ],
};

export const pricingPlans = [
  {
    name: "Evento Signature",
    audience: "Casamentos, aniversários, formaturas e eventos pontuais",
    price: "Sob medida por evento",
    badge: "Mais contratado",
    features: [
      "Galeria dinâmica, telão premium e QR Code de entrada",
      "Jogos com fotos do evento e ranking em tempo real",
      "Moderação manual ou com IA conforme o perfil do evento",
      "Entrega de memórias organizadas e baixáveis",
    ],
  },
  {
    name: "Operação Recorrente",
    audience: "Cerimonialistas, buffets, espaços e produtoras",
    price: "Assinatura consultiva",
    badge: "Escala mensal",
    features: [
      "Uso em múltiplos eventos com operação padronizada",
      "Suporte premium e configuração orientada ao parceiro",
      "Layouts personalizáveis e experiência de marca mais forte",
      "Base pronta para crescimento de volume e novas ativações",
    ],
  },
  {
    name: "Enterprise Experience",
    audience: "Eventos corporativos, ativações e operações de alto volume",
    price: "Projeto dedicado",
    badge: "Alta complexidade",
    features: [
      "Arquitetura preparada para alto volume e janelas críticas",
      "Fluxos de aprovação, indexação e publicação desacoplados",
      "Integrações, branding e operação com SLA comercial",
      "Estratégia de rollout para múltiplos pontos de captura",
    ],
  },
];

export const howItWorksContent: HowItWorksContent = {
  eyebrow: "Fluxo simples e poderoso",
  title: "Como funciona na prática",
  subtitle: "Do envio do convidado até a experiência ao vivo, tudo acontece em segundos",
  steps: [
    {
      id: "step-1",
      number: 1,
      title: "Convidados enviam",
      description: "Por QR Code, WhatsApp, Telegram ou link direto. Sem app, sem cadastro, sem fricção.",
      icon: "Upload",
      visual: "/images/how-it-works/step-1-upload.svg",
    },
    {
      id: "step-2",
      number: 2,
      title: "Plataforma recebe e organiza",
      description: "Cada foto e vídeo é processado, indexado e preparado para publicação em tempo real.",
      icon: "Server",
      visual: "/images/how-it-works/step-2-process.svg",
    },
    {
      id: "step-3",
      number: 3,
      title: "IA modera (opcional)",
      description: "Moderação inteligente filtra conteúdo impróprio antes de publicar, mantendo o evento seguro.",
      icon: "ShieldCheck",
      visual: "/images/how-it-works/step-3-moderate.svg",
    },
    {
      id: "step-4",
      number: 4,
      title: "Aparece em galeria, telão e jogos",
      description: "Conteúdo aprovado vira experiência ao vivo: galeria dinâmica, telão premium, jogos interativos e busca facial.",
      icon: "Sparkles",
      visual: "/images/how-it-works/step-4-publish.svg",
    },
  ],
};

export const captureChannelsContent: CaptureChannelsContent = {
  eyebrow: "Múltiplos canais de entrada",
  title: "Escolha como seus convidados vão participar",
  subtitle: "Cada evento tem seu perfil. Ofereça o canal ideal para o seu público.",
  channels: [
    {
      id: "whatsapp-number",
      name: "WhatsApp Número",
      description: "Convidados enviam fotos e vídeos direto para um número dedicado do evento",
      icon: "MessageCircle",
      visual: "/images/channels/whatsapp-number.png",
      features: [
        "Número exclusivo do evento",
        "Recebe fotos e vídeos",
        "Resposta automática por IA",
        "Ideal para eventos sociais",
      ],
      useCases: [
        "Casamentos e aniversários",
        "Formaturas",
        "Eventos familiares",
      ],
    },
    {
      id: "whatsapp-group",
      name: "Grupo WhatsApp",
      description: "Crie um grupo onde todos compartilham e veem as fotos em tempo real",
      icon: "Users",
      visual: "/images/channels/whatsapp-group.png",
      features: [
        "Grupo dedicado do evento",
        "Compartilhamento coletivo",
        "Moderação antes de publicar",
        "Engajamento social",
      ],
      useCases: [
        "Eventos corporativos",
        "Confraternizações",
        "Reuniões de equipe",
      ],
    },
    {
      id: "telegram",
      name: "Telegram",
      description: "Canal ou bot do Telegram para receber mídia de alta qualidade sem compressão",
      icon: "Send",
      visual: "/images/channels/telegram.png",
      features: [
        "Sem compressão de mídia",
        "Vídeos em alta qualidade",
        "Bot interativo",
        "Ideal para conteúdo premium",
      ],
      useCases: [
        "Eventos corporativos",
        "Produções audiovisuais",
        "Ativações de marca",
      ],
    },
    {
      id: "qr-link",
      name: "QR Code / Link",
      description: "Convidados escaneiam QR Code ou acessam link para enviar fotos pela web",
      icon: "QrCode",
      visual: "/images/channels/qr-code.png",
      features: [
        "Sem app necessário",
        "Acesso instantâneo",
        "Upload web otimizado",
        "Funciona em qualquer dispositivo",
      ],
      useCases: [
        "Todos os tipos de evento",
        "Entrada principal recomendada",
        "Máxima acessibilidade",
      ],
    },
    {
      id: "web-upload",
      name: "Upload Web",
      description: "Interface web completa para upload de múltiplas fotos e vídeos de uma vez",
      icon: "Globe",
      visual: "/images/channels/web-upload.png",
      features: [
        "Upload em lote",
        "Drag and drop",
        "Preview antes de enviar",
        "Ideal para fotógrafos",
      ],
      useCases: [
        "Fotógrafos profissionais",
        "Equipe de produção",
        "Upload pós-evento",
      ],
    },
  ],
};

// ============================================
// EXPERIENCE MODULES CONTENT
// ============================================

export const experienceModulesContent: ExperienceModulesContent = {
  eyebrow: "Ecossistema completo",
  title: "Uma foto. Quatro experiências ao vivo.",
  subtitle: "Cada envio alimenta galeria, telão, jogos e busca facial simultaneamente. Sem retrabalho, sem fricção.",
  modules: [
    {
      id: "gallery",
      name: "Galeria ao vivo",
      eyebrow: "Mosaico dinâmico",
      title: "As fotos entram e reorganizam a experiência automaticamente",
      benefit: "Mosaico responsivo com destaque inteligente para fotos recém-chegadas",
      features: [
        "Destaque automático para fotos novas",
        "Reorganização em tempo real",
        "Sensação de evento vivo, não grade estática",
      ],
      visual: {
        type: "image",
        src: eventImages.galleryFeatured.src,
        alt: "Galeria dinâmica com fotos de evento em destaque",
      },
      cta: {
        text: "Ver galeria em ação",
        action: "demo-gallery",
      },
    },
    {
      id: "wall",
      name: "Telão dinâmico",
      eyebrow: "Palco premium",
      title: "Layouts fullscreen, split, polaroid e cinematic que performam ao vivo",
      benefit: "O telão vira um módulo da plataforma, não o único produto vendável",
      features: [
        "4 layouts premium (fullscreen, split, polaroid, cinematic)",
        "Transições suaves e leitura imediata",
        "Atualização em tempo real sincronizada",
      ],
      visual: {
        type: "image",
        src: eventImages.wallHero.src,
        alt: "Telão dinâmico exibindo fotos em layout premium",
      },
      cta: {
        text: "Ver layouts do telão",
        action: "demo-wall",
      },
    },
    {
      id: "games",
      name: "Jogos interativos",
      eyebrow: "Engajamento real",
      title: "Transforme cada nova foto em puzzle, memória e ranking mobile-first",
      benefit: "Jogos feitos para festa: rápidos, acessíveis no celular e pensados para thumb zone",
      features: [
        "Puzzle e jogo da memória com fotos reais",
        "Ranking em tempo real",
        "Mobile-first e sem fricção",
      ],
      visual: {
        type: "demo",
        src: "/demo/games",
        alt: "Jogos interativos com fotos do evento",
      },
      cta: {
        text: "Experimentar jogos",
        action: "demo-games",
      },
    },
    {
      id: "face",
      name: "Busca facial",
      eyebrow: "Memórias acionáveis",
      title: "Uma selfie encontra as melhores fotos do convidado em segundos",
      benefit: "Entrega premium no pós-evento, sem rolagem infinita e sem dor para o convidado",
      features: [
        "Busca por selfie em segundos",
        "Resultados organizados por relevância",
        "Download e compartilhamento facilitados",
      ],
      visual: {
        type: "image",
        src: eventImages.faceSelfie.src,
        alt: "Busca facial encontrando fotos do convidado",
      },
      cta: {
        text: "Ver como funciona",
        action: "demo-face",
      },
    },
  ],
  priority: {
    // Assessora: Galeria + IA + Busca facial (IA is separate section, so: Galeria → Busca facial → Telão → Jogos)
    assessora: ["gallery", "face", "wall", "games"],
    // Social: Jogos + Busca facial + Galeria (Jogos → Busca facial → Galeria → Telão)
    social: ["games", "face", "gallery", "wall"],
    // Corporativo: Telão + Jogos + IA (IA is separate section, so: Telão → Jogos → Galeria → Busca facial)
    corporativo: ["wall", "games", "gallery", "face"],
  },
};

export const faqs = [
  {
    question: "O convidado precisa baixar aplicativo para participar?",
    answer:
      "Não. A entrada acontece por QR Code e experiência web. O convidado aponta a câmera, envia a foto e participa sem instalar nada.",
  },
  {
    question: "A plataforma aguenta evento com muito volume de fotos?",
    answer:
      "Sim. O fluxo foi pensado para picos reais: captura, moderação, indexação e publicação são tratados de forma desacoplada para a operação não travar.",
  },
  {
    question: "Como a moderação funciona na prática?",
    answer:
      "Você escolhe o modo mais adequado ao evento: sem moderação, moderação manual ou moderação por IA. No modo inteligente, a análise acontece antes da exibição.",
  },
  {
    question: "A busca facial é só uma promessa ou vira experiência real?",
    answer:
      "Ela vira experiência real. O convidado envia uma selfie e recebe os matches do evento em segundos, com uma interface pensada para download e compartilhamento.",
  },
];


// ============================================
// VALIDAÇÃO COM ZOD
// ============================================

import { z } from 'zod';

export const PersonaIdSchema = z.enum(['assessora', 'social', 'corporativo']);

export const TrustSignalSchema = z.object({
  id: z.string().min(1),
  icon: z.string().min(1),
  text: z.string().min(1),
  detail: z.string().optional(),
});

export const CTAConfigSchema = z.object({
  primary: z.object({
    text: z.string().min(1),
    url: z.string().url(),
    icon: z.string().optional(),
  }),
  secondary: z.object({
    text: z.string().min(1),
    url: z.string().url(),
    icon: z.string().optional(),
  }),
});

export const HeroContentSchema = z.object({
  eyebrow: z.string().min(1),
  headline: z.object({
    lead: z.string().min(1),
    accent: z.array(z.string()).min(1),
  }),
  subheadline: z.string().min(1),
  flowSteps: z.array(z.object({
    number: z.number().int().positive(),
    title: z.string().min(1),
    detail: z.string().min(1),
  })).min(3).max(4),
  ctas: CTAConfigSchema,
  trustSignals: z.array(TrustSignalSchema).min(3).max(5),
  metrics: z.array(z.object({
    value: z.string().min(1),
    label: z.string().min(1),
  })).min(2).max(4),
});

export const HowItWorksStepSchema = z.object({
  id: z.string().min(1),
  number: z.number().int().positive(),
  title: z.string().min(1),
  description: z.string().min(1),
  icon: z.string().min(1),
  visual: z.string().optional(),
});

export const HowItWorksContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  steps: z.array(HowItWorksStepSchema).min(3).max(4),
});

export const CaptureChannelSchema = z.object({
  id: z.string().min(1),
  name: z.string().min(1),
  description: z.string().min(1),
  icon: z.string().min(1),
  visual: z.string().min(1),
  features: z.array(z.string()).min(1),
  useCases: z.array(z.string()).min(1),
});

export const CaptureChannelsContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  channels: z.array(CaptureChannelSchema).min(1),
});

export const ExperienceModuleSchema = z.object({
  id: z.enum(['gallery', 'wall', 'games', 'face']),
  name: z.string().min(1),
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  benefit: z.string().min(1),
  features: z.array(z.string()).min(1),
  visual: z.object({
    type: z.enum(['image', 'video', 'demo']),
    src: z.string().min(1),
    alt: z.string().min(1),
    poster: z.string().optional(),
  }),
  cta: z.object({
    text: z.string().min(1),
    action: z.string().min(1),
  }).optional(),
});

export const ModulePrioritySchema = z.object({
  assessora: z.array(z.enum(['gallery', 'wall', 'games', 'face'])),
  social: z.array(z.enum(['gallery', 'wall', 'games', 'face'])),
  corporativo: z.array(z.enum(['gallery', 'wall', 'games', 'face'])),
});

export const ExperienceModulesContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  modules: z.array(ExperienceModuleSchema).min(1),
  priority: ModulePrioritySchema,
});

export const AISafetyFeatureSchema = z.object({
  id: z.string().min(1),
  title: z.string().min(1),
  description: z.string().min(1),
  icon: z.string().min(1),
  benefit: z.string().min(1),
});

export const AISafetyContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  features: z.array(AISafetyFeatureSchema).min(1),
  demo: z.object({
    type: z.enum(['visual', 'interactive']),
    src: z.string().min(1),
    alt: z.string().min(1),
  }),
  trustNote: z.string().min(1),
});

export const FaceRecognitionContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  emotionalHook: z.string().min(1),
  flowSteps: z.array(z.object({
    number: z.number().int().positive(),
    title: z.string().min(1),
    visual: z.string().min(1),
  })).min(2),
  benefits: z.array(z.string()).min(1),
  demo: z.object({
    selfie: z.string().min(1),
    matches: z.array(z.object({
      src: z.string().min(1),
      confidence: z.number().min(0).max(100),
    })).min(1),
  }),
});

export const AudienceProfileSchema = z.object({
  id: PersonaIdSchema,
  name: z.string().min(1),
  icon: z.string().min(1),
  promise: z.string().min(1),
  priorityModules: z.array(z.string()).min(1),
  objections: z.array(z.object({
    question: z.string().min(1),
    answer: z.string().min(1),
  })),
  cta: z.object({
    text: z.string().min(1),
    url: z.string().url(),
  }),
});

export const AudienceContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  profiles: z.array(AudienceProfileSchema).min(3).max(3),
});

export const TestimonialSchema = z.object({
  id: z.string().min(1),
  context: z.enum(['casamento', 'assessoria', 'corporativo']),
  quote: z.string().min(1),
  author: z.object({
    name: z.string().min(1),
    role: z.string().min(1),
    photo: z.string().optional(),
  }),
  event: z.object({
    type: z.string().min(1),
    volume: z.string().min(1),
    photo: z.string().min(1),
  }),
  highlight: z.string().min(1),
  result: z.string().min(1),
});

export const TestimonialsContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  testimonials: z.array(TestimonialSchema).min(3),
  contextGroups: z.object({
    casamento: z.array(TestimonialSchema),
    assessoria: z.array(TestimonialSchema),
    corporativo: z.array(TestimonialSchema),
  }),
});

export const PricingPlanSchema = z.object({
  id: z.string().min(1),
  name: z.string().min(1),
  audience: z.string().min(1),
  price: z.string().min(1),
  badge: z.string().optional(),
  highlight: z.boolean(),
  features: z.array(z.string()).min(1),
  cta: z.object({
    text: z.string().min(1),
    url: z.string().url(),
  }),
});

export const PricingContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  plans: z.array(PricingPlanSchema).min(3).max(3),
  note: z.string().min(1),
});

export const FAQItemSchema = z.object({
  id: z.string().min(1),
  question: z.string().min(1),
  answer: z.string().min(1),
  category: z.enum(['comercial', 'tecnico', 'operacional']).optional(),
});

export const FAQContentSchema = z.object({
  eyebrow: z.string().min(1),
  title: z.string().min(1),
  subtitle: z.string().min(1),
  items: z.array(FAQItemSchema).min(7).max(10),
});

export const FinalCTAContentSchema = z.object({
  title: z.string().min(1),
  subtitle: z.string().min(1),
  ctas: CTAConfigSchema,
  trustSignals: z.array(TrustSignalSchema).min(1),
});

/**
 * Valida dados da landing page em runtime usando Zod
 * @param schema - Schema Zod para validação
 * @param data - Dados a serem validados
 * @returns Dados validados e tipados
 * @throws Error se validação falhar
 */
export function validateLandingData<T>(schema: z.ZodSchema<T>, data: unknown): T {
  try {
    return schema.parse(data);
  } catch (error) {
    if (error instanceof z.ZodError) {
      console.error('Landing data validation failed:', error.issues);
      throw new Error(`Invalid landing data: ${error.issues.map((e: z.ZodIssue) => `${e.path.join('.')}: ${e.message}`).join(', ')}`);
    }
    throw error;
  }
}

/**
 * Valida dados da landing page em runtime de forma segura
 * @param schema - Schema Zod para validação
 * @param data - Dados a serem validados
 * @returns Objeto com sucesso e dados ou erro
 */
export function safeLandingDataValidation<T>(
  schema: z.ZodSchema<T>,
  data: unknown
): { success: true; data: T } | { success: false; error: z.ZodError } {
  const result = schema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data };
  }
  return { success: false, error: result.error };
}
