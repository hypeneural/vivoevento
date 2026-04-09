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
