import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Loader2, Search } from 'lucide-react';
import { useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';

import { getPublicHub } from './api';
import { HubRenderer } from './HubRenderer';

export default function PublicHubPage() {
  const { slug } = useParams<{ slug: string }>();

  const hubQuery = useQuery({
    queryKey: ['public-hub', slug],
    enabled: !!slug,
    retry: false,
    queryFn: () => getPublicHub(slug as string),
  });

  if (!slug) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 px-6 text-center text-white">
        Link da pagina de links invalido.
      </div>
    );
  }

  if (hubQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950">
        <div className="flex items-center gap-2 text-sm text-white/70">
          <Loader2 className="h-4 w-4 animate-spin" />
          Carregando pagina de links...
        </div>
      </div>
    );
  }

  if (hubQuery.isError || !hubQuery.data) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 px-6 text-center">
        <div className="max-w-sm space-y-3">
          <AlertTriangle className="mx-auto h-10 w-10 text-amber-400" />
          <h1 className="text-xl font-semibold text-white">Pagina de links indisponivel</h1>
          <p className="text-sm text-white/65">Este link nao esta ativo no momento.</p>
        </div>
      </div>
    );
  }

  const { event, hub } = hubQuery.data;
  const publicFaceSearch = hubQuery.data.face_search;

  return (
    <div className="min-h-[100dvh] bg-slate-950">
      {publicFaceSearch.public_search_enabled && publicFaceSearch.find_me_url ? (
        <div className="mx-auto w-full max-w-md px-4 pt-4">
          <div className="rounded-[2rem] border border-emerald-400/25 bg-emerald-500/10 p-4 text-white">
            <p className="text-sm font-semibold">Quer encontrar suas fotos mais rapido?</p>
            <p className="mt-1 text-sm text-emerald-50/80">
              Envie uma selfie e veja as fotos publicadas em que voce aparece.
            </p>
            <Button asChild className="mt-3 rounded-full bg-emerald-500 text-white hover:bg-emerald-400">
              <a href={publicFaceSearch.find_me_url}>
                <Search className="h-4 w-4" />
                Encontrar minhas fotos
              </a>
            </Button>
          </div>
        </div>
      ) : null}

      <HubRenderer
        event={event}
        hub={hub}
        className="min-h-[100dvh]"
        innerClassName="min-h-[100dvh]"
      />
    </div>
  );
}
