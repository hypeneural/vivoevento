import { ImageIcon, ScanFace } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

import type { EventMediaFacePeople } from '../types';

export interface EventPeopleFaceOverlayProps {
  mediaType: 'image' | 'video' | string;
  surfaceUrl?: string | null;
  alt: string;
  faces: EventMediaFacePeople[];
  selectedFaceId?: number | null;
  onSelectFace: (face: EventMediaFacePeople) => void;
}

function faceLabel(face: EventMediaFacePeople) {
  return face.current_assignment?.person?.display_name || face.review_item?.payload.question || 'Quem e esta pessoa?';
}

export function EventPeopleFaceOverlay({
  mediaType,
  surfaceUrl,
  alt,
  faces,
  selectedFaceId = null,
  onSelectFace,
}: EventPeopleFaceOverlayProps) {
  if (!surfaceUrl || mediaType !== 'image') {
    return (
      <div className="flex min-h-[320px] items-center justify-center rounded-3xl border border-border/60 bg-muted text-muted-foreground">
        <div className="flex flex-col items-center gap-3 text-center">
          <ImageIcon className="h-10 w-10" />
          <p className="text-sm">A marcacao dos rostos aparece apenas em imagens.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-[320px] items-center justify-center rounded-3xl border border-border/60 bg-muted/40 p-4">
      <div className="relative inline-block max-w-full">
        <img
          src={surfaceUrl}
          alt={alt}
          className="block max-h-[70vh] max-w-full rounded-2xl object-contain shadow-sm"
          loading="lazy"
          decoding="async"
        />

        {faces.map((face) => {
          const label = faceLabel(face);

          return (
            <button
              key={face.id}
              type="button"
              className={cn(
                'group absolute rounded-xl border-2 transition-all focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                selectedFaceId === face.id
                  ? 'border-primary bg-primary/10 shadow-[0_0_0_2px_rgba(255,255,255,0.18)]'
                  : 'border-white/80 bg-black/10 hover:border-primary/80 hover:bg-primary/10',
              )}
              style={{
                left: `${face.bbox.x * 100}%`,
                top: `${face.bbox.y * 100}%`,
                width: `${face.bbox.w * 100}%`,
                height: `${face.bbox.h * 100}%`,
              }}
              onClick={() => onSelectFace(face)}
              aria-label={label}
            >
              <span className="absolute left-1 top-1 max-w-[calc(100%-0.5rem)] rounded-full bg-background/95 px-2 py-1 text-left text-[11px] font-medium text-foreground shadow-sm">
                {face.current_assignment?.person?.display_name ? (
                  face.current_assignment.person.display_name
                ) : (
                  <span className="inline-flex items-center gap-1">
                    <ScanFace className="h-3 w-3" />
                    Quem e esta pessoa?
                  </span>
                )}
              </span>
            </button>
          );
        })}

        {faces.length === 0 ? (
          <div className="absolute inset-0 flex items-center justify-center">
            <Badge variant="secondary">Nenhum rosto detectado nesta imagem</Badge>
          </div>
        ) : null}
      </div>
    </div>
  );
}

export default EventPeopleFaceOverlay;
