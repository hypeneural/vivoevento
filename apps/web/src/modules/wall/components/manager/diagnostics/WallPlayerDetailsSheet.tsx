import type { ApiWallDiagnosticsPlayer } from '@/lib/api-types';

import { Drawer, DrawerContent, DrawerDescription, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';

import {
  formatBytes,
  formatConnectionStatus,
  formatCurrentSender,
  formatDeviceProfile,
  formatDeviceProfileDetail,
  formatFallbackReason,
  formatNetworkProfile,
  formatNetworkProfileDetail,
  formatOperationalGuidance,
  formatPercentLabel,
  formatPersistentStorage,
  formatPlayerHealthLabel,
  formatPlayerSituation,
  formatTimestampLabel,
  formatVideoExitReason,
  formatVideoFailureReason,
  formatVideoPhase,
  formatVideoProgress,
  formatVideoRuntimeSummary,
  healthBadgeClass,
  shortPlayerId,
} from './wall-player-runtime-copy';

interface WallPlayerDetailsSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  player: ApiWallDiagnosticsPlayer | null;
}

function DetailBlock({ label, value, detail }: { label: string; value: string; detail?: string }) {
  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 px-4 py-3">
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-1 text-sm font-semibold text-foreground">{value}</p>
      {detail ? (
        <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{detail}</p>
      ) : null}
    </div>
  );
}

function WallPlayerDetailsBody({ player }: { player: ApiWallDiagnosticsPlayer | null }) {
  if (!player) {
    return null;
  }

  const readinessSummary = `${player.ready_count} prontas | ${player.loading_count} carregando | ${player.error_count} com erro | ${player.stale_count} em cache`;

  return (
    <div className="mt-4 space-y-4">
      <div className="rounded-3xl border border-border/60 bg-muted/20 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="space-y-1">
            <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Tela {shortPlayerId(player.player_instance_id)}</p>
            <h3 className="text-lg font-semibold">{formatPlayerSituation(player)}</h3>
            <p className="text-sm text-muted-foreground">{formatOperationalGuidance(player)}</p>
          </div>
          <span className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-medium ${healthBadgeClass(player.health_status)}`}>
            {formatPlayerHealthLabel(player.health_status)}
          </span>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <DetailBlock label="Situacao atual" value={formatPlayerSituation(player)} detail={`Ultimo sinal ${formatTimestampLabel(player.last_seen_at)}`} />
        <DetailBlock label="Conexao agora" value={formatConnectionStatus(player.connection_status)} detail={player.last_sync_at ? `Ultima sincronizacao ${formatTimestampLabel(player.last_sync_at)}` : 'Sem sincronizacao recente'} />
        <DetailBlock label="Quem esta na tela" value={formatCurrentSender(player.current_sender_key)} detail={player.runtime_status === 'playing' ? 'Essa informacao ajuda a confirmar se a tela esta no ritmo esperado.' : 'Nao existe midia em foco neste momento.'} />
        <DetailBlock label="Fila pronta" value={readinessSummary} detail="Mostra o quanto essa tela ja carregou para seguir sem travar." />
        <DetailBlock label="Aproveitamento do cache" value={formatPercentLabel(player.cache_hit_rate)} detail={`Acertos ${player.cache_hit_count} | Falhas ${player.cache_miss_count} | Desatualizadas ${player.cache_stale_fallback_count}`} />
        <DetailBlock label="Espaco usado no navegador" value={`${formatBytes(player.cache_usage_bytes)} de ${formatBytes(player.cache_quota_bytes)}`} detail={`Armazenamento ${formatPersistentStorage(player.persistent_storage)}`} />
        <DetailBlock label="Perfil da maquina" value={formatDeviceProfile(player)} detail={formatDeviceProfileDetail(player)} />
        <DetailBlock label="Rede percebida" value={formatNetworkProfile(player)} detail={formatNetworkProfileDetail(player)} />
        {player.current_media_type === 'video' ? (
          <>
            <DetailBlock
              label="Playback do video"
              value={formatVideoPhase(player.current_video_phase)}
              detail={formatVideoRuntimeSummary(player)}
            />
            <DetailBlock
              label="Progresso atual"
              value={formatVideoProgress(player)}
              detail={`readyState ${player.current_video_ready_state ?? 'sem dado'} | stalls ${player.current_video_stall_count ?? 0}`}
            />
            <DetailBlock
              label="Ultima saida"
              value={formatVideoExitReason(player.current_video_exit_reason)}
              detail={player.current_video_failure_reason ? `Falha ${formatVideoFailureReason(player.current_video_failure_reason).toLowerCase()}` : 'Sem falha registrada para este ciclo.'}
            />
          </>
        ) : null}
      </div>

      <div className="flex flex-wrap gap-2 text-[11px] text-muted-foreground">
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Cache {player.cache_enabled ? 'ativo' : 'desligado'}
        </span>
        {player.last_fallback_reason ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Ultimo problema {formatFallbackReason(player.last_fallback_reason)}
          </span>
        ) : null}
      </div>
    </div>
  );
}

export function WallPlayerDetailsSheet({
  open,
  onOpenChange,
  player,
}: WallPlayerDetailsSheetProps) {
  const isMobile = useIsMobile();

  if (isMobile) {
    return (
      <Drawer open={open} onOpenChange={onOpenChange}>
        <DrawerContent data-testid="wall-player-details-drawer" className="max-h-[90vh] overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle>Detalhes da tela conectada</DrawerTitle>
            <DrawerDescription>
              Veja o que essa tela esta fazendo agora e quando vale intervir.
            </DrawerDescription>
          </DrawerHeader>

          <div className="px-4 pb-6">
            <WallPlayerDetailsBody player={player} />
          </div>
        </DrawerContent>
      </Drawer>
    );
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        data-testid="wall-player-details-sheet"
        className="w-full overflow-y-auto sm:max-w-xl"
      >
        <SheetHeader>
          <SheetTitle>Detalhes da tela conectada</SheetTitle>
          <SheetDescription>
            Veja o que essa tela esta fazendo agora e quando vale intervir.
          </SheetDescription>
        </SheetHeader>

        <WallPlayerDetailsBody player={player} />
      </SheetContent>
    </Sheet>
  );
}
