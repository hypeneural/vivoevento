import type { ApiWallInsightsRecentItem } from '@/lib/api-types';

import { Drawer, DrawerContent, DrawerDescription, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';

import { formatWallRecentStatusLabel } from '@/modules/wall/wall-copy';
import { getWallSourceMeta } from '@/modules/wall/wall-source-meta';
import { formatWallRelativeTime } from '@/modules/wall/wall-view-models';

interface WallRecentMediaDetailsSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: ApiWallInsightsRecentItem | null;
}

function formatAbsoluteDateTime(value?: string | null) {
  if (!value) {
    return 'Sem registro ainda';
  }

  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return 'Sem registro ainda';
  }

  return parsed.toLocaleString('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  });
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 px-4 py-3">
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-1 text-sm font-medium text-foreground">{value}</p>
    </div>
  );
}

function RecentMediaDetailsBody({ item }: { item: ApiWallInsightsRecentItem | null }) {
  const sourceMeta = item ? getWallSourceMeta(item.source) : null;

  return (
    <div className="mt-4 space-y-4">
      <div className="overflow-hidden rounded-3xl border border-border/60 bg-muted/30">
        <div className="aspect-[4/3] sm:aspect-video">
          {item?.previewUrl ? (
            <img
              src={item.previewUrl}
              alt={`Midia recente enviada por ${item.senderName || 'Convidado'}`}
              className="h-full w-full object-cover"
            />
          ) : (
            <div className="flex h-full items-center justify-center px-6 text-center text-sm text-muted-foreground">
              Esta midia ainda nao tem miniatura pronta.
            </div>
          )}
        </div>
      </div>

      <div className="flex flex-wrap gap-2 text-xs">
        {sourceMeta ? (
          <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 font-medium ${sourceMeta.chipClassName}`}>
            <sourceMeta.Icon className="h-3.5 w-3.5" />
            {sourceMeta.label}
          </span>
        ) : null}
        {item?.isFeatured ? (
          <span className="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 font-medium text-amber-700">
            Destaque
          </span>
        ) : null}
        {item?.isReplay ? (
          <span className="inline-flex rounded-full border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 font-medium text-sky-700">
            Reprise
          </span>
        ) : null}
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <DetailRow label="Remetente" value={item?.senderName || 'Convidado'} />
        <DetailRow
          label="Situacao"
          value={item ? formatWallRecentStatusLabel(item.status) : 'Sem status'}
        />
        <DetailRow
          label="Chegou"
          value={item ? `${formatWallRelativeTime(item.createdAt, 'Agora')} (${formatAbsoluteDateTime(item.createdAt)})` : 'Sem horario'}
        />
        <DetailRow
          label="Aprovada"
          value={item?.approvedAt ? formatAbsoluteDateTime(item.approvedAt) : 'Ainda nao aprovada'}
        />
        <DetailRow
          label="Exibida"
          value={item?.displayedAt ? formatAbsoluteDateTime(item.displayedAt) : 'Ainda nao exibida'}
        />
        <DetailRow
          label="Origem"
          value={sourceMeta?.label ?? 'Sem origem'}
        />
      </div>
    </div>
  );
}

export function WallRecentMediaDetailsSheet({
  open,
  onOpenChange,
  item,
}: WallRecentMediaDetailsSheetProps) {
  const isMobile = useIsMobile();

  if (isMobile) {
    return (
      <Drawer open={open} onOpenChange={onOpenChange}>
        <DrawerContent data-testid="wall-recent-media-details-drawer" className="max-h-[90vh] overflow-y-auto">
          <DrawerHeader>
            <DrawerTitle>Detalhes da midia recente</DrawerTitle>
            <DrawerDescription>
              Veja quem enviou, a origem e o andamento desta midia no telao.
            </DrawerDescription>
          </DrawerHeader>

          <div className="px-4 pb-6">
            <RecentMediaDetailsBody item={item} />
          </div>
        </DrawerContent>
      </Drawer>
    );
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        data-testid="wall-recent-media-details-sheet"
        className="w-full overflow-y-auto sm:max-w-xl"
      >
        <SheetHeader>
          <SheetTitle>Detalhes da midia recente</SheetTitle>
          <SheetDescription>
            Veja quem enviou, a origem e o andamento desta midia no telao.
          </SheetDescription>
        </SheetHeader>

        <RecentMediaDetailsBody item={item} />
      </SheetContent>
    </Sheet>
  );
}
