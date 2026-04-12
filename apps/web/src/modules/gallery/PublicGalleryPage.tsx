import { useQuery } from '@tanstack/react-query';
import { Image as ImageIcon, Loader2, Search } from 'lucide-react';
import { useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { getPublicGallery } from '@/modules/events/api';
import { galleryExperienceFixtures } from './gallery-builder';
import { GalleryRenderer } from './components/GalleryRenderer';

export default function PublicGalleryPage() {
  const { slug } = useParams<{ slug: string }>();

  const galleryQuery = useQuery({
    queryKey: ['public-gallery', slug],
    enabled: !!slug,
    retry: false,
    queryFn: () => getPublicGallery(slug as string),
  });

  if (!slug) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 px-6 text-center text-sm text-white/70">
        Link de galeria invalido.
      </div>
    );
  }

  if (galleryQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950">
        <div className="flex items-center gap-2 text-sm text-white/70">
          <Loader2 className="h-4 w-4 animate-spin" />
          Carregando galeria...
        </div>
      </div>
    );
  }

  if (galleryQuery.isError) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 px-6 text-center">
        <div className="max-w-md space-y-3">
          <ImageIcon className="mx-auto h-10 w-10 text-white/40" />
          <h1 className="text-xl font-semibold text-white">Galeria indisponivel</h1>
          <p className="text-sm text-white/65">
            Este link nao esta ativo no momento ou o evento ainda nao publicou fotos.
          </p>
        </div>
      </div>
    );
  }

  const media = galleryQuery.data?.data ?? [];
  const publicFaceSearch = galleryQuery.data?.meta.face_search;
  const publicEvent = galleryQuery.data?.event;
  const experience = galleryQuery.data?.experience ?? galleryExperienceFixtures.weddingPremiumLight;
  const branding = publicEvent?.branding;
  const pageBackground = experience.theme_tokens.palette.page_background;
  const textPrimary = experience.theme_tokens.palette.text_primary;
  const accent = experience.theme_tokens.palette.accent;

  return (
    <div
      className="min-h-[100dvh] px-4 py-6 text-white md:py-8"
      style={{
        background:
          `radial-gradient(circle at top, ${accent}33, transparent 35%), linear-gradient(180deg, ${pageBackground} 0%, #020617 100%)`,
        color: textPrimary,
      }}
    >
      <div className="mx-auto max-w-6xl space-y-8">
        <div className="overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950 text-white shadow-2xl">
          {branding?.cover_image_url ? (
            <img
              src={branding.cover_image_url}
              alt=""
              className="h-52 w-full object-cover md:h-72"
              loading="eager"
              decoding="async"
            />
          ) : null}
          <div className="space-y-4 p-6 text-center md:p-10">
            {branding?.logo_url ? (
              <img
                src={branding.logo_url}
                alt=""
                className="mx-auto h-14 w-14 rounded-2xl object-contain"
                loading="eager"
                decoding="async"
              />
            ) : null}
            <div className="space-y-3">
              <p className="text-xs uppercase tracking-[0.28em] text-white/55">Evento Vivo</p>
              <h1 className="text-3xl font-semibold md:text-5xl">{publicEvent?.title ?? 'Galeria Publica'}</h1>
              <p className="text-sm text-white/70">
                {galleryQuery.data?.meta.total ?? media.length} foto(s) publicadas para este evento.
              </p>
            </div>
          </div>
        </div>

        {publicFaceSearch?.public_search_enabled && publicFaceSearch.find_me_url ? (
          <Card className="border-emerald-400/20 bg-emerald-500/10 text-white shadow-none">
            <CardContent className="flex flex-col gap-4 py-6 md:flex-row md:items-center md:justify-between">
              <div className="space-y-1">
                <p className="text-base font-semibold">Quer encontrar suas fotos mais rapido?</p>
                <p className="text-sm text-emerald-50/80">
                  Envie uma selfie para filtrar as fotos publicadas em que voce aparece.
                </p>
              </div>
              <Button asChild className="rounded-full bg-emerald-500 text-white hover:bg-emerald-400">
                <a href={publicFaceSearch.find_me_url}>
                  <Search className="h-4 w-4" />
                  Encontrar minhas fotos
                </a>
              </Button>
            </CardContent>
          </Card>
        ) : null}

        {media.length === 0 ? (
          <Card className="border-white/10 bg-white/5 text-white shadow-none">
            <CardContent className="py-14 text-center text-sm text-white/65">
              Ainda nao existem imagens publicadas para esta galeria.
            </CardContent>
          </Card>
        ) : (
          <GalleryRenderer media={media} experience={experience} />
        )}
      </div>
    </div>
  );
}
