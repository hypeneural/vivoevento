import type { ApiEventMediaItem } from '@/lib/api-types';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

interface GalleryVideoModalProps {
  media: ApiEventMediaItem | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function GalleryVideoModal({ media, open, onOpenChange }: GalleryVideoModalProps) {
  const videoUrl = media?.preview_url || media?.original_url || null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-5xl border-white/10 bg-slate-950 p-4 text-white">
        <DialogHeader>
          <DialogTitle>{media?.caption || 'Video da galeria'}</DialogTitle>
          <DialogDescription className="text-white/65">
            Videos usam player dedicado para manter o lightbox otimizado para fotos.
          </DialogDescription>
        </DialogHeader>

        {videoUrl ? (
          <video
            aria-label="Player do video"
            src={videoUrl}
            poster={media?.thumbnail_url || undefined}
            controls
            playsInline
            preload="metadata"
            className="aspect-video w-full rounded-2xl bg-black"
          />
        ) : (
          <div className="flex aspect-video items-center justify-center rounded-2xl bg-white/5 text-sm text-white/65">
            Video indisponivel.
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
