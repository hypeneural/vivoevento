import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useParams } from 'react-router-dom';

import { Card, CardContent } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { ApiError } from '@/lib/api';
import type { ApiFaceSearchResponse } from '@/lib/api-types';
import { resolveAssetUrl } from '@/lib/assets';
import { getPublicFaceSearchBootstrap, searchPublicEventFaces } from './api';
import { FaceSearchSearchPanel } from './components/FaceSearchSearchPanel';

export default function PublicFaceSearchPage() {
  const { slug } = useParams<{ slug: string }>();
  const { toast } = useToast();
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [lastResponse, setLastResponse] = useState<ApiFaceSearchResponse | null>(null);

  const bootstrapQuery = useQuery({
    queryKey: ['public-face-search', slug],
    enabled: !!slug,
    retry: false,
    queryFn: () => getPublicFaceSearchBootstrap(slug as string),
  });

  const searchMutation = useMutation({
    mutationFn: ({ file, consentVersion }: { file: File; consentVersion: string }) =>
      searchPublicEventFaces(slug as string, file, consentVersion),
    onMutate: () => {
      setErrorMessage(null);
    },
    onSuccess: (response) => {
      setLastResponse(response);
      toast({
        title: 'Busca concluida',
        description: response.total_results > 0
          ? `${response.total_results} foto(s) publicadas encontradas.`
          : 'Ainda nao encontramos fotos publicadas para esta selfie.',
      });
    },
    onError: (error) => {
      const message = error instanceof ApiError
        ? error.message
        : 'Nao foi possivel executar a busca agora.';

      setErrorMessage(message);
      toast({
        title: 'Falha na busca',
        description: message,
        variant: 'destructive',
      });
    },
  });

  if (!slug) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6 text-center">
        <div className="space-y-3">
          <AlertTriangle className="mx-auto h-10 w-10 text-destructive" />
          <h1 className="text-xl font-semibold">Link invalido</h1>
          <p className="text-sm text-muted-foreground">Confirme o endereco do evento e tente novamente.</p>
        </div>
      </div>
    );
  }

  if (bootstrapQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background">
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Preparando a busca...
        </div>
      </div>
    );
  }

  if (bootstrapQuery.isError || !bootstrapQuery.data) {
    const message = bootstrapQuery.error instanceof ApiError
      ? bootstrapQuery.error.message
      : 'Este link nao esta disponivel no momento.';

    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6 text-center">
        <div className="space-y-3">
          <AlertTriangle className="mx-auto h-10 w-10 text-destructive" />
          <h1 className="text-xl font-semibold">Busca indisponivel</h1>
          <p className="text-sm text-muted-foreground">{message}</p>
        </div>
      </div>
    );
  }

  const { event, search, links } = bootstrapQuery.data;
  const coverUrl = event.cover_image_url || resolveAssetUrl(event.cover_image_path);
  const logoUrl = event.logo_url || resolveAssetUrl(event.logo_path);
  const primaryColor = event.primary_color || '#0f766e';
  const secondaryColor = event.secondary_color || '#0f172a';

  return (
    <div
      className="min-h-[100dvh] bg-background pb-12"
      style={{
        backgroundImage: `radial-gradient(circle at top, ${primaryColor}1f 0%, transparent 36%), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%)`,
      }}
    >
      <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6">
        <Card className="overflow-hidden border-0 shadow-xl shadow-slate-200/70">
          <div
            className="relative px-6 py-8 text-white"
            style={{ background: `linear-gradient(145deg, ${primaryColor}, ${secondaryColor})` }}
          >
            {coverUrl ? (
              <img
                src={coverUrl}
                alt={event.title}
                className="absolute inset-0 h-full w-full object-cover opacity-20"
              />
            ) : null}
            <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-black/10 to-black/45" />
            <div className="relative space-y-4">
              <div className="flex items-start justify-between gap-4">
                <div className="space-y-2">
                  <p className="text-xs uppercase tracking-[0.24em] text-white/70">Evento Vivo</p>
                  <h1 className="text-3xl font-semibold">{event.title}</h1>
                  <p className="max-w-2xl text-sm text-white/80">{search.message}</p>
                </div>
                {logoUrl ? (
                  <img
                    src={logoUrl}
                    alt="Logo do evento"
                    className="h-14 w-14 rounded-2xl border border-white/20 bg-white/90 object-cover p-2 shadow-lg"
                  />
                ) : null}
              </div>
              <div className="grid gap-3 md:grid-cols-3">
                <div className="rounded-2xl bg-white/12 p-3 backdrop-blur">
                  <p className="text-[11px] uppercase tracking-[0.16em] text-white/70">Status</p>
                  <p className="mt-1 text-sm font-medium">{search.status}</p>
                </div>
                <div className="rounded-2xl bg-white/12 p-3 backdrop-blur">
                  <p className="text-[11px] uppercase tracking-[0.16em] text-white/70">Retencao</p>
                  <p className="mt-1 text-sm font-medium">{search.selfie_retention_hours} horas</p>
                </div>
                <div className="rounded-2xl bg-white/12 p-3 backdrop-blur">
                  <p className="text-[11px] uppercase tracking-[0.16em] text-white/70">Resultados</p>
                  <p className="mt-1 text-sm font-medium">Ate {search.top_k} fotos publicadas</p>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <Card className="border-white/70 bg-white/90 shadow-sm">
          <CardContent className="p-6">
            <FaceSearchSearchPanel
              title="Encontrar minhas fotos"
              description={search.instructions}
              submitLabel="Buscar minhas fotos"
              isPending={searchMutation.isPending}
              requireConsent={search.consent_required}
              consentLabel="Autorizo o uso temporario da selfie apenas para localizar minhas fotos publicadas neste evento."
              disabled={!search.enabled}
              disabledMessage={!search.enabled ? search.message : null}
              requestMeta={lastResponse?.request ?? null}
              results={lastResponse?.results ?? []}
              errorMessage={errorMessage}
              onSubmit={({ file }) => searchMutation.mutate({ file, consentVersion: search.consent_version })}
            />
          </CardContent>
        </Card>

        <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
          {links.gallery_url ? (
            <a href={links.gallery_url} className="underline-offset-4 hover:underline" target="_blank" rel="noreferrer">
              Abrir galeria publica
            </a>
          ) : null}
          {links.hub_url ? (
            <a href={links.hub_url} className="underline-offset-4 hover:underline" target="_blank" rel="noreferrer">
              Voltar ao hub do evento
            </a>
          ) : null}
        </div>
      </div>
    </div>
  );
}
