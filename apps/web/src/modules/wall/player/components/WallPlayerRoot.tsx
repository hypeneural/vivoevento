/**
 * WallPlayerRoot - The main wall player composition.
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

import { MotionConfig } from 'framer-motion';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useWallPlayer } from '../hooks/useWallPlayer';
import { usePerformanceMode } from '../hooks/usePerformanceMode';
import { resolveRenderableLayout, shouldRenderFloatingCaption } from '../engine/layoutStrategy';
import { WALL_CAPTION_PANEL, WALL_TEXT_PRIMARY } from '../design/tokens';
import { resolveWallMotionConfig } from '../themes/motion';
import { getWallLayoutDefinition } from '../themes/registry';
import { createBoardInstanceKey } from '../themes/board/types';
import { resolvePuzzlePieceCount } from '../themes/puzzle/usePuzzleBoard';
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
import type { MediaSurfaceVideoControlProps } from './MediaSurface';
import { useSideThumbnails } from '../hooks/useSideThumbnails';
import { applyStageGeometryToWallSettings, useStageGeometry } from '../hooks/useStageGeometry';

const DEFAULT_BOARD_SLOT_COUNT = 3;

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
    handleAdFinished,
    handleVideoStarting,
    handleVideoFirstFrame,
    handleVideoPlaybackReady,
    handleVideoPlaying,
    handleVideoProgress,
    handleVideoWaiting,
    handleVideoStalled,
    handleVideoEnded,
    handleVideoFailure,
    videoRuntimeConfig,
    setBoardRuntimeTelemetry,
  } = useWallPlayer(code);
  const stageContentRef = useRef<HTMLDivElement | null>(null);
  const lastBoardMediaIdRef = useRef<string | null>(null);
  const boardBurstCountRef = useRef(0);
  const lastBoardIdentityKeyRef = useRef<string | null>(null);
  const boardResetCountRef = useRef(0);
  const lastBoardDowngradeSignatureRef = useRef<string | null>(null);
  const boardBudgetDowngradeCountRef = useRef(0);

  const { reducedEffects, modeLabel, performanceTier, runtimeBudget } = usePerformanceMode();
  const { visible: toastVisible, message: toastMessage } = useNewPhotoToast();
  const isAdShowing = Boolean(state.currentAd);
  const captionVisible = Boolean(
    currentItem
    && state.settings
    && !isAdShowing
    && shouldRenderFloatingCaption(
      resolveRenderableLayout(
        state.settings.layout,
        currentItem,
        state.settings.video_multi_layout_policy ?? 'disallow',
      ),
    )
    && currentItem.caption,
  );
  const stageGeometry = useStageGeometry(stageContentRef, {
    enabled: state.settings?.layout === 'puzzle',
    showQr:
      (state.settings?.show_qr ?? true)
      && state.status !== 'expired'
      && state.status !== 'stopped'
      && state.status !== 'error',
    showBranding: state.settings?.show_branding ?? true,
    showSenderCredit: !isAdShowing && (state.settings?.show_sender_credit ?? false),
    showFloatingCaption: captionVisible,
    preferredPreset: state.settings?.theme_config?.preset ?? 'standard',
    width: typeof window === 'undefined' ? 1365 : window.innerWidth || 1365,
    height: typeof window === 'undefined' ? 768 : window.innerHeight || 768,
  });
  const stageAwareSettings = state.settings
    ? applyStageGeometryToWallSettings(state.settings, stageGeometry)
    : null;

  const activeLayout = currentItem && stageAwareSettings
    ? resolveRenderableLayout(
      stageAwareSettings.layout,
      currentItem,
      stageAwareSettings.video_multi_layout_policy ?? 'disallow',
    )
    : null;

  const activeLayoutDefinition = getWallLayoutDefinition(
    activeLayout ?? stageAwareSettings?.layout ?? 'fullscreen',
  );
  const motionConfig = resolveWallMotionConfig(activeLayoutDefinition.motion, reducedEffects);
  const isBoardLayout = activeLayoutDefinition.kind === 'board';
  const effectivePuzzlePreset = stageAwareSettings?.theme_config?.preset ?? 'standard';
  const preferredPuzzlePreset = state.settings?.theme_config?.preset ?? 'standard';
  const isBoardRuntime = Boolean(currentItem && stageAwareSettings && isBoardLayout && !isAdShowing);
  const boardPieceCount = !isBoardRuntime
    ? 0
    : activeLayout === 'puzzle'
      ? resolvePuzzlePieceCount(effectivePuzzlePreset, runtimeBudget.maxBoardPieces)
      : DEFAULT_BOARD_SLOT_COUNT;
  const boardMediaSlotCount = !isBoardRuntime
    ? 0
    : activeLayout === 'puzzle' && (stageAwareSettings?.theme_config?.anchor_mode ?? 'none') !== 'none'
      ? Math.max(0, boardPieceCount - 1)
      : boardPieceCount;
  const decodeBacklogCount = !isBoardRuntime
    ? 0
    : state.items
      .filter((item) => (activeLayout === 'puzzle' ? item.type === 'image' : true))
      .slice(0, boardMediaSlotCount)
      .filter((item) => item.assetStatus === 'loading')
      .length;
  const preferredPuzzlePieceCount = preferredPuzzlePreset === 'compact' ? 6 : 9;
  const boardBudgetDowngradeReason = !isBoardRuntime || activeLayout !== 'puzzle'
    ? null
    : stageGeometry.downgradeReason
      ?? (boardPieceCount < preferredPuzzlePieceCount ? 'runtime_budget' : null);
  const boardIdentityKey = !isBoardRuntime || !stageAwareSettings
    ? null
    : createBoardInstanceKey({
      eventId: state.event?.id ?? code,
      layout: activeLayout ?? stageAwareSettings.layout,
      preset: stageAwareSettings.theme_config?.preset ?? null,
      themeVersion: activeLayoutDefinition.version,
      performanceTier,
      reducedMotion: reducedEffects,
    });

  const sideThumbs = useSideThumbnails(
    state.items,
    state.currentItemId,
    {
      enabled:
        state.status === 'playing'
        && (stageAwareSettings?.show_side_thumbnails ?? false)
        && !isBoardLayout
        && !isAdShowing,
    },
  );

  const videoControl: MediaSurfaceVideoControlProps | null = currentItem?.type === 'video' && !isAdShowing
    ? {
        playerStatus: state.status,
        startupDeadlineMs: videoRuntimeConfig.startupDeadlineMs,
        stallBudgetMs: videoRuntimeConfig.stallBudgetMs,
        resumeMode: videoRuntimeConfig.resumeMode,
        onStarting: handleVideoStarting,
        onFirstFrame: handleVideoFirstFrame,
        onPlaybackReady: handleVideoPlaybackReady,
        onPlaying: handleVideoPlaying,
        onProgress: handleVideoProgress,
        onWaiting: handleVideoWaiting,
        onStalled: handleVideoStalled,
        onEnded: handleVideoEnded,
        onFailure: handleVideoFailure,
      }
    : null;

  useEffect(() => {
    if (!isBoardRuntime || !currentItem) {
      return;
    }

    if (lastBoardMediaIdRef.current && lastBoardMediaIdRef.current !== currentItem.id) {
      boardBurstCountRef.current += 1;
    }

    lastBoardMediaIdRef.current = currentItem.id;
  }, [currentItem?.id, isBoardRuntime, currentItem]);

  useEffect(() => {
    if (!boardIdentityKey) {
      return;
    }

    if (lastBoardIdentityKeyRef.current && lastBoardIdentityKeyRef.current !== boardIdentityKey) {
      boardResetCountRef.current += 1;
    }

    lastBoardIdentityKeyRef.current = boardIdentityKey;
  }, [boardIdentityKey]);

  useEffect(() => {
    if (!boardBudgetDowngradeReason) {
      return;
    }

    const downgradeSignature = `${boardIdentityKey ?? activeLayout ?? 'board'}:${boardPieceCount}:${boardBudgetDowngradeReason}`;

    if (lastBoardDowngradeSignatureRef.current !== downgradeSignature) {
      boardBudgetDowngradeCountRef.current += 1;
      lastBoardDowngradeSignatureRef.current = downgradeSignature;
    }
  }, [activeLayout, boardBudgetDowngradeReason, boardIdentityKey, boardPieceCount]);

  useEffect(() => {
    setBoardRuntimeTelemetry({
      boardPieceCount: isBoardRuntime ? boardPieceCount : 0,
      boardBurstCount: isBoardRuntime ? boardBurstCountRef.current : 0,
      boardBudgetDowngradeCount: isBoardRuntime ? boardBudgetDowngradeCountRef.current : 0,
      decodeBacklogCount: isBoardRuntime ? decodeBacklogCount : 0,
      boardResetCount: isBoardRuntime ? boardResetCountRef.current : 0,
      boardBudgetDowngradeReason: isBoardRuntime ? boardBudgetDowngradeReason : null,
    });
  }, [
    boardBudgetDowngradeReason,
    boardPieceCount,
    currentItem?.id,
    decodeBacklogCount,
    isBoardRuntime,
    setBoardRuntimeTelemetry,
  ]);

  return (
    <MotionConfig
      reducedMotion={motionConfig.reducedMotion}
      transition={motionConfig.transition}
    >
      <PlayerShell backgroundUrl={stageAwareSettings?.background_url} contentRef={stageContentRef}>
      <BrandingOverlay
        showBranding={stageAwareSettings?.show_branding ?? true}
        showQr={
          (stageAwareSettings?.show_qr ?? true)
          && state.status !== 'expired'
          && state.status !== 'stopped'
          && state.status !== 'error'
        }
        qrUrl={state.event?.upload_url}
        showNeon={stageAwareSettings?.show_neon ?? false}
        neonText={stageAwareSettings?.neon_text}
        neonColor={stageAwareSettings?.neon_color}
        partnerLogoUrl={stageAwareSettings?.partner_logo_url}
        showSenderCredit={!isAdShowing && (stageAwareSettings?.show_sender_credit ?? false)}
        senderCredit={isAdShowing ? null : currentItem?.sender_name}
        syncLabel={resolveSyncLabel(isSyncing, connectionStatus, modeLabel)}
        reducedMotion={reducedEffects}
      />

      <ConnectionOverlay
        connectionStatus={connectionStatus}
        isSyncing={isSyncing}
        lastSyncAt={lastSyncAt}
      />

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
      ) : currentItem && stageAwareSettings ? (
        <>
          {state.currentAd ? (
            <AdOverlay
              ad={state.currentAd}
              onFinished={handleAdFinished}
              reducedMotion={reducedEffects}
            />
          ) : (
            <LayoutRenderer
              media={currentItem}
              settings={stageAwareSettings}
              reducedMotion={reducedEffects}
              allItems={state.items}
              videoControl={videoControl}
              eventId={state.event?.id ?? code}
              performanceTier={performanceTier}
            />
          )}
          {!isAdShowing ? (
            <FloatingCaption
              layout={activeLayout ?? 'fullscreen'}
              text={currentItem.caption}
            />
          ) : null}
          {!isAdShowing ? (
            <FeaturedBadge isFeatured={currentItem.is_featured} reducedMotion={reducedEffects} />
          ) : null}
          {sideThumbs.enabled ? (
            <SideThumbnails leftItems={sideThumbs.leftItems} rightItems={sideThumbs.rightItems} />
          ) : null}
          <NewPhotoToast visible={toastVisible} message={toastMessage} reducedMotion={reducedEffects} />
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
          instructions={stageAwareSettings?.instructions_text}
        />
      )}
      </PlayerShell>
    </MotionConfig>
  );
}

export default WallPlayerRoot;
