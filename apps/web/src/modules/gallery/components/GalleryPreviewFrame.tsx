import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ApiEventMediaItem } from '@/lib/api-types';
import { cn } from '@/lib/utils';
import {
  buildGalleryExperienceFromBuilder,
  type GalleryBuilderEventSummary,
  type GalleryRenderMode,
  type GalleryBuilderSettings,
  type GalleryBuilderViewport,
} from '../gallery-builder';
import { useGalleryReducedMotion } from '../hooks/useGalleryReducedMotion';
import { GalleryRenderer } from './GalleryRenderer';

interface GalleryPreviewFrameProps {
  event: GalleryBuilderEventSummary;
  draft: GalleryBuilderSettings;
  media: ApiEventMediaItem[];
  viewport: GalleryBuilderViewport;
  renderMode: GalleryRenderMode;
}

export function GalleryPreviewFrame({
  event,
  draft,
  media,
  viewport,
  renderMode,
}: GalleryPreviewFrameProps) {
  const heroBlock = draft.page_schema.blocks.hero as {
    show_logo?: boolean;
    show_face_search_cta?: boolean;
    image_url?: string | null;
  } | undefined;
  const bannerBlock = draft.page_schema.blocks.banner_strip as {
    enabled?: boolean;
    image_url?: string | null;
  } | undefined;
  const respectUserPreference = draft.theme_tokens.motion.respect_user_preference ?? true;
  const { shouldReduceMotion } = useGalleryReducedMotion(respectUserPreference);

  return (
    <div
      data-testid="gallery-preview-frame"
      data-viewport={viewport}
      data-render-mode={renderMode}
      data-reduced-motion={String(shouldReduceMotion)}
      role="region"
      aria-label="Preview da galeria"
      className="rounded-[32px] border border-border/60 bg-[linear-gradient(180deg,_rgba(255,255,255,0.92),_rgba(248,250,252,0.94))] p-4 shadow-sm dark:bg-[linear-gradient(180deg,_rgba(15,23,42,0.94),_rgba(15,23,42,0.88))]"
    >
      <div
        className={cn(
          'mx-auto overflow-hidden rounded-[28px] border border-black/5 shadow-2xl',
          viewport === 'mobile' ? 'max-w-[430px]' : 'max-w-[1180px]',
        )}
        style={{
          background:
            `radial-gradient(circle at top, ${draft.theme_tokens.palette.accent}22, transparent 35%), linear-gradient(180deg, ${draft.theme_tokens.palette.page_background} 0%, ${draft.theme_tokens.palette.surface_background} 100%)`,
          color: draft.theme_tokens.palette.text_primary,
        }}
      >
        <div className="space-y-6 p-5 md:p-8">
          <section className="overflow-hidden rounded-[26px] border border-black/5 bg-black/10">
            {heroBlock?.image_url ? (
              <img
                src={heroBlock.image_url}
                alt=""
                className="h-48 w-full object-cover md:h-72"
                loading="eager"
                decoding="async"
              />
            ) : null}
            <div className="space-y-4 p-6 text-center">
              <div className="flex flex-wrap items-center justify-center gap-2">
                <Badge variant="secondary">Preview publico</Badge>
                <Badge variant="outline">{draft.layout_key}</Badge>
                <Badge variant="outline">{renderMode === 'optimized' ? 'Modo otimizado' : 'Modo padrao'}</Badge>
                <Badge variant="outline">{shouldReduceMotion ? 'Reduced motion ativo' : 'Motion completo'}</Badge>
              </div>
              {heroBlock?.show_logo ? (
                <div className="mx-auto inline-flex rounded-full border border-black/10 bg-white/80 px-4 py-1 text-xs font-medium text-slate-700">
                  Logo do evento
                </div>
              ) : null}
              <div className="space-y-2">
                <p className="text-xs uppercase tracking-[0.28em] opacity-70">Evento Vivo</p>
                <h2 className="text-3xl font-semibold md:text-5xl">{event.title}</h2>
                <p className="text-sm opacity-75">
                  Builder em preview com o mesmo renderer publico da galeria.
                </p>
              </div>

              {heroBlock?.show_face_search_cta ? (
                <Button type="button" className="rounded-full">
                  Encontrar minhas fotos
                </Button>
              ) : null}
            </div>
          </section>

          {bannerBlock?.enabled ? (
            <section className="rounded-[22px] border border-black/5 bg-white/70 p-4 text-center text-sm text-slate-700">
              {bannerBlock.image_url ? (
                <img
                  src={bannerBlock.image_url}
                  alt=""
                  className="h-28 w-full rounded-2xl object-cover"
                  loading="lazy"
                  decoding="async"
                />
              ) : (
                'Faixa editorial ativa para patrocinios ou mensagens.'
              )}
            </section>
          ) : null}

          <GalleryRenderer
            media={media}
            experience={buildGalleryExperienceFromBuilder(draft)}
            renderMode={renderMode}
            className="pb-2"
          />
        </div>
      </div>
    </div>
  );
}
