import type { ApiWallDiagnosticsPlayer, ApiWallPersistentStorage } from '@/lib/api-types';

export function WallPlayerRuntimeCard({ player }: { player: ApiWallDiagnosticsPlayer }) {
  return (
    <div
      data-health-status={player.health_status}
      className={`rounded-2xl border p-4 shadow-sm ${playerCardClass(player.health_status)}`}
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <span className={`h-2.5 w-2.5 rounded-full ${playerStatusDotClass(player.health_status)}`} />
            <p className={`text-sm font-semibold ${playerTitleClass(player.health_status)}`}>
              Tela {shortPlayerId(player.player_instance_id)}
            </p>
          </div>
          <p className={`text-xs ${playerSignalClass(player.health_status)}`}>
            Ultimo sinal {formatTimestampLabel(player.last_seen_at)}
          </p>
        </div>
        <span className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-medium ${healthBadgeClass(player.health_status)}`}>
          {formatPlayerHealthLabel(player.health_status)}
        </span>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <RuntimeStat label="Exibicao" value={formatRuntimeStatus(player.runtime_status)} />
        <RuntimeStat label="Conexao" value={formatConnectionStatus(player.connection_status)} />
        <RuntimeStat label="Remetente atual" value={formatCurrentSender(player.current_sender_key)} />
        <RuntimeStat
          label="Midias carregadas"
          value={`${player.ready_count} prontas | ${player.loading_count} carregando | ${player.error_count} com erro | ${player.stale_count} em cache`}
        />
        <RuntimeStat label="Aproveitamento do cache" value={formatPercentLabel(player.cache_hit_rate)} />
        <RuntimeStat
          label="Espaco no navegador"
          value={`${formatBytes(player.cache_usage_bytes)} / ${formatBytes(player.cache_quota_bytes)}`}
        />
      </div>

      <div className="mt-4 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Cache {player.cache_enabled ? 'ativo' : 'desligado'}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Armazenamento {formatPersistentStorage(player.persistent_storage)}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Acertos {player.cache_hit_count} | Falhas {player.cache_miss_count} | Desatualizados {player.cache_stale_fallback_count}
        </span>
        {player.last_sync_at ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Ultima atualizacao {formatTimestampLabel(player.last_sync_at)}
          </span>
        ) : null}
        {player.last_fallback_reason ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Motivo da troca {formatFallbackReason(player.last_fallback_reason)}
          </span>
        ) : null}
      </div>
    </div>
  );
}

function RuntimeStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-border/50 bg-background/70 p-3">
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-1 text-sm font-medium">{value}</p>
    </div>
  );
}

function shortPlayerId(playerInstanceId: string) {
  return playerInstanceId.length <= 12
    ? playerInstanceId
    : `${playerInstanceId.slice(0, 8)}...${playerInstanceId.slice(-4)}`;
}

function formatPercentLabel(value?: number | null) {
  if (value == null) {
    return 'Sem dado';
  }

  return `${Math.max(0, Math.round(value))}%`;
}

function formatBytes(value?: number | null) {
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

function formatTimestampLabel(value?: string | null) {
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

function formatPlayerHealthLabel(value: ApiWallDiagnosticsPlayer['health_status']) {
  if (value === 'healthy') return 'Saudavel';
  if (value === 'degraded') return 'Com instabilidade';
  return 'Sem conexao';
}

function formatRuntimeStatus(value?: string | null) {
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

function formatConnectionStatus(value?: string | null) {
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

function formatFallbackReason(value?: string | null) {
  return formatLooseLabel(value, 'Sem detalhe');
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

function formatPersistentStorage(value: ApiWallPersistentStorage) {
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

function formatCurrentSender(value?: string | null) {
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

function playerCardClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'border-emerald-500/30 bg-emerald-500/8';
    case 'degraded':
      return 'border-amber-500/30 bg-amber-500/8';
    default:
      return 'border-rose-500/30 bg-rose-500/8';
  }
}

function playerStatusDotClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'bg-emerald-500 shadow-[0_0_18px_rgba(16,185,129,0.65)]';
    case 'degraded':
      return 'bg-amber-500 shadow-[0_0_18px_rgba(245,158,11,0.55)]';
    default:
      return 'bg-rose-500 shadow-[0_0_18px_rgba(244,63,94,0.5)]';
  }
}

function playerTitleClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'text-emerald-950';
    case 'degraded':
      return 'text-amber-950';
    default:
      return 'text-rose-950';
  }
}

function playerSignalClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'text-emerald-800/90';
    case 'degraded':
      return 'text-amber-800/90';
    default:
      return 'text-rose-800/90';
  }
}

function healthBadgeClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700';
    case 'degraded':
      return 'border-amber-500/30 bg-amber-500/10 text-amber-700';
    default:
      return 'border-rose-500/30 bg-rose-500/10 text-rose-700';
  }
}
