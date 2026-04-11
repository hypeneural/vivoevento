import { useEffect, useMemo, useRef, useState } from 'react';

import type { ApiWallMediaSource, ApiWallSettings } from '@/lib/api-types';

import BrandingOverlay from '../../../player/components/BrandingOverlay';
import FeaturedBadge from '../../../player/components/FeaturedBadge';
import LayoutRenderer from '../../../player/components/LayoutRenderer';
import SideThumbnails from '../../../player/components/SideThumbnails';
import {
  WALL_CAPTION_PANEL,
  WALL_OVERLAY_GRADIENT,
  WALL_TEXT_PRIMARY,
} from '../../../player/design/tokens';
import { isMultiItemLayout, resolveRenderableLayout, shouldRenderFloatingCaption } from '../../../player/engine/layoutStrategy';
import { applyStageGeometryToWallSettings, useStageGeometry } from '../../../player/hooks/useStageGeometry';
import type { WallRuntimeItem, WallSettings } from '../../../player/types';
import { resolveManagedWallSettings } from '../../../wall-settings';

const DEFAULT_SCENE_WIDTH = 1365;
const DEFAULT_SCENE_HEIGHT = 768;
const PREVIEW_QR_URL = 'https://eventovivo.local/preview-upload';

export interface WallPreviewCanvasPrimaryItem {
  itemId: string;
  previewUrl: string | null;
  senderName: string | null;
  sourceType: ApiWallMediaSource | null;
  isFeatured?: boolean;
  caption?: string | null;
}

export interface WallPreviewCanvasUpcomingItem {
  itemId: string;
  previewUrl: string | null;
  senderName: string;
}

interface WallPreviewCanvasProps {
  settings: ApiWallSettings;
  primaryItem: WallPreviewCanvasPrimaryItem | null;
  upcomingItems: WallPreviewCanvasUpcomingItem[];
}

export function WallPreviewCanvas({
  settings,
  primaryItem,
  upcomingItems,
}: WallPreviewCanvasProps) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const stageRef = useRef<HTMLDivElement | null>(null);
  const [containerSize, setContainerSize] = useState({ width: 0, height: 0 });
  const [sceneSize, setSceneSize] = useState(() => ({
    width: typeof window === 'undefined' ? DEFAULT_SCENE_WIDTH : window.innerWidth || DEFAULT_SCENE_WIDTH,
    height: typeof window === 'undefined' ? DEFAULT_SCENE_HEIGHT : window.innerHeight || DEFAULT_SCENE_HEIGHT,
  }));

  useEffect(() => {
    const updateViewport = () => {
      setSceneSize({
        width: window.innerWidth || DEFAULT_SCENE_WIDTH,
        height: window.innerHeight || DEFAULT_SCENE_HEIGHT,
      });
    };

    updateViewport();
    window.addEventListener('resize', updateViewport);

    return () => {
      window.removeEventListener('resize', updateViewport);
    };
  }, []);

  useEffect(() => {
    const element = containerRef.current;

    if (!element) {
      return;
    }

    const updateContainerSize = () => {
      const rect = element.getBoundingClientRect();

      if (rect.width > 0 && rect.height > 0) {
        setContainerSize({
          width: rect.width,
          height: rect.height,
        });
      }
    };

    updateContainerSize();

    const observer = new ResizeObserver(() => {
      updateContainerSize();
    });

    observer.observe(element);
    window.addEventListener('resize', updateContainerSize);

    return () => {
      observer.disconnect();
      window.removeEventListener('resize', updateContainerSize);
    };
  }, []);

  const previewSettings = useMemo(
    () => resolveManagedWallSettings(settings, []) as unknown as WallSettings,
    [settings],
  );
  const currentItem = useMemo(() => buildRuntimeItem(primaryItem, 0), [primaryItem]);
  const queueRuntimeItems = useMemo(
    () => upcomingItems
      .map((item, index) => buildRuntimeItem({
        itemId: item.itemId,
        previewUrl: item.previewUrl,
        senderName: item.senderName,
        sourceType: null,
        isFeatured: false,
        caption: null,
      }, index + 1))
      .filter((item): item is WallRuntimeItem => item !== null),
    [upcomingItems],
  );
  const allItems = useMemo(
    () => (currentItem ? [currentItem, ...queueRuntimeItems] : queueRuntimeItems),
    [currentItem, queueRuntimeItems],
  );
  const previewLayout = currentItem
    ? resolveRenderableLayout(previewSettings.layout, currentItem)
    : null;
  const captionText = currentItem && previewLayout && shouldRenderFloatingCaption(previewLayout)
    ? currentItem.caption
    : null;
  const stageGeometry = useStageGeometry(stageRef, {
    enabled: previewSettings.layout === 'puzzle',
    showQr: previewSettings.show_qr ?? true,
    showBranding: previewSettings.show_branding ?? true,
    showSenderCredit: previewSettings.show_sender_credit ?? false,
    showFloatingCaption: Boolean(captionText),
    preferredPreset: previewSettings.theme_config?.preset ?? 'standard',
    width: sceneSize.width,
    height: sceneSize.height,
  });
  const stageAwarePreviewSettings = useMemo(
    () => applyStageGeometryToWallSettings(previewSettings, stageGeometry) as WallSettings,
    [previewSettings, stageGeometry],
  );
  const resolvedLayout = currentItem
    ? resolveRenderableLayout(stageAwarePreviewSettings.layout, currentItem)
    : null;
  const isMultiLayout = resolvedLayout ? isMultiItemLayout(resolvedLayout) : false;
  const stageAwareCaptionText = currentItem && resolvedLayout && shouldRenderFloatingCaption(resolvedLayout)
    ? currentItem.caption
    : null;

  const thumbnailColumns = useMemo(() => {
    const visibleItems = queueRuntimeItems
      .filter((item) => item.url)
      .slice(0, 4)
      .map((item) => ({
        id: item.id,
        url: item.url!,
        sender_name: item.sender_name,
      }));

    const midpoint = Math.ceil(visibleItems.length / 2);

    return {
      leftItems: visibleItems.slice(0, midpoint),
      rightItems: visibleItems.slice(midpoint),
    };
  }, [queueRuntimeItems]);

  const scale = containerSize.width > 0 && containerSize.height > 0
    ? Math.min(containerSize.width / sceneSize.width, containerSize.height / sceneSize.height)
    : 1;
  const scaledWidth = sceneSize.width * scale;
  const scaledHeight = sceneSize.height * scale;

  return (
    <div
      ref={containerRef}
      aria-label="Canvas da previa do rascunho"
      className="relative aspect-video overflow-hidden rounded-[28px] border border-border/60 bg-neutral-950"
    >
      <div className="absolute inset-0 flex items-center justify-center overflow-hidden">
        <div
          className="relative overflow-hidden rounded-[28px]"
          style={{
            width: scaledWidth || sceneSize.width,
            height: scaledHeight || sceneSize.height,
          }}
        >
          <div
            className="absolute left-0 top-0 origin-top-left"
            style={{
              width: sceneSize.width,
              height: sceneSize.height,
              transform: `scale(${scale || 1})`,
            }}
          >
            <div ref={stageRef} className="relative h-full w-full overflow-hidden bg-neutral-950 text-white">
              {stageAwarePreviewSettings.background_url ? (
                <>
                  <div
                    className="absolute inset-0 bg-cover bg-center opacity-40"
                    style={{ backgroundImage: `url(${stageAwarePreviewSettings.background_url})` }}
                  />
                  <div className="absolute inset-0 bg-neutral-950/70 backdrop-blur-sm" />
                </>
              ) : (
                <>
                  <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(249,115,22,0.28),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.22),_transparent_28%),linear-gradient(180deg,_#09090b_0%,_#111827_100%)]" />
                  <div className="absolute inset-0 bg-[linear-gradient(135deg,_rgba(255,255,255,0.03)_0%,_transparent_30%,_transparent_70%,_rgba(255,255,255,0.03)_100%)]" />
                </>
              )}

              <div className={`absolute inset-0 bg-black/20 ${WALL_OVERLAY_GRADIENT}`} />

              {currentItem ? (
                <>
                  <LayoutRenderer
                    media={currentItem}
                    settings={stageAwarePreviewSettings}
                    reducedMotion={true}
                    allItems={allItems}
                    eventId="preview"
                    performanceTier="preview"
                  />

                  <BrandingOverlay
                    showBranding={stageAwarePreviewSettings.show_branding ?? true}
                    showQr={stageAwarePreviewSettings.show_qr ?? true}
                    qrUrl={PREVIEW_QR_URL}
                    showNeon={stageAwarePreviewSettings.show_neon ?? false}
                    neonText={stageAwarePreviewSettings.neon_text}
                    neonColor={stageAwarePreviewSettings.neon_color}
                    partnerLogoUrl={stageAwarePreviewSettings.partner_logo_url}
                    showSenderCredit={stageAwarePreviewSettings.show_sender_credit ?? false}
                    senderCredit={currentItem.sender_name}
                    syncLabel="Previa"
                    reducedMotion={true}
                  />

                  {!isMultiLayout && stageAwarePreviewSettings.show_side_thumbnails ? (
                    <SideThumbnails
                      leftItems={thumbnailColumns.leftItems}
                      rightItems={thumbnailColumns.rightItems}
                    />
                  ) : null}

                  <FeaturedBadge isFeatured={Boolean(currentItem.is_featured)} reducedMotion={true} />

                  {stageAwareCaptionText ? (
                    <div className="pointer-events-none absolute bottom-[max(88px,10vh)] left-1/2 z-20 w-[min(90vw,880px)] -translate-x-1/2">
                      <div className={`${WALL_CAPTION_PANEL} px-6 py-5`}>
                        <p className={WALL_TEXT_PRIMARY}>{stageAwareCaptionText}</p>
                      </div>
                    </div>
                  ) : null}
                </>
              ) : (
                <div className="absolute inset-0 flex items-center justify-center px-8 text-center text-sm text-white/72">
                  Selecione uma midia recente ou aguarde a fila prevista para gerar a previa do rascunho.
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function buildRuntimeItem(
  item: WallPreviewCanvasPrimaryItem | null,
  index: number,
): WallRuntimeItem | null {
  if (!item?.previewUrl) {
    return null;
  }

  return {
    id: item.itemId,
    url: item.previewUrl,
    original_url: item.previewUrl,
    type: 'image',
    sender_name: item.senderName,
    sender_key: item.sourceType ? `${item.sourceType}:${item.itemId}` : `preview:${item.itemId}`,
    senderKey: item.sourceType ? `${item.sourceType}:${item.itemId}` : `preview:${item.itemId}`,
    source_type: item.sourceType,
    caption: item.caption ?? null,
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: Boolean(item.isFeatured),
    width: null,
    height: null,
    orientation: null,
    created_at: null,
    assetStatus: 'ready',
    playedAt: null,
    playCount: index,
    lastError: null,
  };
}
