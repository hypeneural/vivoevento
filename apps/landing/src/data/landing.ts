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

// ============================================
// PERSONA-SPECIFIC CTA CONFIGURATION
// ============================================

export type PersonaCTAConfig = {
  assessora: CTAConfig;
  social: CTAConfig;
  corporativo: CTAConfig;
};

export type WhatsAppMessageTemplate = {
  assessora: string;
  social: string;
  corporativo: string;
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
    eyebrow: "Experiências ao vivo",
    headline: {
      lead: "Controle total.",
      accent: ["Moderação inteligente,", "operação segura."],
    },
    subheadline: "Receba por QR, aprove com IA e publique em galeria, jogos, telão e busca facial.",
    flowSteps: [
      {
        number: 1,
        title: "Convidado envia",
        detail: "QR + web, sem app",
      },
      {
        number: 2,
        title: "IA decide",
        detail: "aprova e indexa",
      },
      {
        number: 3,
        title: "Publica experiência",
        detail: "galeria, jogos, telão",
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
    eyebrow: "Experiências ao vivo",
    headline: {
      lead: "Fotos dos convidados.",
      accent: ["Viram experiência", "ao vivo no evento."],
    },
    subheadline: "Transforme fotos em galeria ao vivo, jogos, telão e busca por selfie.",
    flowSteps: [
      {
        number: 1,
        title: "Convidado envia",
        detail: "QR + web, sem app",
      },
      {
        number: 2,
        title: "IA organiza",
        detail: "modera e indexa",
      },
      {
        number: 3,
        title: "Vira experiência",
        detail: "galeria, jogos, telão",
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
    eyebrow: "Experiências ao vivo",
    headline: {
      lead: "Engajamento em escala.",
      accent: ["Galeria, jogos e telão", "em tempo real."],
    },
    subheadline: "Alto volume: múltiplos canais, moderação IA e publicação simultânea.",
    flowSteps: [
      {
        number: 1,
        title: "Múltiplos canais",
        detail: "QR, WhatsApp, Telegram",
      },
      {
        number: 2,
        title: "IA em escala",
        detail: "moderação automática",
      },
      {
        number: 3,
        title: "Publicação realtime",
        detail: "galeria, jogos, telão",
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

// ============================================
// PERSONA-SPECIFIC CTA CONFIGURATIONS
// ============================================

/**
 * WhatsApp phone number for CTAs
 * Format: country code + area code + number (no spaces or special chars)
 */
export const WHATSAPP_PHONE = '5511999999999';

/**
 * Pre-filled WhatsApp messages by persona
 * Requirement 37: Persona-specific conversion model
 */
export const whatsAppMessages: WhatsAppMessageTemplate = {
  assessora: 'Olá! Sou assessora/cerimonialista e quero conhecer a plataforma Evento Vivo. Gostaria de agendar uma demonstração para entender como funciona a moderação por IA e a operação segura.',
  social: 'Olá! Quero usar a plataforma Evento Vivo no meu evento. Gostaria de saber mais sobre a galeria ao vivo, jogos interativos e busca facial.',
  corporativo: 'Olá! Sou produtor/promotor e quero conhecer a plataforma Evento Vivo. Gostaria de agendar uma demonstração para entender a arquitetura para alto volume e as possibilidades de branding.',
};

/**
 * Persona-specific CTA configurations
 * Requirement 37: Adaptive conversion model
 * 
 * - Assessora: Prioriza "Agendar demonstração" (formulário qualificado)
 * - Social: Prioriza "Falar no WhatsApp" (baixa fricção)
 * - Corporativo: Prioriza "Agendar demonstração" (formulário multi-step)
 */
export const personaCTAs: PersonaCTAConfig = {
  assessora: {
    primary: {
      text: 'Agendar demonstração',
      url: 'https://eventovivo.com/agendar?tipo=assessora',
      icon: 'Calendar',
    },
    secondary: {
      text: 'Falar no WhatsApp',
      url: `https://wa.me/${WHATSAPP_PHONE}?text=${encodeURIComponent(whatsAppMessages.assessora)}`,
      icon: 'MessageCircle',
    },
  },
  social: {
    primary: {
      text: 'Falar no WhatsApp',
      url: `https://wa.me/${WHATSAPP_PHONE}?text=${encodeURIComponent(whatsAppMessages.social)}`,
      icon: 'MessageCircle',
    },
    secondary: {
      text: 'Agendar demonstração',
      url: 'https://eventovivo.com/agendar?tipo=social',
      icon: 'Calendar',
    },
  },
  corporativo: {
    primary: {
      text: 'Agendar demonstração',
      url: 'https://eventovivo.com/agendar?tipo=corporativo',
      icon: 'Calendar',
    },
    secondary: {
      text: 'Falar no WhatsApp',
      url: `https://wa.me/${WHATSAPP_PHONE}?text=${encodeURIComponent(whatsAppMessages.corporativo)}`,
      icon: 'MessageCircle',
    },
  },
};

/**
 * Default CTA configuration (when no persona is selected)
 * Balanced approach for generic visitors
 */
export const defaultCTAs: CTAConfig = {
  primary: {
    text: 'Agendar demonstração',
    url: 'https://eventovivo.com/agendar',
    icon: 'Calendar',
  },
  secondary: {
    text: 'Falar no WhatsApp',
    url: `https://wa.me/${WHATSAPP_PHONE}?text=${encodeURIComponent('Olá! Quero conhecer a plataforma Evento Vivo.')}`,
    icon: 'MessageCircle',
  },
};

export const ecosystemModules: VisualModule[] = [
  {
    id: "gallery",
    title: "Galeria Dinâmica",
    eyebrow: "Mosaico vivo",
    benefit: "Fotos entram e reorganizam automaticamente.",
    detail: "Destaque inteligente para conteúdo novo.",
    accent: "cyan",
  },
  {
    id: "games",
    title: "Jogos Interativos",
    eyebrow: "Engajamento real",
    benefit: "Puzzle e memória com fotos do evento.",
    detail: "Rápidos, mobile-first e divertidos.",
    accent: "warm",
  },
  {
    id: "wall",
    title: "Telão Dinâmico",
    eyebrow: "Palco premium",
    benefit: "Layouts profissionais ao vivo.",
    detail: "Fullscreen, split, polaroid e cinematic.",
    accent: "blue",
  },
  {
    id: "moderation",
    title: "Moderação Inteligente",
    eyebrow: "Segurança operacional",
    benefit: "IA filtra conteúdo antes de publicar.",
    detail: "Sem moderação, manual ou IA assistida.",
    accent: "coral",
  },
  {
    id: "face",
    title: "Busca Facial",
    eyebrow: "Memórias acionáveis",
    benefit: "Selfie encontra suas fotos em segundos.",
    detail: "Sem rolagem infinita no pós-evento.",
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

// ============================================
// TESTIMONIALS CONTENT - Organized by context
// ============================================

export const testimonialsContent: TestimonialsContent = {
  eyebrow: "Prova social",
  title: "Não é um telão bonito. É outra categoria.",
  subtitle: "Casos reais por tipo de evento",
  testimonials: [
    {
      id: "testimonial-1",
      context: "casamento",
      quote: "A IA segurou o que precisava sem parar a operação. Menos risco, mais tranquilidade.",
      author: {
        name: "Mariana Costa",
        role: "Cerimonialista",
      },
      event: {
        type: "Casamento",
        volume: "1.800 fotos",
        photo: eventImages.gallerySecondary.src,
      },
      highlight: "Moderação inteligente",
      result: "Operação segura e noivos encantados.",
    },
    {
      id: "testimonial-2",
      context: "casamento",
      quote: "Convidados adoraram ver fotos ao vivo. Busca facial foi o diferencial inesperado.",
      author: {
        name: "Ana Paula Ferreira",
        role: "Noiva",
      },
      event: {
        type: "Casamento premium",
        volume: "2.100 fotos",
        photo: eventImages.wallHero.src,
      },
      highlight: "Galeria e busca facial",
      result: "Experiência memorável.",
    },
    {
      id: "testimonial-3",
      context: "assessoria",
      quote: "Controle total no telão. Moderação IA deu segurança sem surpresas.",
      author: {
        name: "Juliana Mendes",
        role: "Assessora",
      },
      event: {
        type: "Debutante",
        volume: "1.500 fotos",
        photo: eventImages.galleryFeatured.src,
      },
      highlight: "Moderação configurável",
      result: "Cliente encantado, operação segura.",
    },
    {
      id: "testimonial-4",
      context: "corporativo",
      quote: "Substituiu o telão por experiência real. Puzzle virou assunto da festa.",
      author: {
        name: "Rodrigo Almeida",
        role: "Produtor",
      },
      event: {
        type: "Formatura",
        volume: "4.200 fotos",
        photo: eventImages.galleryThird.src,
      },
      highlight: "Jogos com fotos do evento",
      result: "Mais permanência e percepção premium.",
    },
    {
      id: "testimonial-5",
      context: "corporativo",
      quote: "Busca facial virou diferencial inesperado. Selfie encontrou memórias em segundos.",
      author: {
        name: "Thiago Pontes",
        role: "Coordenador",
      },
      event: {
        type: "Evento corporativo",
        volume: "2.600 fotos",
        photo: eventImages.galleryFourth.src,
      },
      highlight: "Busca facial",
      result: "Maior engajamento e percepção premium.",
    },
  ],
  contextGroups: {
    casamento: [],
    assessoria: [],
    corporativo: [],
  },
};

// Organize testimonials into context groups
testimonialsContent.contextGroups.casamento = testimonialsContent.testimonials.filter(t => t.context === 'casamento');
testimonialsContent.contextGroups.assessoria = testimonialsContent.testimonials.filter(t => t.context === 'assessoria');
testimonialsContent.contextGroups.corporativo = testimonialsContent.testimonials.filter(t => t.context === 'corporativo');

// Legacy export for backward compatibility
export const testimonials = testimonialsContent.testimonials.map(t => ({
  quote: t.quote,
  author: t.author.name,
  role: t.author.role,
  eventType: t.event.type,
  volume: t.event.volume,
  highlight: t.highlight,
  result: t.result,
}));

// ============================================
// FALLBACK IMAGES FOR MISSING EVENT PHOTOS
// ============================================

/**
 * Fallback images para fotos de eventos ausentes
 * Usadas quando event.photo não está disponível
 */
export const FALLBACK_EVENT_IMAGES = {
  casamento: "https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=80",
  assessoria: "https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=900&q=80",
  corporativo: "https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=900&q=80",
  generic: "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=900&q=80",
};

/**
 * Fallback images para todas imagens críticas da landing page
 * Requirement 29: Ter fallback visual para imagens ausentes
 * 
 * Usadas quando imagens principais falharem ao carregar
 */
export const FALLBACK_IMAGES = {
  // Hero section
  hero: "https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1400&q=80",
  heroMobile: "https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=720&q=80",
  
  // Gallery module
  gallery: "https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=1200&q=80",
  galleryFeatured: "https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=900&q=80",
  
  // Wall module
  wall: "https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=1400&q=80",
  wallHero: "https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=1400&q=80",
  
  // Face recognition module
  faceSelfie: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=720&q=80",
  faceMatch: "https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=720&q=80",
  
  // Games module
  games: "https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80",
  
  // AI Safety module
  aiModeration: "https://images.unsplash.com/photo-1677442136019-21780ecad995?auto=format&fit=crop&w=1200&q=80",
  
  // Capture channels
  channels: "https://images.unsplash.com/photo-1611162617474-5b21e879e113?auto=format&fit=crop&w=1200&q=80",
  
  // Generic fallback for any missing image
  generic: "https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=900&q=80",
};

// ============================================
// FALLBACK TESTIMONIALS - SAFE FOR PRODUCTION
// ============================================

/**
 * ATENÇÃO: Depoimentos fallback com cara de real em produção é arriscado.
 * 
 * Em PRODUÇÃO: Usar placeholder visual de layout, caso temporariamente oculto,
 * ou bloco "exemplo ilustrativo" bem marcado.
 * 
 * Em DESENVOLVIMENTO: Fallbacks realistas APENAS para testes visuais.
 * 
 * Estratégia:
 * - Produção: Depoimentos claramente marcados como exemplos ilustrativos
 * - Desenvolvimento: Depoimentos realistas para testar layout e fluxo
 */

const isDevelopment = import.meta.env.MODE === 'development';

/**
 * Fallback testimonials para desenvolvimento (realistas)
 */
const DEVELOPMENT_FALLBACK_TESTIMONIALS: Testimonial[] = [
  {
    id: 'fallback-dev-1',
    context: 'casamento',
    quote: 'A plataforma transformou a experiência do nosso casamento. Os convidados adoraram ver as fotos ao vivo no telão.',
    author: {
      name: 'Cliente Satisfeito',
      role: 'Casamento Premium',
    },
    event: {
      type: 'Casamento',
      volume: '1.500+ fotos',
      photo: FALLBACK_EVENT_IMAGES.casamento,
    },
    highlight: 'Galeria ao vivo',
    result: 'Experiência memorável para todos os convidados',
  },
  {
    id: 'fallback-dev-2',
    context: 'assessoria',
    quote: 'Como assessora, preciso de controle total. A moderação por IA me deu segurança para oferecer a experiência sem preocupações.',
    author: {
      name: 'Profissional de Eventos',
      role: 'Assessora',
    },
    event: {
      type: 'Debutante',
      volume: '1.200 fotos',
      photo: FALLBACK_EVENT_IMAGES.assessoria,
    },
    highlight: 'Moderação configurável',
    result: 'Cliente encantado e operação sem riscos',
  },
  {
    id: 'fallback-dev-3',
    context: 'corporativo',
    quote: 'No evento corporativo, a busca facial virou o diferencial. O time tirou selfie e recebeu as melhores memórias em segundos.',
    author: {
      name: 'Gestor de Eventos',
      role: 'Coordenador',
    },
    event: {
      type: 'Evento Corporativo',
      volume: '2.000+ fotos',
      photo: FALLBACK_EVENT_IMAGES.corporativo,
    },
    highlight: 'Busca facial',
    result: 'Maior engajamento e percepção de tecnologia premium',
  },
];

/**
 * Fallback testimonials para produção (claramente marcados como exemplos)
 */
const PRODUCTION_FALLBACK_TESTIMONIALS: Testimonial[] = [
  {
    id: 'fallback-prod-1',
    context: 'casamento',
    quote: '[Exemplo ilustrativo] Depoimento de cliente sobre experiência em casamento com galeria ao vivo e telão dinâmico.',
    author: {
      name: '[Exemplo]',
      role: 'Cliente de Casamento',
    },
    event: {
      type: '[Exemplo] Casamento',
      volume: 'Volume ilustrativo',
      photo: FALLBACK_EVENT_IMAGES.casamento,
    },
    highlight: '[Exemplo] Funcionalidade destacada',
    result: '[Exemplo] Resultado percebido pelo cliente',
  },
  {
    id: 'fallback-prod-2',
    context: 'assessoria',
    quote: '[Exemplo ilustrativo] Depoimento de assessora sobre controle e moderação inteligente.',
    author: {
      name: '[Exemplo]',
      role: 'Assessora de Eventos',
    },
    event: {
      type: '[Exemplo] Debutante',
      volume: 'Volume ilustrativo',
      photo: FALLBACK_EVENT_IMAGES.assessoria,
    },
    highlight: '[Exemplo] Funcionalidade destacada',
    result: '[Exemplo] Resultado percebido pelo cliente',
  },
  {
    id: 'fallback-prod-3',
    context: 'corporativo',
    quote: '[Exemplo ilustrativo] Depoimento de produtor sobre engajamento em escala e busca facial.',
    author: {
      name: '[Exemplo]',
      role: 'Produtor de Eventos',
    },
    event: {
      type: '[Exemplo] Evento Corporativo',
      volume: 'Volume ilustrativo',
      photo: FALLBACK_EVENT_IMAGES.corporativo,
    },
    highlight: '[Exemplo] Funcionalidade destacada',
    result: '[Exemplo] Resultado percebido pelo cliente',
  },
];

/**
 * Fallback testimonials exportados (mínimo 3 depoimentos)
 * Usa versão de desenvolvimento ou produção conforme ambiente
 */
export const FALLBACK_TESTIMONIALS: Testimonial[] = isDevelopment
  ? DEVELOPMENT_FALLBACK_TESTIMONIALS
  : PRODUCTION_FALLBACK_TESTIMONIALS;

/**
 * Fallback testimonials content completo
 * Usado quando testimonialsContent principal não está disponível
 */
export const FALLBACK_TESTIMONIALS_CONTENT: TestimonialsContent = {
  eyebrow: isDevelopment ? "Prova social" : "[Exemplos ilustrativos]",
  title: isDevelopment 
    ? "Casos de sucesso da plataforma" 
    : "[Exemplos] Depoimentos de clientes",
  subtitle: isDevelopment
    ? "Casos reais organizados por tipo de evento"
    : "Exemplos ilustrativos - aguardando depoimentos reais aprovados",
  testimonials: FALLBACK_TESTIMONIALS,
  contextGroups: {
    casamento: FALLBACK_TESTIMONIALS.filter(t => t.context === 'casamento'),
    assessoria: FALLBACK_TESTIMONIALS.filter(t => t.context === 'assessoria'),
    corporativo: FALLBACK_TESTIMONIALS.filter(t => t.context === 'corporativo'),
  },
};

// Audience Section - 3 main profiles with scannable cards
export const audienceContent = {
  eyebrow: "Para quem é",
  title: "Cada perfil enxerga valor diferente",
  subtitle: "Identifique se serve para você",
  profiles: [
    {
      id: "assessora" as const,
      name: "Assessoras e Cerimonialistas",
      icon: "shield-check",
      promise: "Controle total e segurança para encantar sem riscos",
      priorityModules: [
        "Moderação IA configurável",
        "Galeria profissional",
        "Busca facial",
      ],
      objections: [
        {
          question: "E se aparecer conteúdo impróprio?",
          answer: "IA filtra automaticamente antes de publicar",
        },
        {
          question: "Consigo personalizar?",
          answer: "Sim, layouts e branding personalizáveis",
        },
        {
          question: "Funciona para casamentos?",
          answer: "Perfeitamente. É o uso mais comum",
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
      promise: "Transforme fotos em experiência inesquecível",
      priorityModules: [
        "Galeria ao vivo",
        "Jogos com ranking",
        "Busca por selfie",
      ],
      objections: [
        {
          question: "Precisa instalar app?",
          answer: "Não! WhatsApp, Telegram ou link",
        },
        {
          question: "Funciona com muitas pessoas?",
          answer: "Sim, centenas simultaneamente",
        },
        {
          question: "Consigo baixar depois?",
          answer: "Sim, galeria completa para download",
        },
      ],
      cta: {
        text: "Falar no WhatsApp",
        url: "https://wa.me/5511999999999?text=Olá,%20quero%20usar%20no%20meu%20evento",
      },
    },
    {
      id: "corporativo" as const,
      name: "Promotores e Corporativos",
      icon: "building-2",
      promise: "Engajamento em escala com segurança de marca",
      priorityModules: [
        "Telão profissional",
        "Moderação e controle",
        "Alto volume",
      ],
      objections: [
        {
          question: "Aguenta pico de uso?",
          answer: "Sim, milhares de envios simultâneos",
        },
        {
          question: "Controle de conteúdo?",
          answer: "IA + aprovação manual quando necessário",
        },
        {
          question: "Posso usar meu branding?",
          answer: "Sim, layouts personalizáveis",
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
    audience: "Casamentos, aniversários e formaturas",
    price: "Sob medida",
    badge: "Mais contratado",
    features: [
      "Galeria, telão e QR Code",
      "Jogos com ranking realtime",
      "Moderação manual ou IA",
      "Memórias organizadas",
    ],
  },
  {
    name: "Operação Recorrente",
    audience: "Cerimonialistas e produtoras",
    price: "Assinatura",
    badge: "Escala mensal",
    features: [
      "Múltiplos eventos",
      "Suporte premium",
      "Layouts personalizáveis",
      "Base para crescimento",
    ],
  },
  {
    name: "Enterprise",
    audience: "Corporativos e alto volume",
    price: "Projeto dedicado",
    badge: "Alta complexidade",
    features: [
      "Alto volume e SLA",
      "Fluxos desacoplados",
      "Integrações e branding",
      "Múltiplos pontos",
    ],
  },
];

export const howItWorksContent: HowItWorksContent = {
  eyebrow: "Fluxo simples",
  title: "Como funciona",
  subtitle: "Do envio até a experiência ao vivo em segundos",
  steps: [
    {
      id: "step-1",
      number: 1,
      title: "Convidados enviam",
      description: "QR Code, WhatsApp ou link. Sem app, sem cadastro.",
      icon: "Upload",
      visual: "/images/how-it-works/step-1-upload.svg",
    },
    {
      id: "step-2",
      number: 2,
      title: "Plataforma organiza",
      description: "Processa, indexa e prepara em tempo real.",
      icon: "Server",
      visual: "/images/how-it-works/step-2-process.svg",
    },
    {
      id: "step-3",
      number: 3,
      title: "IA modera (opcional)",
      description: "Filtra conteúdo impróprio antes de publicar.",
      icon: "ShieldCheck",
      visual: "/images/how-it-works/step-3-moderate.svg",
    },
    {
      id: "step-4",
      number: 4,
      title: "Aparece ao vivo",
      description: "Galeria, telão, jogos e busca facial.",
      icon: "Sparkles",
      visual: "/images/how-it-works/step-4-publish.svg",
    },
  ],
};

export const captureChannelsContent: CaptureChannelsContent = {
  eyebrow: "Múltiplos canais",
  title: "Escolha como convidados participam",
  subtitle: "Cada evento tem seu perfil. Ofereça o canal ideal.",
  channels: [
    {
      id: "whatsapp-number",
      name: "WhatsApp Número",
      description: "Envio direto para número dedicado do evento",
      icon: "MessageCircle",
      visual: "/images/channels/whatsapp-number.png",
      features: [
        "Número exclusivo",
        "Fotos e vídeos",
        "Resposta automática IA",
      ],
      useCases: [
        "Casamentos",
        "Formaturas",
      ],
    },
    {
      id: "whatsapp-group",
      name: "Grupo WhatsApp",
      description: "Grupo onde todos compartilham em tempo real",
      icon: "Users",
      visual: "/images/channels/whatsapp-group.png",
      features: [
        "Grupo dedicado",
        "Compartilhamento coletivo",
        "Moderação antes de publicar",
      ],
      useCases: [
        "Eventos corporativos",
        "Confraternizações",
      ],
    },
    {
      id: "telegram",
      name: "Telegram",
      description: "Canal ou bot sem compressão de mídia",
      icon: "Send",
      visual: "/images/channels/telegram.png",
      features: [
        "Sem compressão",
        "Vídeos alta qualidade",
        "Bot interativo",
      ],
      useCases: [
        "Corporativos",
        "Ativações de marca",
      ],
    },
    {
      id: "qr-link",
      name: "QR Code / Link",
      description: "Escaneie QR ou acesse link para enviar pela web",
      icon: "QrCode",
      visual: "/images/channels/qr-code.png",
      features: [
        "Sem app",
        "Acesso instantâneo",
        "Qualquer dispositivo",
      ],
      useCases: [
        "Todos os eventos",
        "Entrada principal",
      ],
    },
    {
      id: "web-upload",
      name: "Upload Web",
      description: "Interface web para upload em lote",
      icon: "Globe",
      visual: "/images/channels/web-upload.png",
      features: [
        "Upload em lote",
        "Drag and drop",
        "Preview antes de enviar",
      ],
      useCases: [
        "Fotógrafos",
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
  title: "Uma foto. Quatro experiências.",
  subtitle: "Cada envio alimenta galeria, telão, jogos e busca simultaneamente.",
  modules: [
    {
      id: "gallery",
      name: "Galeria ao vivo",
      eyebrow: "Mosaico dinâmico",
      title: "Fotos entram e reorganizam automaticamente",
      benefit: "Destaque inteligente para conteúdo novo",
      features: [
        "Destaque automático",
        "Reorganização realtime",
        "Sensação de evento vivo",
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
      title: "Layouts profissionais ao vivo",
      benefit: "Fullscreen, split, polaroid e cinematic",
      features: [
        "4 layouts premium",
        "Transições suaves",
        "Atualização sincronizada",
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
      title: "Puzzle e memória com fotos do evento",
      benefit: "Rápidos, mobile-first e divertidos",
      features: [
        "Puzzle e memória",
        "Ranking realtime",
        "Mobile-first",
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
      title: "Selfie encontra suas fotos em segundos",
      benefit: "Sem rolagem infinita no pós-evento",
      features: [
        "Busca por selfie",
        "Resultados por relevância",
        "Download facilitado",
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

export const faqs: FAQItem[] = [
  {
    id: "faq-app",
    question: "Precisa de app?",
    answer: "Não. QR Code, WhatsApp ou link web.",
    category: "comercial",
  },
  {
    id: "faq-video",
    question: "Aceita vídeos?",
    answer: "Sim. Fotos e vídeos no mesmo fluxo.",
    category: "comercial",
  },
  {
    id: "faq-volume",
    question: "Funciona com muito volume?",
    answer: "Sim. Centenas de envios simultâneos.",
    category: "tecnico",
  },
  {
    id: "faq-moderation",
    question: "Como funciona a moderação?",
    answer: "Você escolhe: sem, manual ou IA.",
    category: "operacional",
  },
  {
    id: "faq-face-search",
    question: "Busca facial é configurável?",
    answer: "Sim. Você decide se ativa por evento.",
    category: "operacional",
  },
  {
    id: "faq-event-types",
    question: "Serve para casamento e corporativo?",
    answer: "Sim. Casamentos, formaturas e eventos corporativos.",
    category: "comercial",
  },
  {
    id: "faq-branding",
    question: "Posso usar meu branding?",
    answer: "Sim. Layouts e cores personalizáveis.",
    category: "comercial",
  },
  {
    id: "faq-download",
    question: "Convidados baixam as fotos?",
    answer: "Sim. Galeria completa para download.",
    category: "operacional",
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
