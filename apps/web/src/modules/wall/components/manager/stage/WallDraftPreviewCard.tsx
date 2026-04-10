import type { ApiWallMediaSource, ApiWallSettings } from '@/lib/api-types';

import { resolveManagedWallSettings } from '../../../wall-settings';
import { WallPreviewCanvas } from './WallPreviewCanvas';

type PreviewMedia = {
  previewUrl: string | null;
  senderName: string | null;
  sourceType: ApiWallMediaSource | null;
  isFeatured?: boolean;
  caption?: string | null;
};

type UpcomingPreviewItem = {
  itemId: string;
  previewUrl: string | null;
  senderName: string;
};

interface WallDraftPreviewCardProps {
  settings: ApiWallSettings;
  previewMedia: PreviewMedia | null;
  upcomingItems: UpcomingPreviewItem[];
}

export function WallDraftPreviewCard({
  settings,
  previewMedia,
  upcomingItems,
}: WallDraftPreviewCardProps) {
  const previewSettings = resolveManagedWallSettings(settings, []);
  const visibleUpcomingItems = previewSettings.show_side_thumbnails ? upcomingItems.slice(0, 3) : [];

  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
      <div className="space-y-1">
        <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Previa do rascunho</p>
        <p className="text-sm text-muted-foreground">
          Usa o mesmo renderer visual do player, sem abrir outra tela e sem depender de `iframe`.
        </p>
      </div>

      <div className="mt-4">
        <WallPreviewCanvas
          settings={previewSettings}
          primaryItem={previewMedia ? {
            itemId: 'preview-current',
            previewUrl: previewMedia.previewUrl,
            senderName: previewMedia.senderName,
            sourceType: previewMedia.sourceType,
            isFeatured: previewMedia.isFeatured,
            caption: previewMedia.caption,
          } : null}
          upcomingItems={visibleUpcomingItems}
        />
      </div>

      <div className="mt-4 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          {previewSettings.show_side_thumbnails ? 'Miniaturas laterais ativas' : 'Miniaturas laterais ocultas'}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          {previewSettings.show_sender_credit ? 'Credito do remetente ativo' : 'Credito do remetente oculto'}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          {previewSettings.show_qr ? 'QR visivel' : 'QR oculto'}
        </span>
      </div>
    </div>
  );
}
