import type {
  ApiWallEventPhase,
  ApiWallSelectionMode,
  ApiWallSelectionModeOption,
  ApiWallSelectionPolicy,
  ApiWallSettings,
} from '@/lib/api-types';

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

export function cloneWallSettings(settings: ApiWallSettings): ApiWallSettings {
  return {
    ...settings,
    event_phase: settings.event_phase ?? 'flow',
    selection_policy: normalizeWallSelectionPolicy(settings.selection_policy),
  };
}

export function prepareWallSettingsPayload(settings: ApiWallSettings): ApiWallSettings {
  return {
    ...settings,
    event_phase: settings.event_phase ?? 'flow',
    selection_policy: normalizeWallSelectionPolicy(settings.selection_policy),
    neon_text: blankToNull(settings.neon_text),
    instructions_text: blankToNull(settings.instructions_text),
  };
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
    `${modeLabel} em ${phaseLabel}: ate ${policy.max_eligible_items_per_sender} midias elegiveis por remetente antes do backlog gradual.`,
    `Cada item tenta no maximo ${policy.max_replays_per_item} replay(s) antes do fallback de continuidade.`,
    `Replay adaptativo em fila baixa/media/alta: ${policy.replay_interval_low_minutes}/${policy.replay_interval_medium_minutes}/${policy.replay_interval_high_minutes} min.`,
    `Faixas de volume: ate ${policy.low_volume_max_items} itens para fila baixa e ate ${policy.medium_volume_max_items} para fila media.`,
    `Cooldown de ${policy.sender_cooldown_seconds}s e limite de ${policy.sender_window_limit} por ${policy.sender_window_minutes} min.`,
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
      return 'A fase de recepcao endurece fairness e segura um pouco o replay para o inicio da festa.';
    case 'party':
      return 'A fase de festa acelera o ritmo efetivo e flexibiliza replay e cooldown para acompanhar o pico da noite.';
    case 'closing':
      return 'A fase de encerramento desacelera a troca e abre mais espaco para reprises finais.';
    case 'flow':
    default:
      return 'A fase de fluxo mantem o comportamento base do modo escolhido.';
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
