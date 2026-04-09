import type {
  ApiWallEventPhaseOption,
  ApiWallOptionsResponse,
  ApiWallSelectionModeOption,
  ApiWallSettings,
  ApiWallVideoAudioPolicy,
  ApiWallVideoMultiLayoutPolicy,
  ApiWallVideoPlaybackMode,
  ApiWallVideoPreferredVariant,
  ApiWallVideoResumeMode,
} from '@/lib/api-types';

export const fallbackOptions: ApiWallOptionsResponse = {
  layouts: [
    { value: 'auto', label: 'Automatico' },
    { value: 'fullscreen', label: 'Tela cheia' },
    { value: 'polaroid', label: 'Polaroid' },
    { value: 'split', label: 'Tela dividida' },
    { value: 'cinematic', label: 'Cinematografico' },
    { value: 'kenburns', label: 'Ken Burns' },
    { value: 'spotlight', label: 'Holofote' },
    { value: 'gallery', label: 'Galeria de arte' },
    { value: 'carousel', label: 'Carrossel' },
    { value: 'mosaic', label: 'Mosaico' },
    { value: 'grid', label: 'Grade' },
  ],
  transitions: [
    { value: 'fade', label: 'Suave' },
    { value: 'slide', label: 'Deslizar' },
    { value: 'zoom', label: 'Aproximar' },
    { value: 'flip', label: 'Virar' },
    { value: 'none', label: 'Nenhuma' },
  ],
  statuses: [],
  event_phases: [
    {
      value: 'reception',
      label: 'Recepcao',
      description: 'Mais cuidado com repeticao enquanto o evento ainda esta enchendo.',
    },
    {
      value: 'flow',
      label: 'Fluxo',
      description: 'Equilibrio padrao entre novidade, repeticao controlada e boa distribuicao.',
    },
    {
      value: 'party',
      label: 'Festa',
      description: 'Ritmo mais vivo e menor atraso para valorizar o pico da festa.',
    },
    {
      value: 'closing',
      label: 'Encerramento',
      description: 'Ritmo mais contemplativo com mais elasticidade para reprises finais.',
    },
  ],
  selection_modes: [
    {
      value: 'balanced',
      label: 'Equilibrado',
      description: 'Distribui melhor entre convidados e evita monopolizacao do telao.',
      selection_policy: {
        max_eligible_items_per_sender: 4,
        max_replays_per_item: 2,
        low_volume_max_items: 6,
        medium_volume_max_items: 12,
        replay_interval_low_minutes: 8,
        replay_interval_medium_minutes: 12,
        replay_interval_high_minutes: 20,
        sender_cooldown_seconds: 60,
        sender_window_limit: 3,
        sender_window_minutes: 10,
        avoid_same_sender_if_alternative_exists: true,
        avoid_same_duplicate_cluster_if_alternative_exists: true,
      },
    },
    {
      value: 'live',
      label: 'Ao vivo',
      description: 'Valoriza fotos recem-chegadas sem perder a justica basica da fila.',
      selection_policy: {
        max_eligible_items_per_sender: 5,
        max_replays_per_item: 3,
        low_volume_max_items: 6,
        medium_volume_max_items: 12,
        replay_interval_low_minutes: 5,
        replay_interval_medium_minutes: 8,
        replay_interval_high_minutes: 12,
        sender_cooldown_seconds: 30,
        sender_window_limit: 4,
        sender_window_minutes: 10,
        avoid_same_sender_if_alternative_exists: true,
        avoid_same_duplicate_cluster_if_alternative_exists: true,
      },
    },
    {
      value: 'inclusive',
      label: 'Inclusivo',
      description: 'Prioriza mostrar pessoas diferentes antes de repetir remetentes.',
      selection_policy: {
        max_eligible_items_per_sender: 3,
        max_replays_per_item: 1,
        low_volume_max_items: 6,
        medium_volume_max_items: 12,
        replay_interval_low_minutes: 10,
        replay_interval_medium_minutes: 14,
        replay_interval_high_minutes: 20,
        sender_cooldown_seconds: 90,
        sender_window_limit: 2,
        sender_window_minutes: 10,
        avoid_same_sender_if_alternative_exists: true,
        avoid_same_duplicate_cluster_if_alternative_exists: true,
      },
    },
    {
      value: 'editorial',
      label: 'Editorial',
      description: 'Mantem a justica base, mas abre mais espaco para destaques da operacao.',
      selection_policy: {
        max_eligible_items_per_sender: 4,
        max_replays_per_item: 2,
        low_volume_max_items: 6,
        medium_volume_max_items: 12,
        replay_interval_low_minutes: 8,
        replay_interval_medium_minutes: 12,
        replay_interval_high_minutes: 16,
        sender_cooldown_seconds: 45,
        sender_window_limit: 3,
        sender_window_minutes: 10,
        avoid_same_sender_if_alternative_exists: true,
        avoid_same_duplicate_cluster_if_alternative_exists: true,
      },
    },
    {
      value: 'custom',
      label: 'Personalizado',
      description: 'Usa ajustes manuais para controlar repeticao e distribuicao entre convidados.',
      selection_policy: {
        max_eligible_items_per_sender: 4,
        max_replays_per_item: 2,
        low_volume_max_items: 6,
        medium_volume_max_items: 12,
        replay_interval_low_minutes: 8,
        replay_interval_medium_minutes: 12,
        replay_interval_high_minutes: 20,
        sender_cooldown_seconds: 60,
        sender_window_limit: 3,
        sender_window_minutes: 10,
        avoid_same_sender_if_alternative_exists: true,
        avoid_same_duplicate_cluster_if_alternative_exists: true,
      },
    },
  ],
};

export const HELP_TEXTS = {
  realtime: {
    title: 'Atualizacao ao vivo',
    description: 'Mostra se esta tela esta recebendo as mudancas do telao na hora.',
    why: 'Isso ajuda a saber se suas mudancas estao sendo sincronizadas imediatamente com o telao.',
  },
  wallCode: {
    title: 'Codigo do telao',
    description: 'E o codigo unico que identifica este telao no sistema.',
    why: 'Ele serve para conectar o telao certo e facilitar suporte quando houver mais de um telao no evento.',
  },
  slideshowSection: {
    title: 'Ajustes da exibicao',
    description: 'Aqui voce define como as fotos e videos vao aparecer no telao.',
    why: 'Esses ajustes controlam ritmo, volume de conteudo e elementos visuais que aparecem junto das midias.',
  },
  selectionMode: {
    title: 'Modo do telao',
    description: 'Define o comportamento principal da fila antes dos ajustes finos.',
    why: 'Com esses modos, o operador escolhe uma base segura sem precisar entender a regra tecnica completa.',
  },
  eventPhase: {
    title: 'Fase do evento',
    description: 'Aplica um contexto operacional por cima do modo escolhido sem destruir seus ajustes manuais.',
    why: 'A fase ajuda o telao a se comportar de um jeito diferente na recepcao, no pico da festa e no encerramento sem exigir nova configuracao.',
  },
  fairnessSection: {
    title: 'Fila e justica',
    description: 'Regras que impedem uma unica pessoa de dominar a exibicao.',
    why: 'Esse bloco controla o tempo de espera, o volume por pessoa e a alternancia entre convidados na tela.',
  },
  senderCooldown: {
    title: 'Tempo minimo entre aparicoes',
    description: 'E o tempo que o mesmo remetente precisa esperar para voltar a aparecer quando ha alternativas.',
    why: 'Isso deixa o telao mais coletivo e reduz a sensacao de repeticao excessiva nos picos de envio.',
  },
  senderWindowLimit: {
    title: 'Limite por janela',
    description: 'Controla quantas fotos do mesmo remetente podem aparecer dentro de uma janela de tempo.',
    why: 'Serve para evitar monopolizacao sem precisar bloquear ou apagar o que a pessoa enviou.',
  },
  senderWindowMinutes: {
    title: 'Janela de controle',
    description: 'Define a duracao da janela usada no limite por remetente.',
    why: 'Janelas menores reagem mais rapido a rajadas; janelas maiores distribuem melhor eventos longos.',
  },
  maxEligibleItems: {
    title: 'Backlog elegivel por remetente',
    description: 'Quantidade maxima de midias do mesmo remetente disputando a tela ao mesmo tempo.',
    why: 'O restante continua aguardando, mas entra aos poucos para nao tomar conta da fila.',
  },
  maxReplaysPerItem: {
    title: 'Maximo de repeticoes por foto',
    description: 'Controla quantas vezes a mesma foto ou video pode voltar para a fila antes de o sistema liberar novas reprises.',
    why: 'Isso reduz a sensacao de loop infinito quando a festa esta com pouco conteudo novo, sem apagar a foto da fila.',
  },
  replayAdaptiveSection: {
    title: 'Repeticao por volume da fila',
    description: 'Ajusta o tempo minimo para repetir uma foto conforme o tamanho atual da fila do telao.',
    why: 'Com pouco conteudo, a repeticao pode ser mais rapida. Com muita fila, ela fica mais espaçada para priorizar novidade.',
  },
  lowVolumeThreshold: {
    title: 'Faixa de fila baixa',
    description: 'Quantidade maxima de itens para o telao ainda ser tratado como fila baixa.',
    why: 'Quando o evento esta vazio, o telao precisa girar mais rapido sem parecer travado.',
  },
  mediumVolumeThreshold: {
    title: 'Faixa de fila media',
    description: 'Quantidade maxima de itens para a fila media antes de virar fila alta.',
    why: 'Isso define quando o sistema precisa espaçar mais as repeticoes para proteger novidade e variedade.',
  },
  replayIntervalLow: {
    title: 'Repeticao com fila curta',
    description: 'Tempo minimo para uma foto voltar quando a fila esta curta.',
    why: 'Use menos tempo quando entram poucas fotos; use mais tempo se a repeticao estiver cansando.',
  },
  replayIntervalMedium: {
    title: 'Repeticao com fila media',
    description: 'Tempo minimo para repetir quando o evento esta em volume intermediario.',
    why: 'Esse e o comportamento mais comum da noite e precisa equilibrar novidade e continuidade.',
  },
  replayIntervalHigh: {
    title: 'Repeticao com fila cheia',
    description: 'Tempo minimo para repetir quando ha muito conteudo novo no wall.',
    why: 'Em pico de festa, a repeticao precisa ser mais rara para dar espaco ao que acabou de chegar.',
  },
  antiDuplicateSequence: {
    title: 'Anti-sequencia parecida',
    description: 'Evita puxar duas midias muito parecidas da mesma pessoa quando existir alternativa.',
    why: 'Isso reduz sequencias de fotos quase iguais e melhora a percepcao de variedade no telao.',
  },
  interval: {
    title: 'Tempo de cada midia',
    description: 'E o tempo que cada foto ou video fica na tela antes de passar para o proximo.',
    why: 'Use menos tempo quando entram muitas fotos e mais tempo quando voce quer dar destaque maior a cada envio.',
  },
  queueLimit: {
    title: 'Maximo de midias na fila',
    description: 'E a quantidade maxima de midias que o telao mantem prontas para exibir.',
    why: 'Isso existe para o telao nao ficar muito atrasado mostrando conteudo antigo. Quando a fila lota, o sistema prioriza as midias mais recentes.',
  },
  qrOverlay: {
    title: 'Mostrar QR para envio',
    description: 'Mostra um QR Code no canto do telao para o publico abrir a pagina de envio de fotos.',
    why: 'Deixe ligado quando quiser incentivar novas participacoes durante o evento.',
  },
  branding: {
    title: 'Mostrar marca',
    description: 'Controla a exibicao da assinatura visual do telao, como a marca da plataforma e elementos de identidade.',
    why: 'Desative se quiser uma tela mais limpa ou se o evento pedir uma apresentacao sem marcas visiveis.',
  },
  senderCredit: {
    title: 'Mostrar nome de quem enviou',
    description: 'Mostra no telao o nome de quem enviou a foto ou video.',
    why: 'Isso da reconhecimento ao participante, mas pode poluir a tela em eventos mais formais.',
  },
  neonToggle: {
    title: 'Chamada em destaque',
    description: 'Liga uma frase fixa em destaque no canto do telao.',
    why: 'Serve para reforcar o nome da festa, uma hashtag ou uma chamada curta para o publico.',
  },
  neonText: {
    title: 'Texto da chamada',
    description: 'E a frase que vai aparecer nesse destaque visual.',
    why: 'Prefira um texto curto e facil de ler de longe, como o nome do evento ou uma hashtag.',
  },
  neonColor: {
    title: 'Cor da chamada',
    description: 'Define a cor do destaque fixo do texto neon.',
    why: 'Use uma cor com bom contraste para o texto continuar visivel no telao.',
  },
  layoutSection: {
    title: 'Visual e troca de fotos',
    description: 'Essas opcoes mudam a forma como as midias entram, ocupam a tela e trocam entre si.',
    why: 'Com isso voce adapta o telao ao estilo do evento sem precisar trocar o sistema de exibicao.',
  },
  layout: {
    title: 'Estilo da exibicao',
    description: 'Define o estilo visual de exibicao das fotos e videos.',
    why: 'O modo Automatico tenta escolher a melhor composicao sozinho. Os demais forcam um estilo especifico.',
  },
  transition: {
    title: 'Animacao de troca',
    description: 'E a animacao usada quando uma midia sai da tela e a proxima entra.',
    why: 'Animacoes suaves deixam o telao mais elegante. Animacoes fortes chamam mais atencao, mas podem cansar em exibicoes longas.',
  },
  idleSection: {
    title: 'Mensagem quando nao ha fotos',
    description: 'Essa mensagem aparece quando o telao esta ligado, mas ainda nao ha midias para exibir.',
    why: 'E o melhor lugar para explicar ao publico como participar e enviar conteudo.',
  },
  instructions: {
    title: 'Texto de espera',
    description: 'Texto mostrado enquanto o telao espera novas fotos e videos.',
    why: 'Use uma frase simples ensinando a escanear o QR Code ou acessar o link de envio.',
  },
  advancedActions: {
    title: 'Acoes avancadas',
    description: 'Esses comandos fazem mudancas fortes no funcionamento do telao.',
    why: 'Use apenas quando precisar encerrar ou parar totalmente a exibicao. Eles afetam o telao imediatamente.',
  },
  saving: {
    title: 'Salvar alteracoes',
    description: 'As mudancas feitas nesta tela ficam locais ate voce tocar em salvar.',
    why: 'Isso reduz chamadas em redes lentas e evita varias atualizacoes pequenas enquanto voce ainda esta ajustando o telao.',
  },
  orientation: {
    title: 'Orientacao aceita',
    description: 'Filtra quais midias aparecem no telao com base na orientacao. Midias quadradas sempre passam em qualquer filtro.',
    why: 'Quando o telao esta em formato 16:9, fotos verticais ficam com barras laterais grandes e podem prejudicar a experiencia visual.',
  },
  sideThumbnails: {
    title: 'Miniaturas laterais',
    description: 'Exibe uma faixa com as proximas midias na lateral da tela principal. Mantem o publico curioso sobre o proximo conteudo.',
    why: 'Mostrar a fila de midias vindouras gera engajamento e transparencia sobre a ordem de exibicao.',
  },
  videoPolicySection: {
    title: 'Politica de video',
    description: 'Controla quando videos entram, como eles tocam e quando o wall pode interromper para seguir a fila.',
    why: 'Esse bloco evita cortes inesperados, travamentos por video pesado e configuracoes contraditorias entre upload, player e operador.',
  },
  videoEnabled: {
    title: 'Suportar videos no telao',
    description: 'Liga ou desliga a trilha oficial de video neste wall.',
    why: 'Quando desligado, o player continua exibindo apenas imagens e o intake publico bloqueia video para este evento.',
  },
  videoPlaybackMode: {
    title: 'Modo de reproducao do video',
    description: 'Define se o wall trata video como tempo fixo, playback ate o fim ou playback ate um cap.',
    why: 'Essa decisao muda o ritmo do telao e evita o comportamento de “video cortado do nada” em arquivos longos.',
  },
  videoMaxSeconds: {
    title: 'Duracao maxima de video',
    description: 'Cap operacional aplicado a videos comuns do wall.',
    why: 'Serve para impedir que um unico video longo segure a fila inteira em momentos de pico.',
  },
  videoResumeMode: {
    title: 'Comportamento ao retomar',
    description: 'Escolhe se o wall continua do ponto atual ou reinicia o video quando sai da pausa.',
    why: 'Essa regra precisa ser previsivel para o operador saber se uma pausa curta preserva o playback atual.',
  },
  videoAudioPolicy: {
    title: 'Audio do video',
    description: 'Define se o wall mantem o video mudo ou tenta outra politica no futuro.',
    why: 'Autoplay confiavel em navegadores modernos depende de video mudo na grande maioria dos cenarios de telão.',
  },
  videoMultiLayoutPolicy: {
    title: 'Video em layouts multi-slot',
    description: 'Controla se mosaico, grade e carrossel podem receber video.',
    why: 'Limitar ou bloquear video nesses layouts reduz decode paralelo, buffering e ruido visual.',
  },
  videoPreferredVariant: {
    title: 'Variante preferida do video',
    description: 'Escolhe qual variante o player tenta usar antes de cair em alternativas.',
    why: 'Priorizar a variante certa reduz peso de rede, melhora startup e evita depender do original pesado.',
  },
} as const;

export type WallHelpKey = keyof typeof HELP_TEXTS;

export const WALL_SELECTION_MODE_OPTIONS: ApiWallSelectionModeOption[] = fallbackOptions.selection_modes;
export const WALL_EVENT_PHASE_OPTIONS: ApiWallEventPhaseOption[] = fallbackOptions.event_phases;
export const WALL_COOLDOWN_OPTIONS = [0, 30, 45, 60, 90, 120];
export const WALL_WINDOW_MINUTE_OPTIONS = [5, 10, 15];
export const WALL_VOLUME_THRESHOLD_OPTIONS = [4, 6, 8, 10, 12, 16, 20, 24, 30, 40, 50];
export const WALL_REPLAY_MINUTE_OPTIONS = [5, 8, 10, 12, 14, 16, 20, 25, 30];
export const WALL_VIDEO_MAX_SECONDS_OPTIONS = [10, 12, 15, 20, 30, 45, 60];

export const WALL_VIDEO_PLAYBACK_MODE_OPTIONS: Array<{
  value: ApiWallVideoPlaybackMode;
  label: string;
  description: string;
}> = [
  {
    value: 'fixed_interval',
    label: 'Tempo fixo do slide',
    description: 'O video respeita o mesmo intervalo das imagens.',
  },
  {
    value: 'play_to_end',
    label: 'Tocar ate o fim',
    description: 'O wall espera o termino natural do video.',
  },
  {
    value: 'play_to_end_if_short_else_cap',
    label: 'Fim se curto, senao cap',
    description: 'Videos curtos vao ate o fim e videos longos seguem o limite configurado.',
  },
];

export const WALL_VIDEO_RESUME_MODE_OPTIONS: Array<{
  value: ApiWallVideoResumeMode;
  label: string;
  description: string;
}> = [
  {
    value: 'resume_if_same_item_else_restart',
    label: 'Retomar se for o mesmo item',
    description: 'Continua do ponto atual quando o mesmo video segue em foco e reinicia se a fila mudar.',
  },
  {
    value: 'resume_if_same_item',
    label: 'Sempre tentar retomar',
    description: 'Mantem o ponto atual sempre que o mesmo video continuar montado.',
  },
  {
    value: 'restart_from_zero',
    label: 'Reiniciar do comeco',
    description: 'Volta o video para o inicio na retomada apos pausa.',
  },
];

export const WALL_VIDEO_AUDIO_POLICY_OPTIONS: Array<{
  value: ApiWallVideoAudioPolicy;
  label: string;
  description: string;
}> = [
  {
    value: 'muted',
    label: 'Sempre mudo',
    description: 'Politica segura para autoplay confiavel em Chrome e navegadores mobile.',
  },
];

export const WALL_VIDEO_MULTI_LAYOUT_OPTIONS: Array<{
  value: ApiWallVideoMultiLayoutPolicy;
  label: string;
  description: string;
}> = [
  {
    value: 'disallow',
    label: 'Nao permitir',
    description: 'Sempre cai para layout single-item quando a midia atual e video.',
  },
  {
    value: 'one',
    label: 'Permitir no maximo 1',
    description: 'Abre espaco para um unico video simultaneo em layouts multi-slot.',
  },
  {
    value: 'all',
    label: 'Permitir todos',
    description: 'Mantem layouts multi-slot livres para videos quando o device aguentar.',
  },
];

export const WALL_VIDEO_PREFERRED_VARIANT_OPTIONS: Array<{
  value: ApiWallVideoPreferredVariant;
  label: string;
  description: string;
}> = [
  {
    value: 'wall_video_720p',
    label: '720p otimizado',
    description: 'Padrao mais leve para startup rapido e decode previsivel.',
  },
  {
    value: 'wall_video_1080p',
    label: '1080p otimizado',
    description: 'Usa a variante 1080p quando houver hardware e rede para isso.',
  },
  {
    value: 'original',
    label: 'Arquivo original',
    description: 'Permite fallback para o arquivo original quando a policy aceitar.',
  },
];

export const WALL_TOGGLE_FIELDS: Array<{
  key: keyof Pick<ApiWallSettings, 'show_qr' | 'show_branding' | 'show_sender_credit' | 'show_neon'>;
  label: string;
  helpKey: WallHelpKey;
  description: string;
}> = [
  {
    key: 'show_qr',
    label: 'Mostrar QR para envio',
    helpKey: 'qrOverlay',
    description: 'Mantem o QR visivel no telao quando habilitado.',
  },
  {
    key: 'show_branding',
    label: 'Mostrar marca',
    helpKey: 'branding',
    description: 'Liga ou desliga a assinatura visual do telao.',
  },
  {
    key: 'show_sender_credit',
    label: 'Mostrar nome de quem enviou',
    helpKey: 'senderCredit',
    description: 'Mostra o nome de quem enviou a midia na exibicao.',
  },
  {
    key: 'show_neon',
    label: 'Chamada em destaque',
    helpKey: 'neonToggle',
    description: 'Mostra uma chamada destacada sobre o layout do telao.',
  },
];

export const WALL_SLIDER_FIELDS: Array<{
  key: keyof Pick<ApiWallSettings, 'interval_ms' | 'queue_limit'>;
  label: string;
  helpKey: WallHelpKey;
  min: number;
  max: number;
  step: number;
  formatValue: (value: number) => string;
  toControlValue: (value: number) => number;
  fromControlValue: (value: number) => number;
}> = [
  {
    key: 'interval_ms',
    label: 'Tempo de cada midia',
    helpKey: 'interval',
    min: 2,
    max: 60,
    step: 1,
    formatValue: (value) => `${Math.round(value / 1000)}s`,
    toControlValue: (value) => Math.round(value / 1000),
    fromControlValue: (value) => value * 1000,
  },
  {
    key: 'queue_limit',
    label: 'Maximo de midias na fila',
    helpKey: 'queueLimit',
    min: 5,
    max: 500,
    step: 5,
    formatValue: (value) => String(value),
    toControlValue: (value) => value,
    fromControlValue: (value) => value,
  },
];
