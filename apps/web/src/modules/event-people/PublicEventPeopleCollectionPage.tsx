import { useQuery } from '@tanstack/react-query';
import { Images, Loader2 } from 'lucide-react';
import { useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

import { eventPeopleApi } from './api';

export default function PublicEventPeopleCollectionPage() {
  const { token } = useParams<{ token: string }>();

  const collectionQuery = useQuery({
    queryKey: ['public-event-people-collection', token],
    enabled: !!token,
    retry: false,
    queryFn: () => eventPeopleApi.getPublicRelationalCollection(token as string),
  });

  if (!token) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-[#0b1220] px-6 text-center text-sm text-white/70">
        Link de momentos invalido.
      </div>
    );
  }

  if (collectionQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-[#0b1220]">
        <div className="flex items-center gap-2 text-sm text-white/70">
          <Loader2 className="h-4 w-4 animate-spin" />
          Carregando entrega...
        </div>
      </div>
    );
  }

  if (collectionQuery.isError || !collectionQuery.data) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-[#0b1220] px-6 text-center">
        <div className="max-w-md space-y-3">
          <Images className="mx-auto h-10 w-10 text-white/40" />
          <h1 className="text-xl font-semibold text-white">Entrega indisponivel</h1>
          <p className="text-sm text-white/65">
            Este link nao esta ativo no momento ou o evento ainda nao liberou essa entrega publica.
          </p>
        </div>
      </div>
    );
  }

  const { event, collection } = collectionQuery.data;

  return (
    <div className="min-h-[100dvh] bg-[linear-gradient(180deg,#0f172a_0%,#020617_100%)] px-4 py-6 text-white md:py-8">
      <div className="mx-auto max-w-6xl space-y-8">
        <div className="overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 shadow-2xl">
          <div className="space-y-4 p-6 text-center md:p-10">
            <p className="text-xs uppercase tracking-[0.28em] text-white/55">Evento Vivo</p>
            <div className="space-y-3">
              <h1 className="text-3xl font-semibold md:text-5xl">{collection.display_name}</h1>
              <p className="text-sm text-white/75">{event.title}</p>
              <p className="text-sm text-white/60">{collection.item_count} foto(s) publicadas nesta entrega.</p>
            </div>
            <div className="flex flex-wrap items-center justify-center gap-3">
              {event.public_gallery_url ? (
                <Button asChild className="rounded-full bg-white text-slate-950 hover:bg-white/90">
                  <a href={event.public_gallery_url}>Abrir galeria do evento</a>
                </Button>
              ) : null}
              {event.public_hub_url ? (
                <Button asChild variant="outline" className="rounded-full border-white/20 bg-transparent text-white hover:bg-white/10">
                  <a href={event.public_hub_url}>Voltar para o evento</a>
                </Button>
              ) : null}
            </div>
          </div>
        </div>

        {collection.items.length === 0 ? (
          <Card className="border-white/10 bg-white/5 text-white shadow-none">
            <CardContent className="py-14 text-center text-sm text-white/65">
              Ainda nao existem fotos publicadas nesta entrega.
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {collection.items.map((item) => (
              <article key={item.id} className="overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/5">
                {item.media?.preview_url ? (
                  <img
                    src={item.media.preview_url}
                    alt={item.media.caption || collection.display_name}
                    className="h-72 w-full object-cover"
                    loading="eager"
                    decoding="async"
                  />
                ) : (
                  <div className="flex h-72 items-center justify-center bg-white/5 text-sm text-white/50">
                    Midia #{item.event_media_id}
                  </div>
                )}
                <div className="space-y-2 p-4">
                  <p className="text-sm font-medium">{item.media?.caption || collection.display_name}</p>
                  <p className="text-xs text-white/60">
                    {item.matched_people_count} pessoa(s) reconhecidas nesta foto
                  </p>
                </div>
              </article>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
