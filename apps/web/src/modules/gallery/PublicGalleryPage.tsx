import { useQuery } from '@tanstack/react-query';
import { Image as ImageIcon, Loader2, Search } from 'lucide-react';
import { useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { getPublicGallery } from '@/modules/events/api';

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

  return (
    <div className="min-h-[100dvh] bg-[radial-gradient(circle_at_top,_rgba(34,197,94,0.18),_transparent_35%),linear-gradient(180deg,_#020617_0%,_#0f172a_100%)] px-4 py-8 text-white">
      <div className="mx-auto max-w-6xl space-y-8">
        <div className="space-y-3 text-center">
          <p className="text-xs uppercase tracking-[0.28em] text-white/55">Evento Vivo</p>
          <h1 className="text-3xl font-semibold md:text-4xl">Galeria Publica</h1>
          <p className="text-sm text-white/70">
            {galleryQuery.data?.meta.total ?? media.length} foto(s) publicadas para este evento.
          </p>
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
          <div className="columns-2 gap-3 space-y-3 md:columns-3 lg:columns-4">
            {media.map((item) => (
              <div key={item.id} className="break-inside-avoid overflow-hidden rounded-3xl border border-white/10 bg-white/5">
                {item.thumbnail_url ? (
                  <img
                    src={item.thumbnail_url}
                    alt={item.caption || item.sender_name}
                    className="w-full object-cover"
                    loading="lazy"
                    decoding="async"
                  />
                ) : (
                  <div className="flex h-48 items-center justify-center text-sm text-white/55">Sem preview</div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
