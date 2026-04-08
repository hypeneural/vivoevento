/**
 * WallPlayerRoot — The main wall player composition.
 *
 * Renders the appropriate screen based on player state:
 * - booting: loading spinner
 * - idle: waiting for photos screen
 * - playing: slideshow with layout renderer
 * - paused: slideshow frozen with pause badge
 * - stopped/expired: ended screen
 * - error: error screen
 *
 * Also renders overlays: branding, connection status, floating caption.
 */

import { AlertTriangle, Loader2 } from 'lucide-react';
import { useWallPlayer } from '../hooks/useWallPlayer';
import { usePerformanceMode } from '../hooks/usePerformanceMode';
import { useAdEngine } from '../hooks/useAdEngine';
import { resolveRenderableLayout, shouldRenderFloatingCaption } from '../engine/layoutStrategy';
import { WALL_CAPTION_PANEL, WALL_TEXT_PRIMARY } from '../design/tokens';
import AdOverlay from './AdOverlay';
import BrandingOverlay from './BrandingOverlay';
import ConnectionOverlay from './ConnectionOverlay';
import ExpiredScreen from './ExpiredScreen';
import FeaturedBadge from './FeaturedBadge';
import IdleScreen from './IdleScreen';
import LayoutRenderer from './LayoutRenderer';
import { NewPhotoToast, useNewPhotoToast } from './NewPhotoToast';
import PlayerShell from './PlayerShell';
import SideThumbnails from './SideThumbnails';
import type { WallConnectionStatus } from '../types';
import { useSideThumbnails } from '../hooks/useSideThumbnails';

function FloatingCaption({ layout, text }: { layout: string; text?: string | null }) {
  if (!shouldRenderFloatingCaption(layout as any) || !text) return null;

  return (
    <div className="pointer-events-none absolute bottom-[max(88px,10vh)] left-1/2 z-20 w-[min(90vw,880px)] -translate-x-1/2">
      <div className={`${WALL_CAPTION_PANEL} px-6 py-5`}>
        <p className={WALL_TEXT_PRIMARY}>{text}</p>
      </div>
    </div>
  );
}

function resolveSyncLabel(isSyncing: boolean, connectionStatus: WallConnectionStatus, modeLabel: string): string | undefined {
  if (!isSyncing && connectionStatus === 'connected') return `Live · ${modeLabel}`;
  return undefined;
}

export function WallPlayerRoot({ code }: { code: string }) {
  const {
    state,
    currentItem,
    isSyncing,
    errorMessage,
    connectionStatus,
    lastSyncAt,
    ads,
  } = useWallPlayer(code);

  const { reducedEffects, modeLabel } = usePerformanceMode();
  const { visible: toastVisible, message: toastMessage } = useNewPhotoToast();

  // Ad engine — sits alongside the main engine
  const adEngine = useAdEngine({
    mode: state.settings?.ad_mode ?? 'disabled',
    frequency: state.settings?.ad_frequency ?? 5,
    intervalMinutes: state.settings?.ad_interval_minutes ?? 3,
    ads,
    currentItemId: state.currentItemId,
    isPlaying: state.status === 'playing',
  });

  const activeLayout = currentItem && state.settings
    ? resolveRenderableLayout(state.settings.layout, currentItem)
    : null;

  // Multi-item layouts don't show side thumbnails
  const isMultiItemLayout = activeLayout === 'carousel' || activeLayout === 'mosaic' || activeLayout === 'grid';

  const sideThumbs = useSideThumbnails(
    state.items,
    state.currentItemId,
    {
      enabled:
        state.status === 'playing'
        && (state.settings?.show_side_thumbnails ?? false)
        && !isMultiItemLayout,
    },
  );

  return (
    <PlayerShell backgroundUrl={state.settings?.background_url}>
      {/* Branding overlay */}
      <BrandingOverlay
        showBranding={state.settings?.show_branding ?? true}
        showQr={
          (state.settings?.show_qr ?? true)
          && state.status !== 'expired'
          && state.status !== 'stopped'
          && state.status !== 'error'
        }
        qrUrl={state.event?.upload_url}
        showNeon={state.settings?.show_neon ?? false}
        neonText={state.settings?.neon_text}
        neonColor={state.settings?.neon_color}
        partnerLogoUrl={state.settings?.partner_logo_url}
        showSenderCredit={state.settings?.show_sender_credit ?? false}
        senderCredit={currentItem?.sender_name}
        syncLabel={resolveSyncLabel(isSyncing, connectionStatus, modeLabel)}
        reducedMotion={reducedEffects}
      />

      {/* Connection status */}
      <ConnectionOverlay
        connectionStatus={connectionStatus}
        isSyncing={isSyncing}
        lastSyncAt={lastSyncAt}
      />

      {/* ─── State-based rendering ────────────────────────── */}

      {state.status === 'expired' || state.status === 'stopped' ? (
        <ExpiredScreen
          title={
            state.status === 'stopped'
              ? 'Telão desativado'
              : undefined
          }
          message={
            errorMessage || (
              state.status === 'stopped'
                ? 'O operador parou a exibição pública deste evento.'
                : undefined
            )
          }
        />
      ) : state.status === 'booting' && !currentItem ? (
        <div className="flex min-h-screen items-center justify-center">
          <div className="rounded-3xl border border-white/10 bg-black/30 px-8 py-6 text-center shadow-2xl backdrop-blur-xl">
            <Loader2 className="mx-auto h-8 w-8 animate-spin text-orange-300" />
            <p className="mt-4 text-lg font-medium">Carregando o telão</p>
            <p className="mt-2 text-sm text-white/65">
              Preparando fila, cache e layout do evento.
            </p>
          </div>
        </div>
      ) : state.status === 'paused' && !currentItem ? (
        <ExpiredScreen
          title="Telão pausado"
          message="O operador pausou temporariamente a exibição. As novas fotos continuam sendo sincronizadas e entram na fila quando o player voltar para ativo."
        />
      ) : currentItem && state.settings ? (
        <>
          {adEngine.currentAd ? (
            <AdOverlay
              ad={adEngine.currentAd}
              onFinished={adEngine.onAdFinished}
              reducedMotion={reducedEffects}
            />
          ) : (
            <LayoutRenderer media={currentItem} settings={state.settings} reducedMotion={reducedEffects} allItems={state.items} />
          )}
          <FloatingCaption
            layout={activeLayout ?? 'fullscreen'}
            text={currentItem.caption}
          />
          {/* Featured badge */}
          <FeaturedBadge isFeatured={currentItem.is_featured} reducedMotion={reducedEffects} />
          {/* Side thumbnails */}
          {sideThumbs.enabled ? (
            <SideThumbnails leftItems={sideThumbs.leftItems} rightItems={sideThumbs.rightItems} />
          ) : null}
          {/* New photo toast */}
          <NewPhotoToast visible={toastVisible} message={toastMessage} reducedMotion={reducedEffects} />
          {/* Paused badge */}
          {state.status === 'paused' ? (
            <div className="pointer-events-none absolute inset-x-0 top-[max(16px,2vh)] z-30 flex justify-center px-[max(16px,2vw)]">
              <div className="rounded-full border border-amber-400/30 bg-amber-500/15 px-5 py-2 text-sm font-medium uppercase tracking-[0.3em] text-amber-100 shadow-[0_16px_50px_rgba(0,0,0,0.22)] backdrop-blur-xl">
                Telão pausado
              </div>
            </div>
          ) : null}
        </>
      ) : state.status === 'error' ? (
        <div className="flex min-h-screen items-center justify-center px-[max(16px,2vw)]">
          <div className="w-full max-w-2xl rounded-[32px] border border-white/10 bg-black/35 p-10 text-center shadow-[0_30px_120px_rgba(0,0,0,0.45)] backdrop-blur-xl">
            <AlertTriangle className="mx-auto h-12 w-12 text-amber-300" />
            <h1 className="mt-6 text-[clamp(2rem,3vw,3rem)] font-semibold">
              Não foi possível carregar o telão
            </h1>
            <p className="mt-4 text-lg text-white/70">
              {errorMessage || 'A API não respondeu e não há mídia local suficiente para iniciar o player.'}
            </p>
          </div>
        </div>
      ) : (
        <IdleScreen
          title={state.event?.title}
          code={state.event?.wall_code || code}
          instructions={state.settings?.instructions_text}
        />
      )}
    </PlayerShell>
  );
}

export default WallPlayerRoot;
