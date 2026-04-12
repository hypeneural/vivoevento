import type { ApiWallDiagnosticsPlayer, ApiWallPersistentStorage } from '@/lib/api-types';

export function shortPlayerId(playerInstanceId: string) {
  return playerInstanceId.length <= 12
    ? playerInstanceId
    : `${playerInstanceId.slice(0, 8)}...${playerInstanceId.slice(-4)}`;
}

export function formatPercentLabel(value?: number | null) {
  if (value == null) {
    return 'Sem dado';
  }

  return `${Math.max(0, Math.round(value))}%`;
}

export function formatBytes(value?: number | null) {
  if (value == null || value <= 0) {
    return 'Sem dado';
  }

  if (value < 1024) {
    return `${value} B`;
  }

  const units = ['KB', 'MB', 'GB', 'TB'];
  let size = value / 1024;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex += 1;
  }

  const rounded = size >= 100 ? Math.round(size) : Math.round(size * 10) / 10;
  return `${rounded} ${units[unitIndex]}`;
}

export function formatTimestampLabel(value?: string | null) {
  if (!value) {
    return 'Sem sinal';
  }

  try {
    return new Date(value).toLocaleTimeString('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return 'Sem sinal';
  }
}

export function formatPlayerHealthLabel(value: ApiWallDiagnosticsPlayer['health_status']) {
  if (value === 'healthy') return 'Saudavel';
  if (value === 'degraded') return 'Com instabilidade';
  return 'Sem conexao';
}

export function formatRuntimeStatus(value?: string | null) {
  switch (value) {
    case 'booting':
      return 'Iniciando';
    case 'loading':
      return 'Carregando';
    case 'playing':
      return 'Exibindo';
    case 'paused':
      return 'Pausado';
    case 'error':
      return 'Com erro';
    case 'idle':
      return 'Aguardando';
    default:
      return formatLooseLabel(value, 'Sem dado');
  }
}

export function formatConnectionStatus(value?: string | null) {
  switch (value) {
    case 'connected':
      return 'Conectado';
    case 'reconnecting':
      return 'Reconectando';
    case 'disconnected':
      return 'Desconectado';
    case 'offline':
      return 'Offline';
    default:
      return formatLooseLabel(value, 'Sem dado');
  }
}

export function formatFallbackReason(value?: string | null) {
  return formatLooseLabel(value, 'Sem detalhe');
}

export function formatTransitionEffect(value?: ApiWallDiagnosticsPlayer['active_transition_effect']) {
  return formatLooseLabel(value, 'Sem transicao ativa');
}

export function formatTransitionMode(value?: ApiWallDiagnosticsPlayer['transition_mode']) {
  switch (value) {
    case 'random':
      return 'rand';
    case 'fixed':
      return 'fixo';
    default:
      return 'fixo';
  }
}

export function formatTransitionFallbackReason(
  value?: ApiWallDiagnosticsPlayer['transition_last_fallback_reason'],
) {
  switch (value) {
    case 'reduced_motion':
      return 'motion reduzido';
    case 'capability_tier':
      return 'tier de capacidade';
    case 'effect_unavailable':
      return 'efeito indisponivel';
    default:
      return 'fallback seguro';
  }
}

export function formatVideoPhase(value?: ApiWallDiagnosticsPlayer['current_video_phase']) {
  return formatLooseLabel(value, 'Sem fase');
}

export function formatVideoExitReason(value?: ApiWallDiagnosticsPlayer['current_video_exit_reason']) {
  return formatLooseLabel(value, 'Sem saida registrada');
}

export function formatVideoFailureReason(value?: ApiWallDiagnosticsPlayer['current_video_failure_reason']) {
  return formatLooseLabel(value, 'Sem falha registrada');
}

export function formatVideoProgress(player: ApiWallDiagnosticsPlayer) {
  const current = player.current_video_position_seconds;
  const duration = player.current_video_duration_seconds;

  if (current == null && duration == null) {
    return 'Sem progresso de video';
  }

  const currentLabel = current == null ? '--' : `${Math.max(0, Math.round(current))}s`;
  const durationLabel = duration == null ? '--' : `${Math.max(0, Math.round(duration))}s`;

  return `${currentLabel} de ${durationLabel}`;
}

export function formatVideoRuntimeSummary(player: ApiWallDiagnosticsPlayer) {
  if (player.current_media_type !== 'video') {
    return 'A tela nao esta com video em foco agora.';
  }

  const flags = [
    player.current_video_poster_visible ? 'poster visivel' : null,
    player.current_video_first_frame_ready ? 'primeira frame pronta' : null,
    player.current_video_playback_ready ? 'playback pronto' : null,
    player.current_video_playing_confirmed ? 'tocando de verdade' : null,
    player.current_video_startup_degraded ? 'startup degradado' : null,
  ].filter(Boolean);

  return [
    `Fase ${formatVideoPhase(player.current_video_phase).toLowerCase()}.`,
    `Progresso ${formatVideoProgress(player)}.`,
    flags.length > 0 ? `Sinais: ${flags.join(', ')}.` : null,
  ].filter(Boolean).join(' ');
}

export function formatPersistentStorage(value: ApiWallPersistentStorage) {
  switch (value) {
    case 'localstorage':
      return 'Memoria do navegador';
    case 'indexeddb':
      return 'Banco local do navegador';
    case 'cache_api':
      return 'Cache do navegador';
    case 'unavailable':
      return 'Indisponivel';
    case 'unknown':
      return 'Desconhecida';
    default:
      return 'Nenhuma';
  }
}

export function formatDeviceProfile(player: ApiWallDiagnosticsPlayer) {
  const parts = [
    player.hardware_concurrency ? `${player.hardware_concurrency} threads` : null,
    player.device_memory_gb ? `${player.device_memory_gb} GB` : null,
  ].filter(Boolean);

  if (parts.length === 0) {
    return 'Sem leitura de hardware';
  }

  return parts.join(' | ');
}

export function formatDeviceProfileDetail(player: ApiWallDiagnosticsPlayer) {
  if (player.prefers_reduced_motion == null && !player.document_visibility_state) {
    return 'O navegador nao expôs preferencia de movimento nem visibilidade.';
  }

  return [
    player.prefers_reduced_motion == null
      ? null
      : player.prefers_reduced_motion
        ? 'Reduced motion ativo'
        : 'Reduced motion desligado',
    player.document_visibility_state
      ? `Documento ${player.document_visibility_state}`
      : null,
  ].filter(Boolean).join(' | ');
}

export function formatNetworkProfile(player: ApiWallDiagnosticsPlayer) {
  if (!player.network_effective_type) {
    return 'Sem leitura de rede';
  }

  return player.network_effective_type.toUpperCase();
}

export function formatNetworkProfileDetail(player: ApiWallDiagnosticsPlayer) {
  const parts = [
    player.network_downlink_mbps != null ? `${player.network_downlink_mbps} Mbps` : null,
    player.network_rtt_ms != null ? `RTT ${player.network_rtt_ms}ms` : null,
    player.network_save_data == null
      ? null
      : player.network_save_data
        ? 'Economia de dados ativa'
        : 'Sem economia de dados',
  ].filter(Boolean);

  return parts.join(' | ') || 'Sem detalhe de rede';
}

export function formatCurrentSender(value?: string | null) {
  if (!value) {
    return 'Nenhum convidado em foco agora';
  }

  const normalized = value.toLowerCase();

  if (normalized.startsWith('whatsapp')) {
    return 'Convidado via WhatsApp';
  }

  if (normalized.startsWith('telegram')) {
    return 'Convidado via Telegram';
  }

  if (normalized.startsWith('upload')) {
    return 'Convidado por upload';
  }

  if (normalized.startsWith('manual')) {
    return 'Midia escolhida pela equipe';
  }

  if (normalized.startsWith('gallery')) {
    return 'Midia da galeria';
  }

  if (normalized.startsWith('user')) {
    return 'Equipe do evento';
  }

  return 'Convidado identificado';
}

export function formatOperationalGuidance(player: ApiWallDiagnosticsPlayer) {
  if (player.current_media_type === 'video' && player.current_video_failure_reason) {
    return `A tela falhou no playback do video por ${formatVideoFailureReason(player.current_video_failure_reason).toLowerCase()}. Vale revisar variante, rede ou compatibilidade.`;
  }

  if (player.current_media_type === 'video' && player.current_video_phase === 'stalled') {
    return 'O video em foco estagnou esperando dados. Se isso se repetir, vale revisar a variante servida ou limpar o cache local.';
  }

  if (player.current_media_type === 'video' && player.current_video_phase === 'waiting') {
    return 'O video em foco ainda responde, mas esta aguardando buffer. Observe se a tela sai sozinha do waiting ou entra em stall.';
  }

  if (player.transition_last_fallback_reason === 'reduced_motion') {
    return 'A tela esta rodando com motion reduzido. As trocas entram em fallback seguro enquanto essa preferencia estiver ativa.';
  }

  if (player.transition_last_fallback_reason === 'capability_tier') {
    return 'A tela caiu para transicao segura por limite de capacidade do runtime. Vale acompanhar hardware, budget e tier efetivo.';
  }

  if (player.transition_last_fallback_reason === 'effect_unavailable') {
    return 'A tela recebeu um efeito que nao existe mais no registry atual e caiu para a transicao segura.';
  }

  if (player.health_status === 'healthy') {
    return 'Tudo esta estavel nesta tela agora.';
  }

  if (player.health_status === 'offline') {
    return 'Essa tela parou de responder. Vale revisar rede, energia ou reabrir o player.';
  }

  if (player.connection_status === 'reconnecting') {
    return 'A tela segue ativa, mas esta tentando recuperar a conexao.';
  }

  if (player.error_count > 0 || player.stale_count > 0) {
    return 'A tela ainda responde, mas pode precisar atualizar fotos ou limpar o cache.';
  }

  return 'Essa tela esta com instabilidade e merece acompanhamento mais de perto.';
}

export function formatPlayerSituation(player: ApiWallDiagnosticsPlayer) {
  if (player.health_status === 'offline') {
    return 'Sem resposta';
  }

  if (player.current_media_type === 'video' && player.runtime_status === 'playing') {
    if (player.current_video_phase === 'playing') {
      return 'Video tocando';
    }

    if (player.current_video_phase === 'waiting' || player.current_video_phase === 'stalled') {
      return 'Video com buffer';
    }

    if (player.current_video_phase === 'starting' || player.current_video_phase === 'primed') {
      return 'Video iniciando';
    }
  }

  if (player.runtime_status === 'playing' && player.connection_status === 'connected') {
    return 'Exibindo sem problemas';
  }

  if (player.runtime_status === 'paused') {
    return 'Parada na pausa';
  }

  if (player.connection_status === 'reconnecting') {
    return 'Tentando voltar';
  }

  return formatRuntimeStatus(player.runtime_status);
}

export function playerCardClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'border-emerald-500/30 bg-emerald-500/8';
    case 'degraded':
      return 'border-amber-500/30 bg-amber-500/8';
    default:
      return 'border-rose-500/30 bg-rose-500/8';
  }
}

export function playerStatusDotClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'bg-emerald-500 shadow-[0_0_18px_rgba(16,185,129,0.65)]';
    case 'degraded':
      return 'bg-amber-500 shadow-[0_0_18px_rgba(245,158,11,0.55)]';
    default:
      return 'bg-rose-500 shadow-[0_0_18px_rgba(244,63,94,0.5)]';
  }
}

export function playerTitleClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'text-emerald-950';
    case 'degraded':
      return 'text-amber-950';
    default:
      return 'text-rose-950';
  }
}

export function playerSignalClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'text-emerald-800/90';
    case 'degraded':
      return 'text-amber-800/90';
    default:
      return 'text-rose-800/90';
  }
}

export function healthBadgeClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700';
    case 'degraded':
      return 'border-amber-500/30 bg-amber-500/10 text-amber-700';
    default:
      return 'border-rose-500/30 bg-rose-500/10 text-rose-700';
  }
}

function formatLooseLabel(value?: string | null, fallback = 'Sem dado') {
  if (!value) {
    return fallback;
  }

  const normalized = value.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();

  if (!normalized) {
    return fallback;
  }

  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}
