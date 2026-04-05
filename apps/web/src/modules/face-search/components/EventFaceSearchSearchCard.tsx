import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { ExternalLink, Search } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { ApiError } from '@/lib/api';
import type { ApiFaceSearchResponse } from '@/lib/api-types';
import { searchEventFaces } from '../api';
import { FaceSearchSearchPanel } from './FaceSearchSearchPanel';

interface EventFaceSearchSearchCardProps {
  eventId: number;
  publicSearchUrl?: string | null;
  enabled: boolean;
}

export function EventFaceSearchSearchCard({
  eventId,
  publicSearchUrl,
  enabled,
}: EventFaceSearchSearchCardProps) {
  const { toast } = useToast();
  const [includePending, setIncludePending] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [lastResponse, setLastResponse] = useState<ApiFaceSearchResponse | null>(null);

  const searchMutation = useMutation({
    mutationFn: ({ file, includePendingValue }: { file: File; includePendingValue: boolean }) =>
      searchEventFaces(eventId, file, includePendingValue),
    onMutate: () => {
      setErrorMessage(null);
    },
    onSuccess: (response) => {
      setLastResponse(response);
      toast({
        title: 'Busca facial concluida',
        description: response.total_results > 0
          ? `${response.total_results} foto(s) encontradas para esta selfie.`
          : 'Nenhuma foto correspondente foi encontrada.',
      });
    },
    onError: (error) => {
      const message = error instanceof ApiError
        ? error.message
        : 'Nao foi possivel executar a busca facial agora.';

      setErrorMessage(message);
      toast({
        title: 'Falha na busca facial',
        description: message,
        variant: 'destructive',
      });
    },
  });

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="pb-2">
        <div className="flex items-start justify-between gap-3">
          <CardTitle className="flex items-center gap-2 text-base">
            <Search className="h-4 w-4 text-primary" />
            Busca interna por selfie
          </CardTitle>
          {publicSearchUrl ? (
            <Button asChild variant="outline" size="sm" className="rounded-full">
              <a href={publicSearchUrl} target="_blank" rel="noreferrer">
                <ExternalLink className="h-4 w-4" />
                Abrir link publico
              </a>
            </Button>
          ) : null}
        </div>
      </CardHeader>
      <CardContent>
        <FaceSearchSearchPanel
          title="Buscar pessoa no evento"
          description="Use uma selfie para localizar fotos ja indexadas deste evento sem sair do backoffice."
          submitLabel="Buscar no evento"
          isPending={searchMutation.isPending}
          includePendingEnabled
          includePending={includePending}
          onIncludePendingChange={setIncludePending}
          disabled={!enabled}
          disabledMessage={!enabled ? 'Ative o FaceSearch do evento para liberar a busca por selfie.' : null}
          requestMeta={lastResponse?.request ?? null}
          results={lastResponse?.results ?? []}
          errorMessage={errorMessage}
          onSubmit={({ file, includePending: includePendingValue }) =>
            searchMutation.mutate({ file, includePendingValue })
          }
        />
      </CardContent>
    </Card>
  );
}
