export type NavItem = {
  id: string;
  label: string;
};

export type VisualModule = {
  id: string;
  title: string;
  eyebrow: string;
  benefit: string;
  detail: string;
  accent: "cyan" | "warm" | "blue" | "green" | "coral";
};

export type EventImage = {
  src: string;
  alt: string;
};

export const navItems: NavItem[] = [
  { id: "produto", label: "Produto" },
  { id: "como-funciona", label: "Como funciona" },
  { id: "jogos", label: "Jogos" },
  { id: "ia", label: "IA" },
  { id: "depoimentos", label: "Depoimentos" },
  { id: "precos", label: "Planos" },
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

export const audienceProfiles = [
  {
    id: "produtor",
    label: "Produtor de eventos",
    pain: "Precisa vender algo memorável sem inflar a operação da equipe.",
    value: "Entrega uma plataforma premium que transforma fotos em atração, sem depender de workflows improvisados.",
    module: "Mais relevante: Telão + Jogos + operação robusta",
  },
  {
    id: "cerimonialista",
    label: "Cerimonialista",
    pain: "Quer encantar noivos e convidados sem correr risco com conteúdo indevido.",
    value: "Ganha moderação com IA, galeria organizada e um pós-evento muito mais memorável.",
    module: "Mais relevante: IA + Galeria + busca facial",
  },
  {
    id: "buffet",
    label: "Buffet / Espaço",
    pain: "Precisa elevar a percepção do espaço com uma experiência que pareça de outro patamar.",
    value: "Agrega uma tecnologia visível, elegante e vendável como diferencial premium do local.",
    module: "Mais relevante: Telão dinâmico + layouts premium",
  },
  {
    id: "cliente",
    label: "Cliente final",
    pain: "Quer que as fotos do evento virem algo divertido e inesquecível, não uma pasta esquecida.",
    value: "Recebe galeria viva, jogos, telão e memórias encontráveis por selfie.",
    module: "Mais relevante: Jogos + busca facial + galeria baixável",
  },
  {
    id: "corporativo",
    label: "Evento corporativo",
    pain: "Precisa de engajamento, segurança de marca e operação confiável sob pico de uso.",
    value: "Consegue moderação, realtime, layouts profissionais e relatórios de uma ativação mais rica.",
    module: "Mais relevante: IA + realtime + arquitetura robusta",
  },
];

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
