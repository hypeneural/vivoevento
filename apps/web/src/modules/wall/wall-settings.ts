import type {
  ApiWallEventPhase,
  ApiWallLayoutOption,
  ApiWallSelectionMode,
  ApiWallSelectionModeOption,
  ApiWallSelectionPolicy,
  ApiWallSettings,
  ApiWallThemeConfig,
  ApiWallVideoAudioPolicy,
  ApiWallVideoMultiLayoutPolicy,
  ApiWallVideoPlaybackMode,
  ApiWallVideoPreferredVariant,
  ApiWallVideoResumeMode,
} from '@/lib/api-types';
import { resolveManagerWallLayoutOption } from './manager-config';

export const DEFAULT_WALL_SELECTION_POLICY: ApiWallSelectionPolicy = {
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
};

export function normalizeWallSelectionPolicy(
  policy?: Partial<ApiWallSelectionPolicy> | null,
): ApiWallSelectionPolicy {
  const lowVolumeMaxItems = clampInteger(
    policy?.low_volume_max_items,
    DEFAULT_WALL_SELECTION_POLICY.low_volume_max_items,
    2,
    20,
  );
  const mediumVolumeMaxItems = clampInteger(
    policy?.medium_volume_max_items,
    Math.max(lowVolumeMaxItems + 1, DEFAULT_WALL_SELECTION_POLICY.medium_volume_max_items),
    lowVolumeMaxItems + 1,
    50,
  );

  return {
    max_eligible_items_per_sender: clampInteger(
      policy?.max_eligible_items_per_sender,
      DEFAULT_WALL_SELECTION_POLICY.max_eligible_items_per_sender,
      1,
      12,
    ),
    max_replays_per_item: clampInteger(
      policy?.max_replays_per_item,
      DEFAULT_WALL_SELECTION_POLICY.max_replays_per_item,
      0,
      6,
    ),
    low_volume_max_items: lowVolumeMaxItems,
    medium_volume_max_items: mediumVolumeMaxItems,
    replay_interval_low_minutes: clampInteger(
      policy?.replay_interval_low_minutes,
      DEFAULT_WALL_SELECTION_POLICY.replay_interval_low_minutes,
      1,
      60,
    ),
    replay_interval_medium_minutes: clampInteger(
      policy?.replay_interval_medium_minutes,
      DEFAULT_WALL_SELECTION_POLICY.replay_interval_medium_minutes,
      1,
      60,
    ),
    replay_interval_high_minutes: clampInteger(
      policy?.replay_interval_high_minutes,
      DEFAULT_WALL_SELECTION_POLICY.replay_interval_high_minutes,
      1,
      60,
    ),
    sender_cooldown_seconds: clampInteger(
      policy?.sender_cooldown_seconds,
      DEFAULT_WALL_SELECTION_POLICY.sender_cooldown_seconds,
      0,
      300,
    ),
    sender_window_limit: clampInteger(
      policy?.sender_window_limit,
      DEFAULT_WALL_SELECTION_POLICY.sender_window_limit,
      1,
      12,
    ),
    sender_window_minutes: clampInteger(
      policy?.sender_window_minutes,
      DEFAULT_WALL_SELECTION_POLICY.sender_window_minutes,
      1,
      30,
    ),
    avoid_same_sender_if_alternative_exists:
      policy?.avoid_same_sender_if_alternative_exists
      ?? DEFAULT_WALL_SELECTION_POLICY.avoid_same_sender_if_alternative_exists,
    avoid_same_duplicate_cluster_if_alternative_exists:
      policy?.avoid_same_duplicate_cluster_if_alternative_exists
      ?? DEFAULT_WALL_SELECTION_POLICY.avoid_same_duplicate_cluster_if_alternative_exists,
  };
}

export function normalizeWallThemeConfig(
  config?: Partial<ApiWallThemeConfig> | null,
): ApiWallThemeConfig {
  const normalized: ApiWallThemeConfig = {};

  if (isWallThemePreset(config?.preset)) {
    normalized.preset = config.preset;
  }

  if (isWallThemeAnchorMode(config?.anchor_mode)) {
    normalized.anchor_mode = config.anchor_mode;
  }

  if (isWallThemeBurstIntensity(config?.burst_intensity)) {
    normalized.burst_intensity = config.burst_intensity;
  }

  if (typeof config?.hero_enabled === 'boolean') {
    normalized.hero_enabled = config.hero_enabled;
  }

  if (config?.video_behavior === 'fallback_single_item') {
    normalized.video_behavior = config.video_behavior;
  }

  return normalized;
}

export function cloneWallSettings(settings: ApiWallSettings): ApiWallSettings {
  return {
    ...settings,
    event_phase: settings.event_phase ?? 'flow',
    ad_mode: settings.ad_mode ?? 'disabled',
    ad_frequency: clampInteger(settings.ad_frequency, 5, 1, 100),
    ad_interval_minutes: clampInteger(settings.ad_interval_minutes, 3, 1, 60),
    video_enabled: settings.video_enabled ?? true,
    public_upload_video_enabled: settings.public_upload_video_enabled ?? true,
    private_inbound_video_enabled: settings.private_inbound_video_enabled ?? true,
    video_playback_mode: normalizeVideoPlaybackMode(settings.video_playback_mode),
    video_max_seconds: clampInteger(settings.video_max_seconds, 30, 5, 300),
    video_resume_mode: normalizeVideoResumeMode(settings.video_resume_mode),
    video_audio_policy: normalizeVideoAudioPolicy(settings.video_audio_policy),
    video_multi_layout_policy: normalizeVideoMultiLayoutPolicy(settings.video_multi_layout_policy),
    video_preferred_variant: normalizeVideoPreferredVariant(settings.video_preferred_variant),
    theme_config: normalizeWallThemeConfig(settings.theme_config),
    selection_policy: normalizeWallSelectionPolicy(settings.selection_policy),
  };
}

export function prepareWallSettingsPayload(settings: ApiWallSettings): ApiWallSettings {
  return {
    ...settings,
    event_phase: settings.event_phase ?? 'flow',
    ad_mode: settings.ad_mode ?? 'disabled',
    ad_frequency: clampInteger(settings.ad_frequency, 5, 1, 100),
    ad_interval_minutes: clampInteger(settings.ad_interval_minutes, 3, 1, 60),
    video_enabled: settings.video_enabled ?? true,
    public_upload_video_enabled: settings.public_upload_video_enabled ?? true,
    private_inbound_video_enabled: settings.private_inbound_video_enabled ?? true,
    video_playback_mode: normalizeVideoPlaybackMode(settings.video_playback_mode),
    video_max_seconds: clampInteger(settings.video_max_seconds, 30, 5, 300),
    video_resume_mode: normalizeVideoResumeMode(settings.video_resume_mode),
    video_audio_policy: normalizeVideoAudioPolicy(settings.video_audio_policy),
    video_multi_layout_policy: normalizeVideoMultiLayoutPolicy(settings.video_multi_layout_policy),
    video_preferred_variant: normalizeVideoPreferredVariant(settings.video_preferred_variant),
    theme_config: normalizeWallThemeConfig(settings.theme_config),
    selection_policy: normalizeWallSelectionPolicy(settings.selection_policy),
    neon_text: blankToNull(settings.neon_text),
    instructions_text: blankToNull(settings.instructions_text),
  };
}

export function applyWallLayoutCapabilities(
  settings: ApiWallSettings,
  layoutOption?: ApiWallLayoutOption | null,
): ApiWallSettings {
  const resolvedLayoutOption = layoutOption ?? resolveManagerWallLayoutOption(settings.layout, []);
  const normalizedThemeConfig = resolvedLayoutOption?.capabilities.supports_theme_config
    ? normalizeWallThemeConfig({
      ...resolvedLayoutOption.defaults.theme_config,
      ...settings.theme_config,
    })
    : normalizeWallThemeConfig(settings.theme_config);

  return {
    ...settings,
    show_side_thumbnails: resolvedLayoutOption?.capabilities.supports_side_thumbnails === false
      ? false
      : (settings.show_side_thumbnails ?? true),
    video_multi_layout_policy: resolvedLayoutOption?.value === 'puzzle'
      ? 'disallow'
      : normalizeVideoMultiLayoutPolicy(settings.video_multi_layout_policy),
    theme_config: normalizedThemeConfig,
  };
}

export function resolveManagedWallSettings(
  settings: ApiWallSettings,
  layoutOptions: ApiWallLayoutOption[] = [],
): ApiWallSettings {
  return applyWallLayoutCapabilities(
    cloneWallSettings(settings),
    resolveManagerWallLayoutOption(settings.layout, layoutOptions),
  );
}

export function areWallSettingsEqual(
  left: ApiWallSettings | null | undefined,
  right: ApiWallSettings | null | undefined,
): boolean {
  if (!left || !right) return false;

  const leftSelection = normalizeWallSelectionPolicy(left.selection_policy);
  const rightSelection = normalizeWallSelectionPolicy(right.selection_policy);

  return left.interval_ms === right.interval_ms
    && left.queue_limit === right.queue_limit
    && left.selection_mode === right.selection_mode
    && left.event_phase === right.event_phase
    && leftSelection.max_eligible_items_per_sender === rightSelection.max_eligible_items_per_sender
    && leftSelection.max_replays_per_item === rightSelection.max_replays_per_item
    && leftSelection.low_volume_max_items === rightSelection.low_volume_max_items
    && leftSelection.medium_volume_max_items === rightSelection.medium_volume_max_items
    && leftSelection.replay_interval_low_minutes === rightSelection.replay_interval_low_minutes
    && leftSelection.replay_interval_medium_minutes === rightSelection.replay_interval_medium_minutes
    && leftSelection.replay_interval_high_minutes === rightSelection.replay_interval_high_minutes
    && leftSelection.sender_cooldown_seconds === rightSelection.sender_cooldown_seconds
    && leftSelection.sender_window_limit === rightSelection.sender_window_limit
    && leftSelection.sender_window_minutes === rightSelection.sender_window_minutes
    && leftSelection.avoid_same_sender_if_alternative_exists === rightSelection.avoid_same_sender_if_alternative_exists
    && leftSelection.avoid_same_duplicate_cluster_if_alternative_exists === rightSelection.avoid_same_duplicate_cluster_if_alternative_exists
    && left.layout === right.layout
    && left.transition_effect === right.transition_effect
    && left.background_url === right.background_url
    && left.partner_logo_url === right.partner_logo_url
    && left.show_qr === right.show_qr
    && left.show_branding === right.show_branding
    && left.show_neon === right.show_neon
    && blankToNull(left.neon_text) === blankToNull(right.neon_text)
    && blankToNull(left.neon_color) === blankToNull(right.neon_color)
    && left.show_sender_credit === right.show_sender_credit
    && (left.show_side_thumbnails ?? true) === (right.show_side_thumbnails ?? true)
    && (left.accepted_orientation ?? 'all') === (right.accepted_orientation ?? 'all')
    && (left.ad_mode ?? 'disabled') === (right.ad_mode ?? 'disabled')
    && clampInteger(left.ad_frequency, 5, 1, 100) === clampInteger(right.ad_frequency, 5, 1, 100)
    && clampInteger(left.ad_interval_minutes, 3, 1, 60) === clampInteger(right.ad_interval_minutes, 3, 1, 60)
    && (left.video_enabled ?? true) === (right.video_enabled ?? true)
    && (left.public_upload_video_enabled ?? true) === (right.public_upload_video_enabled ?? true)
    && (left.private_inbound_video_enabled ?? true) === (right.private_inbound_video_enabled ?? true)
    && normalizeVideoPlaybackMode(left.video_playback_mode) === normalizeVideoPlaybackMode(right.video_playback_mode)
    && clampInteger(left.video_max_seconds, 30, 5, 300) === clampInteger(right.video_max_seconds, 30, 5, 300)
    && normalizeVideoResumeMode(left.video_resume_mode) === normalizeVideoResumeMode(right.video_resume_mode)
    && normalizeVideoAudioPolicy(left.video_audio_policy) === normalizeVideoAudioPolicy(right.video_audio_policy)
    && normalizeVideoMultiLayoutPolicy(left.video_multi_layout_policy) === normalizeVideoMultiLayoutPolicy(right.video_multi_layout_policy)
    && normalizeVideoPreferredVariant(left.video_preferred_variant) === normalizeVideoPreferredVariant(right.video_preferred_variant)
    && serializeWallThemeConfig(left.theme_config) === serializeWallThemeConfig(right.theme_config)
    && blankToNull(left.instructions_text) === blankToNull(right.instructions_text);
}

export function applyWallSelectionPreset(
  settings: ApiWallSettings,
  preset?: ApiWallSelectionModeOption | null,
): ApiWallSettings {
  if (!preset) {
    return settings;
  }

  return {
    ...settings,
    selection_mode: preset.value,
    selection_policy: normalizeWallSelectionPolicy(preset.selection_policy),
  };
}

export function markWallSelectionAsCustom(
  settings: ApiWallSettings,
  policy: Partial<ApiWallSelectionPolicy>,
): ApiWallSettings {
  return {
    ...settings,
    selection_mode: 'custom',
    selection_policy: normalizeWallSelectionPolicy({
      ...settings.selection_policy,
      ...policy,
    }),
  };
}

export function buildWallSelectionSummary(
  settings: Pick<ApiWallSettings, 'selection_mode' | 'selection_policy' | 'event_phase'>,
  presets: ApiWallSelectionModeOption[] = [],
): string {
  const policy = normalizeWallSelectionPolicy(settings.selection_policy);
  const preset = resolveWallSelectionModeOption(settings.selection_mode, presets);
  const modeLabel = preset?.label ?? labelForMode(settings.selection_mode);
  const phaseLabel = labelForPhase(settings.event_phase ?? 'flow');
  const phaseImpact = describePhaseImpact(settings.event_phase ?? 'flow');

  return [
    `${modeLabel} em ${phaseLabel}: ate ${policy.max_eligible_items_per_sender} midias por pessoa entram de cada vez na disputa pelo telao.`,
    `Cada foto ou video pode voltar no maximo ${policy.max_replays_per_item} vez(es) antes de o sistema abrir espaco para novas reprises.`,
    `Tempo minimo para repetir com fila curta/media/cheia: ${policy.replay_interval_low_minutes}/${policy.replay_interval_medium_minutes}/${policy.replay_interval_high_minutes} min.`,
    `Faixas de volume: ate ${policy.low_volume_max_items} itens para fila baixa e ate ${policy.medium_volume_max_items} para fila media.`,
    `Espera minima de ${policy.sender_cooldown_seconds}s e limite de ${policy.sender_window_limit} aparicoes por ${policy.sender_window_minutes} min.`,
    phaseImpact,
    policy.avoid_same_sender_if_alternative_exists
      ? 'Evita repetir o mesmo remetente quando houver alternativa.'
      : 'Permite repetir o mesmo remetente com mais liberdade.',
  ].join(' ');
}

export function resolveWallSelectionModeOption(
  mode: ApiWallSelectionMode,
  presets: ApiWallSelectionModeOption[] = [],
): ApiWallSelectionModeOption | null {
  return presets.find((preset) => preset.value === mode) ?? null;
}

export function buildWallVideoPolicySummary(
  settings: Pick<ApiWallSettings, 'video_enabled' | 'public_upload_video_enabled' | 'private_inbound_video_enabled' | 'video_playback_mode' | 'video_max_seconds' | 'video_resume_mode' | 'video_audio_policy' | 'video_multi_layout_policy' | 'video_preferred_variant'>,
): string {
  if (!settings.video_enabled) {
    return 'Videos estao bloqueados neste telao. O wall segue aceitando apenas imagens na exibicao.';
  }

  const playbackMode = describePlaybackMode(settings.video_playback_mode, settings.video_max_seconds);
  const resumeMode = describeResumeMode(settings.video_resume_mode);
  const layoutPolicy = describeMultiLayoutPolicy(settings.video_multi_layout_policy);
  const variantPolicy = describePreferredVariant(settings.video_preferred_variant);

  return [
    playbackMode,
    `${resumeMode}.`,
    `Audio ${settings.video_audio_policy === 'muted' ? 'sempre mudo' : 'com politica personalizada'}.`,
    `${layoutPolicy}.`,
    `${variantPolicy}.`,
    `Canal publico ${settings.public_upload_video_enabled ?? true ? 'liberado para 1 video curto por envio' : 'mantido apenas com fotos'}.`,
    `Canal privado ${settings.private_inbound_video_enabled ?? true ? 'segue gerando variantes do wall quando o tooling estiver pronto' : 'fica no fluxo legado sem rollout oficial de video'}.`,
  ].join(' ');
}

function labelForMode(mode: ApiWallSelectionMode): string {
  switch (mode) {
    case 'live':
      return 'Ao vivo';
    case 'inclusive':
      return 'Inclusivo';
    case 'editorial':
      return 'Editorial';
    case 'custom':
      return 'Personalizado';
    case 'balanced':
    default:
      return 'Equilibrado';
  }
}

function labelForPhase(phase: ApiWallEventPhase): string {
  switch (phase) {
    case 'reception':
      return 'Recepcao';
    case 'party':
      return 'Festa';
    case 'closing':
      return 'Encerramento';
    case 'flow':
    default:
      return 'Fluxo';
  }
}

function describePhaseImpact(phase: ApiWallEventPhase): string {
  switch (phase) {
    case 'reception':
      return 'A fase de recepcao segura um pouco as repeticoes para distribuir melhor as primeiras fotos.';
    case 'party':
      return 'A fase de festa acelera o ritmo e flexibiliza as repeticoes para acompanhar o pico da noite.';
    case 'closing':
      return 'A fase de encerramento desacelera a troca e abre mais espaco para reprises finais.';
    case 'flow':
    default:
      return 'A fase de fluxo mantem o comportamento padrao do modo escolhido.';
  }
}

function normalizeVideoPlaybackMode(value?: ApiWallVideoPlaybackMode | string | null): ApiWallVideoPlaybackMode {
  if (value === 'fixed_interval' || value === 'play_to_end' || value === 'play_to_end_if_short_else_cap') {
    return value;
  }

  return 'play_to_end_if_short_else_cap';
}

function normalizeVideoResumeMode(value?: ApiWallVideoResumeMode | string | null): ApiWallVideoResumeMode {
  if (value === 'resume_if_same_item' || value === 'restart_from_zero' || value === 'resume_if_same_item_else_restart') {
    return value;
  }

  return 'resume_if_same_item_else_restart';
}

function normalizeVideoAudioPolicy(value?: ApiWallVideoAudioPolicy | string | null): ApiWallVideoAudioPolicy {
  return value === 'muted' ? value : 'muted';
}

function normalizeVideoMultiLayoutPolicy(value?: ApiWallVideoMultiLayoutPolicy | string | null): ApiWallVideoMultiLayoutPolicy {
  if (value === 'disallow' || value === 'one' || value === 'all') {
    return value;
  }

  return 'disallow';
}

function normalizeVideoPreferredVariant(value?: ApiWallVideoPreferredVariant | string | null): ApiWallVideoPreferredVariant {
  if (value === 'wall_video_720p' || value === 'wall_video_1080p' || value === 'original') {
    return value;
  }

  return 'wall_video_720p';
}

function isWallThemePreset(value: unknown): value is ApiWallThemeConfig['preset'] {
  return value === 'compact' || value === 'standard';
}

function isWallThemeAnchorMode(value: unknown): value is ApiWallThemeConfig['anchor_mode'] {
  return value === 'event_brand' || value === 'qr_prompt' || value === 'none';
}

function isWallThemeBurstIntensity(value: unknown): value is ApiWallThemeConfig['burst_intensity'] {
  return value === 'gentle' || value === 'normal';
}

function serializeWallThemeConfig(config?: Partial<ApiWallThemeConfig> | null): string {
  const normalized = normalizeWallThemeConfig(config);
  const sorted = Object.keys(normalized)
    .sort()
    .reduce<Record<string, unknown>>((acc, key) => {
      acc[key] = normalized[key as keyof ApiWallThemeConfig];

      return acc;
    }, {});

  return JSON.stringify(sorted);
}

function describePlaybackMode(mode: ApiWallVideoPlaybackMode | string | null | undefined, maxSeconds: number): string {
  switch (normalizeVideoPlaybackMode(mode)) {
    case 'fixed_interval':
      return 'Videos seguem o mesmo tempo fixo do slide.';
    case 'play_to_end':
      return 'Videos tocam ate o fim natural.';
    case 'play_to_end_if_short_else_cap':
    default:
      return `Videos curtos tocam ate o fim; acima disso o wall limita a ${maxSeconds}s.`;
  }
}

function describeResumeMode(mode: ApiWallVideoResumeMode | string | null | undefined): string {
  switch (normalizeVideoResumeMode(mode)) {
    case 'resume_if_same_item':
      return 'Ao pausar, o wall tenta retomar do ponto atual se o mesmo video continuar em foco';
    case 'restart_from_zero':
      return 'Ao pausar, o wall reinicia o video desde o comeco na retomada';
    case 'resume_if_same_item_else_restart':
    default:
      return 'Ao pausar, o wall retoma do ponto atual se o mesmo video permanecer em foco e reinicia quando a fila mudar';
  }
}

function describeMultiLayoutPolicy(mode: ApiWallVideoMultiLayoutPolicy | string | null | undefined): string {
  switch (normalizeVideoMultiLayoutPolicy(mode)) {
    case 'all':
      return 'Layouts com varios slots podem receber videos quando a composicao permitir';
    case 'one':
      return 'Layouts com varios slots aceitam no maximo um video por vez';
    case 'disallow':
    default:
      return 'Layouts com varios slots nao recebem video e o wall cai para exibicao single-item';
  }
}

function describePreferredVariant(value: ApiWallVideoPreferredVariant | string | null | undefined): string {
  switch (normalizeVideoPreferredVariant(value)) {
    case 'wall_video_1080p':
      return 'O wall prioriza a variante 1080p quando ela existir';
    case 'original':
      return 'O wall pode usar o arquivo original quando o runtime precisar';
    case 'wall_video_720p':
    default:
      return 'O wall prioriza a variante 720p para manter startup e decode mais leves';
  }
}

function clampInteger(value: number | undefined, fallback: number, min: number, max: number): number {
  if (!Number.isFinite(value)) {
    return fallback;
  }

  return Math.max(min, Math.min(max, Math.trunc(value as number)));
}

function blankToNull(value?: string | null): string | null {
  const trimmed = value?.trim() ?? '';
  return trimmed === '' ? null : trimmed;
}
