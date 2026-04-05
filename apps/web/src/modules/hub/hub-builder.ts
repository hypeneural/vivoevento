import type {
  ApiEvent,
  ApiHubBlockKey,
  ApiHubBuilderConfig,
  ApiHubButton,
  ApiHubButtonStyle,
  ApiHubLayoutKey,
  ApiHubSocialProviderKey,
  ApiHubThemeKey,
  ApiHubThemeTokens,
  HubButtonIconKey,
} from '@/lib/api-types';

type HubBrandingInput = Pick<ApiEvent, 'primary_color' | 'secondary_color' | 'title' | 'starts_at' | 'location_name' | 'description'>;

export type HubPresetActionKey = Exclude<ApiHubButton['preset_key'], null>;

export interface HubLayoutPresetDefinition {
  key: ApiHubLayoutKey;
  label: string;
  description: string;
}

export interface HubThemePresetDefinition {
  key: ApiHubThemeKey;
  label: string;
  description: string;
  layout_key: ApiHubLayoutKey;
  summary: string;
  mood: string;
  recommended_for: string[];
  swatches: [string, string, string];
}

export interface HubThemePresetApplication {
  builder_config: ApiHubBuilderConfig;
  button_style: ApiHubButtonStyle;
  preset_button_order: HubPresetActionKey[];
  preset_button_overrides: Record<HubPresetActionKey, HubThemeButtonOverride>;
  content_copy: {
    headline: string;
    subheadline: string;
    welcome_text: string;
  };
}

interface HubThemeButtonOverride {
  label: string;
  icon: HubButtonIconKey;
  background_color: string | null;
  text_color: string | null;
  outline_color: string | null;
}

export const hubLayoutPresets: HubLayoutPresetDefinition[] = [
  {
    key: 'classic-cover',
    label: 'Capa classica',
    description: 'Capa forte com mensagem e botoes em destaque para eventos gerais.',
  },
  {
    key: 'hero-cards',
    label: 'Capa + cartoes',
    description: 'Separacao mais clara entre capa, informacoes e a lista principal de links.',
  },
  {
    key: 'minimal-center',
    label: 'Minimal central',
    description: 'Visual mais seco e focado nos botoes, bom para convites e paginas curtas.',
  },
];

export const hubThemePresets: HubThemePresetDefinition[] = [
  {
    key: 'midnight',
    label: 'Noite eletrica',
    description: 'Base escura premium para eventos noturnos, shows e festas.',
    layout_key: 'classic-cover',
    summary: 'Capa imersiva, destaque forte e botao principal em tom eletrico.',
    mood: 'Noturno e premium',
    recommended_for: ['Show', 'Balada', 'Festival'],
    swatches: ['#020617', '#2563eb', '#0f172a'],
  },
  {
    key: 'sunset',
    label: 'Celebra quente',
    description: 'Paleta quente com mais energia para casamentos, aniversarios e eventos sociais.',
    layout_key: 'hero-cards',
    summary: 'Blocos mais abertos, tons quentes e botoes mais leves para navegar rapido.',
    mood: 'Caloroso e celebrativo',
    recommended_for: ['Casamento', 'Aniversario', 'Evento social'],
    swatches: ['#2c0f0f', '#f97316', '#4b1d1d'],
  },
  {
    key: 'pearl',
    label: 'Editorial claro',
    description: 'Tema claro e corporativo, com leitura limpa e look editorial.',
    layout_key: 'minimal-center',
    summary: 'Layout enxuto, foco na leitura e botoes refinados com contorno.',
    mood: 'Editorial e corporativo',
    recommended_for: ['Congresso', 'Corporativo', 'Workshop'],
    swatches: ['#f8fafc', '#0f172a', '#ffffff'],
  },
  {
    key: 'wedding',
    label: 'Casamento romantico',
    description: 'Tema claro e elegante, com tons suaves para celebracoes afetivas.',
    layout_key: 'hero-cards',
    summary: 'Visual delicado com ritmo leve, ideal para links, RSVP e galeria do casal.',
    mood: 'Romantico e sofisticado',
    recommended_for: ['Casamento', 'Noivado', 'Mini wedding'],
    swatches: ['#fff7f5', '#d97786', '#f5d0d6'],
  },
  {
    key: 'quince',
    label: 'Debut vibrante',
    description: 'Mistura de brilho e energia para 15 anos, festas teen e ensaios especiais.',
    layout_key: 'hero-cards',
    summary: 'Cores vivas, botoes em destaque e ritmo mais pop para convidadas e familia.',
    mood: 'Jovem e glamouroso',
    recommended_for: ['15 anos', 'Debut', 'Festa teen'],
    swatches: ['#3b0764', '#ec4899', '#22d3ee'],
  },
  {
    key: 'corporate',
    label: 'Executivo',
    description: 'Tema limpo e confiante para eventos corporativos, feiras e convenções.',
    layout_key: 'minimal-center',
    summary: 'Leitura objetiva, hierarquia forte e estrutura pronta para agenda e links oficiais.',
    mood: 'Corporativo e objetivo',
    recommended_for: ['Corporativo', 'Feira', 'Congresso'],
    swatches: ['#ecfeff', '#0f766e', '#cffafe'],
  },
];

export const hubBlockLabels: Record<ApiHubBlockKey, string> = {
  hero: 'Capa principal',
  meta_cards: 'Informacoes rapidas',
  welcome: 'Boas-vindas',
  countdown: 'Contagem regressiva',
  info_grid: 'Grade de informacoes',
  cta_list: 'Botoes principais',
  social_strip: 'Faixa de redes',
  sponsor_strip: 'Faixa de parceiros',
};

export function cloneBuilderConfig(config: ApiHubBuilderConfig): ApiHubBuilderConfig {
  return {
    ...config,
    theme_tokens: { ...config.theme_tokens },
    block_order: [...config.block_order],
    blocks: {
      hero: { ...config.blocks.hero },
      meta_cards: { ...config.blocks.meta_cards },
      welcome: { ...config.blocks.welcome },
      countdown: { ...config.blocks.countdown },
      info_grid: {
        ...config.blocks.info_grid,
        items: config.blocks.info_grid.items.map((item) => ({ ...item })),
      },
      cta_list: { ...config.blocks.cta_list },
      social_strip: {
        ...config.blocks.social_strip,
        items: config.blocks.social_strip.items.map((item) => ({ ...item })),
      },
      sponsor_strip: {
        ...config.blocks.sponsor_strip,
        items: config.blocks.sponsor_strip.items.map((item) => ({ ...item })),
      },
    },
  };
}

export function applyLayoutPreset(config: ApiHubBuilderConfig, layoutKey: ApiHubLayoutKey): ApiHubBuilderConfig {
  const preset = getLayoutPresetConfig(layoutKey);

  return {
    ...cloneBuilderConfig(config),
    layout_key: layoutKey,
    block_order: [...preset.block_order],
    blocks: {
      hero: { ...preset.blocks.hero },
      meta_cards: { ...preset.blocks.meta_cards },
      welcome: { ...preset.blocks.welcome },
      countdown: { ...preset.blocks.countdown },
      info_grid: {
        ...preset.blocks.info_grid,
        items: preset.blocks.info_grid.items.map((item) => ({ ...item })),
      },
      cta_list: { ...preset.blocks.cta_list },
      social_strip: {
        ...preset.blocks.social_strip,
        items: preset.blocks.social_strip.items.map((item) => ({ ...item })),
      },
      sponsor_strip: {
        ...preset.blocks.sponsor_strip,
        items: preset.blocks.sponsor_strip.items.map((item) => ({ ...item })),
      },
    },
  };
}

export function applyThemePreset(
  config: ApiHubBuilderConfig,
  themeKey: ApiHubThemeKey,
  branding: HubBrandingInput,
): ApiHubBuilderConfig {
  return {
    ...cloneBuilderConfig(config),
    theme_key: themeKey,
    theme_tokens: createThemeTokens(themeKey, branding),
  };
}

export function buildHubThemePreset(
  themeKey: ApiHubThemeKey,
  branding: HubBrandingInput,
): HubThemePresetApplication {
  const definition = hubThemePresets.find((preset) => preset.key === themeKey) ?? hubThemePresets[0];
  const themeTokens = createThemeTokens(themeKey, branding);
  const builderConfig = applyThemeStructureOverrides(
    createBuilderConfig(definition.layout_key, themeKey, branding),
    themeKey,
  );

  return {
    builder_config: {
      ...builderConfig,
      theme_tokens: themeTokens,
    },
    button_style: createButtonStyle(themeKey, branding, themeTokens),
    preset_button_order: getPresetButtonOrder(themeKey),
    preset_button_overrides: getPresetButtonOverrides(themeKey),
    content_copy: createContentCopy(themeKey, branding),
  };
}

export function createBuilderConfig(
  layoutKey: ApiHubLayoutKey,
  themeKey: ApiHubThemeKey,
  branding: HubBrandingInput,
): ApiHubBuilderConfig {
  const layout = getLayoutPresetConfig(layoutKey);
  const defaultTargetAt = branding.starts_at ?? null;

  return {
    version: 1,
    layout_key: layoutKey,
    theme_key: themeKey,
    theme_tokens: createThemeTokens(themeKey, branding),
    block_order: [...layout.block_order],
    blocks: {
      hero: { ...layout.blocks.hero },
      meta_cards: { ...layout.blocks.meta_cards },
      welcome: { ...layout.blocks.welcome },
      countdown: { ...layout.blocks.countdown, target_at: defaultTargetAt, enabled: layout.blocks.countdown.enabled && Boolean(defaultTargetAt) },
      info_grid: {
        ...layout.blocks.info_grid,
        items: layout.blocks.info_grid.items.map((item) => ({ ...item })),
      },
      cta_list: { ...layout.blocks.cta_list },
      social_strip: {
        ...layout.blocks.social_strip,
        items: layout.blocks.social_strip.items.map((item) => ({ ...item })),
      },
      sponsor_strip: {
        ...layout.blocks.sponsor_strip,
        items: layout.blocks.sponsor_strip.items.map((item) => ({ ...item })),
      },
    },
  };
}

export function moveBlockOrder(order: ApiHubBlockKey[], from: ApiHubBlockKey, to: ApiHubBlockKey): ApiHubBlockKey[] {
  const next = [...order];
  const fromIndex = next.indexOf(from);
  const toIndex = next.indexOf(to);

  if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
    return order;
  }

  const [moved] = next.splice(fromIndex, 1);
  next.splice(toIndex, 0, moved);

  return next;
}

export function ensureVisibleBlocks(order: ApiHubBlockKey[], config: ApiHubBuilderConfig): ApiHubBlockKey[] {
  const visible = (['hero', 'meta_cards', 'welcome', 'countdown', 'info_grid', 'cta_list', 'social_strip', 'sponsor_strip'] as ApiHubBlockKey[]).filter((key) => {
    if (key === 'hero') return config.blocks.hero.enabled;
    if (key === 'meta_cards') return config.blocks.meta_cards.enabled;
    if (key === 'welcome') return config.blocks.welcome.enabled;
    if (key === 'countdown') return config.blocks.countdown.enabled;
    if (key === 'info_grid') return config.blocks.info_grid.enabled;
    if (key === 'cta_list') return config.blocks.cta_list.enabled;
    if (key === 'social_strip') return config.blocks.social_strip.enabled;
    return config.blocks.sponsor_strip.enabled;
  });

  const deduped = order.filter((key, index) => visible.includes(key) && order.indexOf(key) === index);
  const missing = visible.filter((key) => !deduped.includes(key));

  return [...deduped, ...missing];
}

export function createThemeTokens(themeKey: ApiHubThemeKey, branding: HubBrandingInput): ApiHubThemeTokens {
  switch (themeKey) {
    case 'sunset':
      return {
        page_background: '#2c0f0f',
        page_accent: branding.secondary_color ?? '#f97316',
        surface_background: '#4b1d1d',
        surface_border: '#fb923c',
        text_primary: '#fff7ed',
        text_secondary: '#fed7aa',
        hero_overlay_color: '#1c0a0a',
      };
    case 'wedding':
      return {
        page_background: '#fff7f5',
        page_accent: '#d97786',
        surface_background: '#ffffff',
        surface_border: '#f5d0d6',
        text_primary: '#4c0519',
        text_secondary: '#9f1239',
        hero_overlay_color: '#881337',
      };
    case 'quince':
      return {
        page_background: '#3b0764',
        page_accent: '#ec4899',
        surface_background: '#581c87',
        surface_border: '#22d3ee',
        text_primary: '#faf5ff',
        text_secondary: '#e9d5ff',
        hero_overlay_color: '#2e1065',
      };
    case 'corporate':
      return {
        page_background: '#ecfeff',
        page_accent: '#0f766e',
        surface_background: '#ffffff',
        surface_border: '#99f6e4',
        text_primary: '#134e4a',
        text_secondary: '#115e59',
        hero_overlay_color: '#134e4a',
      };
    case 'pearl':
      return {
        page_background: '#f8fafc',
        page_accent: branding.primary_color ?? '#0f172a',
        surface_background: '#ffffff',
        surface_border: '#cbd5e1',
        text_primary: '#0f172a',
        text_secondary: '#475569',
        hero_overlay_color: '#0f172a',
      };
    default:
      return {
        page_background: '#020617',
        page_accent: branding.secondary_color ?? '#2563eb',
        surface_background: '#0f172a',
        surface_border: branding.secondary_color ?? '#1d4ed8',
        text_primary: '#ffffff',
        text_secondary: '#cbd5e1',
        hero_overlay_color: '#020617',
      };
  }
}

function applyThemeStructureOverrides(
  config: ApiHubBuilderConfig,
  themeKey: ApiHubThemeKey,
): ApiHubBuilderConfig {
  const next = cloneBuilderConfig(config);

  switch (themeKey) {
    case 'sunset':
      next.block_order = ['hero', 'meta_cards', 'countdown', 'info_grid', 'cta_list', 'sponsor_strip', 'welcome'];
      next.blocks.hero = {
        ...next.blocks.hero,
        height: 'md',
        show_meta_cards: false,
        overlay_opacity: 52,
      };
      next.blocks.meta_cards = {
        ...next.blocks.meta_cards,
        enabled: true,
        style: 'solid',
      };
      next.blocks.welcome = {
        ...next.blocks.welcome,
        style: 'card',
      };
      next.blocks.countdown = {
        ...next.blocks.countdown,
        enabled: Boolean(next.blocks.countdown.target_at),
        style: 'cards',
        title: 'Falta pouco para a celebracao',
        completed_message: 'A celebracao ja comecou',
        hide_after_start: false,
      };
      next.blocks.info_grid = {
        ...next.blocks.info_grid,
        enabled: true,
        style: 'highlight',
        columns: 2,
        title: 'Informacoes para aproveitar melhor',
      };
      next.blocks.cta_list = {
        ...next.blocks.cta_list,
        style: 'soft',
        size: 'md',
        icon_position: 'left',
      };
      next.blocks.social_strip = {
        ...next.blocks.social_strip,
        enabled: true,
        style: 'chips',
        size: 'md',
      };
      next.blocks.sponsor_strip = {
        ...next.blocks.sponsor_strip,
        enabled: true,
        style: 'cards',
        title: 'Marcas e parceiros',
      };
      return next;
    case 'wedding':
      next.block_order = ['hero', 'meta_cards', 'countdown', 'welcome', 'cta_list', 'social_strip', 'sponsor_strip'];
      next.blocks.hero = {
        ...next.blocks.hero,
        height: 'md',
        show_badge: false,
        show_meta_cards: false,
        overlay_opacity: 46,
      };
      next.blocks.meta_cards = {
        ...next.blocks.meta_cards,
        enabled: true,
        style: 'minimal',
      };
      next.blocks.welcome = {
        ...next.blocks.welcome,
        style: 'card',
      };
      next.blocks.countdown = {
        ...next.blocks.countdown,
        enabled: Boolean(next.blocks.countdown.target_at),
        style: 'cards',
        title: 'Contagem para o grande dia',
        completed_message: 'O grande dia chegou',
        hide_after_start: false,
      };
      next.blocks.info_grid = {
        ...next.blocks.info_grid,
        enabled: false,
      };
      next.blocks.cta_list = {
        ...next.blocks.cta_list,
        style: 'soft',
        size: 'lg',
        icon_position: 'left',
      };
      next.blocks.social_strip = {
        ...next.blocks.social_strip,
        enabled: true,
        style: 'chips',
        size: 'sm',
      };
      next.blocks.sponsor_strip = {
        ...next.blocks.sponsor_strip,
        enabled: true,
        style: 'logos',
        title: 'Fornecedores do dia',
      };
      return next;
    case 'quince':
      next.block_order = ['hero', 'countdown', 'info_grid', 'cta_list', 'social_strip', 'welcome', 'sponsor_strip'];
      next.blocks.hero = {
        ...next.blocks.hero,
        height: 'lg',
        show_badge: true,
        show_meta_cards: false,
        overlay_opacity: 38,
      };
      next.blocks.meta_cards = {
        ...next.blocks.meta_cards,
        enabled: false,
      };
      next.blocks.welcome = {
        ...next.blocks.welcome,
        style: 'bubble',
      };
      next.blocks.countdown = {
        ...next.blocks.countdown,
        enabled: Boolean(next.blocks.countdown.target_at),
        style: 'cards',
        title: 'Falta pouco para a festa',
        completed_message: 'A pista ja abriu',
        hide_after_start: false,
      };
      next.blocks.info_grid = {
        ...next.blocks.info_grid,
        enabled: true,
        style: 'highlight',
        columns: 3,
        title: 'Destaques da noite',
      };
      next.blocks.cta_list = {
        ...next.blocks.cta_list,
        style: 'solid',
        size: 'md',
        icon_position: 'top',
      };
      next.blocks.social_strip = {
        ...next.blocks.social_strip,
        enabled: true,
        style: 'cards',
        size: 'md',
      };
      next.blocks.sponsor_strip = {
        ...next.blocks.sponsor_strip,
        enabled: false,
      };
      return next;
    case 'corporate':
      next.block_order = ['hero', 'info_grid', 'cta_list', 'countdown', 'social_strip', 'sponsor_strip', 'welcome'];
      next.blocks.hero = {
        ...next.blocks.hero,
        height: 'sm',
        show_badge: false,
        show_meta_cards: false,
        overlay_opacity: 70,
      };
      next.blocks.meta_cards = {
        ...next.blocks.meta_cards,
        enabled: false,
        style: 'minimal',
      };
      next.blocks.welcome = {
        ...next.blocks.welcome,
        style: 'inline',
      };
      next.blocks.countdown = {
        ...next.blocks.countdown,
        enabled: Boolean(next.blocks.countdown.target_at),
        style: 'inline',
        title: 'Inicio oficial em',
        completed_message: 'Evento em andamento',
        hide_after_start: false,
      };
      next.blocks.info_grid = {
        ...next.blocks.info_grid,
        enabled: true,
        style: 'cards',
        columns: 3,
        title: 'Informacoes rapidas',
      };
      next.blocks.cta_list = {
        ...next.blocks.cta_list,
        style: 'outline',
        size: 'md',
        icon_position: 'left',
      };
      next.blocks.social_strip = {
        ...next.blocks.social_strip,
        enabled: true,
        style: 'icons',
        size: 'sm',
      };
      next.blocks.sponsor_strip = {
        ...next.blocks.sponsor_strip,
        enabled: true,
        style: 'compact',
        title: 'Marcas apoiadoras',
      };
      return next;
    case 'pearl':
      next.block_order = ['hero', 'countdown', 'info_grid', 'cta_list', 'social_strip', 'sponsor_strip', 'welcome'];
      next.blocks.hero = {
        ...next.blocks.hero,
        height: 'sm',
        show_badge: false,
        show_meta_cards: false,
        overlay_opacity: 76,
      };
      next.blocks.meta_cards = {
        ...next.blocks.meta_cards,
        enabled: false,
        style: 'minimal',
      };
      next.blocks.welcome = {
        ...next.blocks.welcome,
        style: 'inline',
      };
      next.blocks.countdown = {
        ...next.blocks.countdown,
        enabled: Boolean(next.blocks.countdown.target_at),
        style: 'inline',
        title: 'Comeca em',
        completed_message: 'Evento em andamento',
        hide_after_start: false,
      };
      next.blocks.info_grid = {
        ...next.blocks.info_grid,
        enabled: true,
        style: 'cards',
        columns: 2,
        title: 'Destaques do encontro',
      };
      next.blocks.cta_list = {
        ...next.blocks.cta_list,
        style: 'outline',
        size: 'lg',
        icon_position: 'top',
      };
      next.blocks.social_strip = {
        ...next.blocks.social_strip,
        enabled: true,
        style: 'icons',
        size: 'sm',
      };
      next.blocks.sponsor_strip = {
        ...next.blocks.sponsor_strip,
        enabled: true,
        style: 'logos',
        title: 'Patrocinadores',
      };
      return next;
    default:
      next.block_order = ['hero', 'welcome', 'countdown', 'cta_list', 'social_strip', 'info_grid', 'sponsor_strip'];
      next.blocks.hero = {
        ...next.blocks.hero,
        height: 'lg',
        show_badge: true,
        show_meta_cards: true,
        overlay_opacity: 64,
      };
      next.blocks.meta_cards = {
        ...next.blocks.meta_cards,
        enabled: true,
        style: 'glass',
      };
      next.blocks.welcome = {
        ...next.blocks.welcome,
        style: 'bubble',
      };
      next.blocks.countdown = {
        ...next.blocks.countdown,
        enabled: Boolean(config.blocks.countdown.target_at),
        style: 'minimal',
        title: 'Contagem oficial',
        completed_message: 'O evento ja comecou',
        hide_after_start: false,
      };
      next.blocks.info_grid = {
        ...next.blocks.info_grid,
        enabled: false,
        style: 'cards',
        columns: 2,
        title: 'Guia rapido do evento',
      };
      next.blocks.cta_list = {
        ...next.blocks.cta_list,
        style: 'solid',
        size: 'lg',
        icon_position: 'left',
      };
      next.blocks.social_strip = {
        ...next.blocks.social_strip,
        enabled: false,
        style: 'icons',
        size: 'md',
      };
      next.blocks.sponsor_strip = {
        ...next.blocks.sponsor_strip,
        enabled: false,
        style: 'logos',
        title: 'Marcas apoiadoras',
      };
      return next;
  }
}

function createButtonStyle(
  themeKey: ApiHubThemeKey,
  branding: HubBrandingInput,
  themeTokens: ApiHubThemeTokens,
): ApiHubButtonStyle {
  switch (themeKey) {
    case 'sunset':
      return {
        background_color: branding.secondary_color ?? '#f97316',
        text_color: '#fff7ed',
        outline_color: mixHex(themeTokens.page_accent, '#ffffff', 0.32),
      };
    case 'wedding':
      return {
        background_color: '#d97786',
        text_color: '#fff7f5',
        outline_color: '#f5d0d6',
      };
    case 'quince':
      return {
        background_color: '#ec4899',
        text_color: '#faf5ff',
        outline_color: '#22d3ee',
      };
    case 'corporate':
      return {
        background_color: '#0f766e',
        text_color: '#f0fdfa',
        outline_color: '#99f6e4',
      };
    case 'pearl':
      return {
        background_color: branding.primary_color ?? '#0f172a',
        text_color: '#ffffff',
        outline_color: '#cbd5e1',
      };
    default:
      return {
        background_color: branding.secondary_color ?? '#2563eb',
        text_color: '#ffffff',
        outline_color: mixHex(themeTokens.page_accent, '#020617', 0.2),
      };
  }
}

function getPresetButtonOrder(themeKey: ApiHubThemeKey): HubPresetActionKey[] {
  switch (themeKey) {
    case 'sunset':
      return ['upload', 'gallery', 'play', 'wall'];
    case 'wedding':
      return ['gallery', 'upload', 'wall', 'play'];
    case 'quince':
      return ['upload', 'gallery', 'wall', 'play'];
    case 'corporate':
      return ['gallery', 'wall', 'upload', 'play'];
    case 'pearl':
      return ['gallery', 'upload', 'wall', 'play'];
    default:
      return ['upload', 'gallery', 'wall', 'play'];
  }
}

function getPresetButtonOverrides(themeKey: ApiHubThemeKey): Record<HubPresetActionKey, HubThemeButtonOverride> {
  switch (themeKey) {
    case 'sunset':
      return {
        upload: createButtonOverride('Compartilhar foto', 'camera'),
        gallery: createButtonOverride('Ver destaques', 'sparkles'),
        wall: createButtonOverride('Abrir telao', 'monitor'),
        play: createButtonOverride('Jogar agora', 'gamepad'),
      };
    case 'wedding':
      return {
        upload: createButtonOverride('Enviar memoria', 'camera'),
        gallery: createButtonOverride('Ver album', 'image'),
        wall: createButtonOverride('Abrir telao', 'monitor'),
        play: createButtonOverride('Momento interativo', 'sparkles'),
      };
    case 'quince':
      return {
        upload: createButtonOverride('Postar foto', 'camera'),
        gallery: createButtonOverride('Ver destaques', 'sparkles'),
        wall: createButtonOverride('Abrir painel', 'monitor'),
        play: createButtonOverride('Entrar na brincadeira', 'gamepad'),
      };
    case 'corporate':
      return {
        upload: createButtonOverride('Enviar registro', 'camera'),
        gallery: createButtonOverride('Acessar galeria', 'image'),
        wall: createButtonOverride('Ver painel', 'monitor'),
        play: createButtonOverride('Ativar experiencia', 'sparkles'),
      };
    case 'pearl':
      return {
        upload: createButtonOverride('Enviar conteudo', 'camera'),
        gallery: createButtonOverride('Acessar galeria', 'image'),
        wall: createButtonOverride('Abrir apresentacao', 'monitor'),
        play: createButtonOverride('Experiencia interativa', 'sparkles'),
      };
    default:
      return {
        upload: createButtonOverride('Enviar foto', 'camera'),
        gallery: createButtonOverride('Galeria ao vivo', 'image'),
        wall: createButtonOverride('Abrir telao', 'monitor'),
        play: createButtonOverride('Jogar agora', 'gamepad'),
      };
  }
}

function createButtonOverride(label: string, icon: HubButtonIconKey): HubThemeButtonOverride {
  return {
    label,
    icon,
    background_color: null,
    text_color: null,
    outline_color: null,
  };
}

function getLayoutPresetConfig(layoutKey: ApiHubLayoutKey) {
  switch (layoutKey) {
    case 'hero-cards':
      return {
        block_order: ['hero', 'meta_cards', 'countdown', 'info_grid', 'welcome', 'cta_list', 'sponsor_strip'] as ApiHubBlockKey[],
        blocks: {
          hero: {
            enabled: true,
            show_logo: true,
            show_badge: true,
            show_meta_cards: false,
            height: 'md' as const,
            overlay_opacity: 58,
          },
          meta_cards: {
            enabled: true,
            show_date: true,
            show_location: true,
            style: 'glass' as const,
          },
          welcome: {
            enabled: true,
            style: 'card' as const,
          },
          countdown: createDefaultCountdownBlock(),
          info_grid: createDefaultInfoGridBlock(),
          cta_list: {
            enabled: true,
            style: 'solid' as const,
            size: 'md' as const,
            icon_position: 'left' as const,
          },
          social_strip: {
            enabled: true,
            style: 'chips' as const,
            size: 'md' as const,
            items: [] as ReturnType<typeof createDefaultSocialItems>,
          },
          sponsor_strip: createDefaultSponsorStripBlock({
            style: 'cards',
          }),
        },
      };
    case 'minimal-center':
      return {
        block_order: ['hero', 'countdown', 'info_grid', 'cta_list', 'social_strip', 'sponsor_strip', 'welcome'] as ApiHubBlockKey[],
        blocks: {
          hero: {
            enabled: true,
            show_logo: true,
            show_badge: false,
            show_meta_cards: false,
            height: 'sm' as const,
            overlay_opacity: 72,
          },
          meta_cards: {
            enabled: false,
            show_date: true,
            show_location: true,
            style: 'minimal' as const,
          },
          welcome: {
            enabled: true,
            style: 'inline' as const,
          },
          countdown: createDefaultCountdownBlock({
            style: 'inline',
          }),
          info_grid: createDefaultInfoGridBlock({
            style: 'cards',
            columns: 2,
          }),
          cta_list: {
            enabled: true,
            style: 'outline' as const,
            size: 'lg' as const,
            icon_position: 'top' as const,
          },
          social_strip: {
            enabled: true,
            style: 'icons' as const,
            size: 'sm' as const,
            items: [] as ReturnType<typeof createDefaultSocialItems>,
          },
          sponsor_strip: createDefaultSponsorStripBlock({
            style: 'logos',
          }),
        },
      };
    default:
      return {
        block_order: ['hero', 'welcome', 'countdown', 'cta_list', 'social_strip', 'info_grid', 'sponsor_strip'] as ApiHubBlockKey[],
        blocks: {
          hero: {
            enabled: true,
            show_logo: true,
            show_badge: true,
            show_meta_cards: true,
            height: 'lg' as const,
            overlay_opacity: 64,
          },
          meta_cards: {
            enabled: true,
            show_date: true,
            show_location: true,
            style: 'glass' as const,
          },
          welcome: {
            enabled: true,
            style: 'bubble' as const,
          },
          countdown: createDefaultCountdownBlock({
            style: 'minimal',
          }),
          info_grid: createDefaultInfoGridBlock({
            style: 'minimal',
            columns: 2,
          }),
          cta_list: {
            enabled: true,
            style: 'solid' as const,
            size: 'lg' as const,
            icon_position: 'left' as const,
          },
          social_strip: {
            enabled: false,
            style: 'icons' as const,
            size: 'md' as const,
            items: [] as ReturnType<typeof createDefaultSocialItems>,
          },
          sponsor_strip: createDefaultSponsorStripBlock({
            style: 'logos',
          }),
        },
      };
  }
}

function createContentCopy(themeKey: ApiHubThemeKey, event: HubBrandingInput) {
  const headline = event.title ?? 'Evento Vivo';
  const subheadline = [formatEventDate(event.starts_at), event.location_name].filter(Boolean).join(' - ');
  const description = event.description?.trim();

  switch (themeKey) {
    case 'sunset':
      return {
        headline,
        subheadline,
        welcome_text: description || 'Reunimos aqui os links principais para compartilhar, acompanhar e reviver cada momento deste evento.',
      };
    case 'wedding':
      return {
        headline,
        subheadline,
        welcome_text: description || 'Tudo o que os convidados precisam para acompanhar esse dia especial esta reunido aqui.',
      };
    case 'quince':
      return {
        headline,
        subheadline,
        welcome_text: description || 'Aqui ficam os links da festa, os destaques da noite e os caminhos para entrar na experiencia.',
      };
    case 'corporate':
      return {
        headline,
        subheadline,
        welcome_text: description || 'Acesse os canais oficiais, materiais e experiencias deste evento em um so lugar.',
      };
    case 'pearl':
      return {
        headline,
        subheadline,
        welcome_text: description || 'Acesse os canais oficiais deste evento com uma navegação clara, direta e pronta para qualquer tela.',
      };
    default:
      return {
        headline,
        subheadline,
        welcome_text: description || 'Tudo o que importa para viver o evento agora está reunido neste hub oficial.',
      };
  }
}

function formatEventDate(value?: string | null) {
  if (!value) {
    return '';
  }

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function createDefaultSocialItems() {
  return [] as Array<{
    id: string;
    provider: ApiHubSocialProviderKey;
    label: string;
    href: string | null;
    icon: HubButtonIconKey;
    is_visible: boolean;
    opens_in_new_tab: boolean;
  }>;
}

function createDefaultCountdownBlock(overrides: Partial<ApiHubBuilderConfig['blocks']['countdown']> = {}) {
  return {
    enabled: false,
    style: 'cards' as const,
    target_mode: 'event_start' as const,
    target_at: null,
    title: 'Falta pouco',
    completed_message: 'O evento ja comecou',
    hide_after_start: false,
    ...overrides,
  };
}

function createDefaultInfoGridBlock(overrides: Partial<ApiHubBuilderConfig['blocks']['info_grid']> = {}) {
  return {
    enabled: false,
    title: 'Informacoes importantes',
    style: 'cards' as const,
    columns: 2 as const,
    items: [] as Array<{
      id: string;
      title: string;
      value: string;
      description: string | null;
      icon: HubButtonIconKey;
      is_visible: boolean;
    }>,
    ...overrides,
  };
}

function createDefaultSponsorStripBlock(overrides: Partial<ApiHubBuilderConfig['blocks']['sponsor_strip']> = {}) {
  return {
    enabled: false,
    title: 'Patrocinadores',
    style: 'logos' as const,
    items: [] as Array<{
      id: string;
      name: string;
      subtitle: string | null;
      logo_path: string | null;
      href: string | null;
      is_visible: boolean;
      opens_in_new_tab: boolean;
    }>,
    ...overrides,
  };
}

function mixHex(primary: string, secondary: string, ratio: number) {
  const base = parseHex(primary);
  const overlay = parseHex(secondary);
  const weight = Math.max(0, Math.min(ratio, 1));

  const mixed = {
    red: Math.round(base.red * (1 - weight) + overlay.red * weight),
    green: Math.round(base.green * (1 - weight) + overlay.green * weight),
    blue: Math.round(base.blue * (1 - weight) + overlay.blue * weight),
  };

  return `#${toHex(mixed.red)}${toHex(mixed.green)}${toHex(mixed.blue)}`;
}

function parseHex(hex: string) {
  const value = hex.replace('#', '');
  const safe = value.length === 6 ? value : '000000';
  const number = Number.parseInt(safe, 16);

  return {
    red: (number >> 16) & 255,
    green: (number >> 8) & 255,
    blue: number & 255,
  };
}

function toHex(value: number) {
  return value.toString(16).padStart(2, '0');
}
