import type { ApiWallDiagnosticsPlayer } from '@/lib/api-types';

import { Button } from '@/components/ui/button';

import {
  formatBytes,
  formatConnectionStatus,
  formatCurrentSender,
  formatOperationalGuidance,
  formatPercentLabel,
  formatPlayerHealthLabel,
  formatPlayerSituation,
  formatTimestampLabel,
  formatVideoExitReason,
  formatVideoFailureReason,
  formatVideoPhase,
  formatVideoProgress,
  healthBadgeClass,
  playerCardClass,
  playerSignalClass,
  playerStatusDotClass,
  playerTitleClass,
  shortPlayerId,
} from './wall-player-runtime-copy';

function formatBoardDowngradeReason(reason?: ApiWallDiagnosticsPlayer['board_budget_downgrade_reason']) {
  switch (reason) {
    case 'small_stage':
      return 'palco reduzido';
    case 'safe_area_pressure':
      return 'safe area pressionada';
    case 'runtime_budget':
      return 'budget do runtime';
    default:
      return 'sem downgrade';
  }
}

export function WallPlayerRuntimeCard({
  player,
  onOpenDetails,
}: {
  player: ApiWallDiagnosticsPlayer;
  onOpenDetails?: (player: ApiWallDiagnosticsPlayer) => void;
}) {
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
          <p className="text-sm text-foreground/85">
            {formatOperationalGuidance(player)}
          </p>
        </div>
        <div className="flex flex-col items-start gap-2 sm:items-end">
          <span className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-medium ${healthBadgeClass(player.health_status)}`}>
            {formatPlayerHealthLabel(player.health_status)}
          </span>
          {onOpenDetails ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-8 px-2 text-xs"
              aria-label={`Ver detalhe da tela ${shortPlayerId(player.player_instance_id)}`}
              onClick={() => onOpenDetails(player)}
            >
              Ver detalhe
            </Button>
          ) : null}
        </div>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <RuntimeStat label="Situacao atual" value={formatPlayerSituation(player)} />
        <RuntimeStat label="Conexao agora" value={formatConnectionStatus(player.connection_status)} />
        <RuntimeStat label="Quem esta na tela" value={formatCurrentSender(player.current_sender_key)} />
        <RuntimeStat
          label={player.current_media_type === 'video' ? 'Playback do video' : 'Fila pronta'}
          value={player.current_media_type === 'video'
            ? `${formatVideoPhase(player.current_video_phase)} | ${formatVideoProgress(player)}`
            : `${player.ready_count} prontas | ${player.loading_count} carregando`}
        />
      </div>

      <div className="mt-4 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Cache {player.cache_enabled ? 'ativo' : 'desligado'} | {formatPercentLabel(player.cache_hit_rate)}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Memoria {formatBytes(player.cache_usage_bytes)} de {formatBytes(player.cache_quota_bytes)}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Erros {player.error_count} | Em cache {player.stale_count}
        </span>
        {player.board_piece_count ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Board {player.board_piece_count} pecas
          </span>
        ) : null}
        {player.board_piece_count ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Bursts {player.board_burst_count ?? 0}
          </span>
        ) : null}
        {player.board_piece_count ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Backlog decode {player.decode_backlog_count ?? 0}
          </span>
        ) : null}
        {player.board_piece_count ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Reset board {player.board_reset_count ?? 0}
          </span>
        ) : null}
        {player.board_piece_count && player.board_budget_downgrade_reason ? (
          <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-amber-700">
            Downgrade {formatBoardDowngradeReason(player.board_budget_downgrade_reason)}
            {player.board_budget_downgrade_count ? ` | ${player.board_budget_downgrade_count}x` : ''}
          </span>
        ) : null}
        {player.last_sync_at ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Ultima atualizacao {formatTimestampLabel(player.last_sync_at)}
          </span>
        ) : null}
        {player.current_media_type === 'video' ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Exit reason {formatVideoExitReason(player.current_video_exit_reason)}
          </span>
        ) : null}
        {player.current_media_type === 'video' && player.current_video_failure_reason ? (
          <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-amber-700">
            Falha {formatVideoFailureReason(player.current_video_failure_reason)}
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
