import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useParams } from 'react-router-dom';

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
        Link do hub invalido.
      </div>
    );
  }

  if (hubQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950">
        <div className="flex items-center gap-2 text-sm text-white/70">
          <Loader2 className="h-4 w-4 animate-spin" />
          Carregando hub...
        </div>
      </div>
    );
  }

  if (hubQuery.isError || !hubQuery.data) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 px-6 text-center">
        <div className="max-w-sm space-y-3">
          <AlertTriangle className="mx-auto h-10 w-10 text-amber-400" />
          <h1 className="text-xl font-semibold text-white">Hub indisponivel</h1>
          <p className="text-sm text-white/65">Este link nao esta ativo no momento.</p>
        </div>
      </div>
    );
  }

  const { event, hub } = hubQuery.data;

  return (
    <HubRenderer
      event={event}
      hub={hub}
      className="min-h-[100dvh]"
      innerClassName="min-h-[100dvh]"
    />
  );
}
