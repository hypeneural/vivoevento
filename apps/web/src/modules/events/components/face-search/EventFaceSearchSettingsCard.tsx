import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, RefreshCcw, ScanFace, ShieldCheck, Trash2, Wrench } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventDetail, ApiEventFaceSearchSettings } from '@/lib/api-types';
import {
  deleteEventFaceSearchCollection,
  getEventFaceSearchHealth,
  getEventFaceSearchSettings,
  reconcileEventFaceSearch,
  reindexEventFaceSearch,
  updateEventFaceSearchSettings,
  type UpdateEventFaceSearchSettingsPayload,
} from '../../api';
import { EventFaceSearchSettingsForm } from './EventFaceSearchSettingsForm';

interface EventFaceSearchSettingsCardProps {
  eventId: number;
  eventModerationMode: ApiEventDetail['moderation_mode'];
}

export function EventFaceSearchSettingsCard({
  eventId,
  eventModerationMode,
}: EventFaceSearchSettingsCardProps) {
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const settingsQuery = useQuery({
    queryKey: ['event-face-search-settings', eventId],
    queryFn: () => getEventFaceSearchSettings(eventId),
  });

  const updateMutation = useMutation({
    mutationFn: (payload: UpdateEventFaceSearchSettingsPayload) =>
      updateEventFaceSearchSettings(eventId, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-face-search-settings', eventId] });
      await queryClient.invalidateQueries({ queryKey: ['event-detail', String(eventId)] });
      toast({
        title: 'FaceSearch atualizado',
        description: 'As configuracoes de indexacao facial foram salvas.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao salvar FaceSearch',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const healthMutation = useMutation({
    mutationFn: () => getEventFaceSearchHealth(eventId),
    onSuccess: (data) => {
      toast({
        title: 'Health check concluido',
        description: `Backend ${data.backend_key} retornou status ${data.status}.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha no health check',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const reindexMutation = useMutation({
    mutationFn: () => reindexEventFaceSearch(eventId),
    onSuccess: async (data) => {
      await queryClient.invalidateQueries({ queryKey: ['event-face-search-settings', eventId] });
      toast({
        title: 'Reindexacao iniciada',
        description: `${data.queued_media_count ?? 0} imagens entraram na fila de face-index.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao reindexar FaceSearch',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const reconcileMutation = useMutation({
    mutationFn: () => reconcileEventFaceSearch(eventId),
    onSuccess: () => {
      toast({
        title: 'Reconciliacao enfileirada',
        description: 'A reconciliacao da collection AWS foi enviada para a fila operacional.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao reconciliar collection',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const deleteCollectionMutation = useMutation({
    mutationFn: () => deleteEventFaceSearchCollection(eventId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-face-search-settings', eventId] });
      await queryClient.invalidateQueries({ queryKey: ['event-detail', String(eventId)] });
      toast({
        title: 'Collection AWS removida',
        description: 'A collection remota foi apagada e os metadados locais foram atualizados.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao apagar collection AWS',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const renderOperations = (settings: ApiEventFaceSearchSettings) => {
    const awsOperationalContext = settings.search_backend_key === 'aws_rekognition'
      || settings.recognition_enabled
      || Boolean(settings.aws_collection_id);
    const collectionProvisioned = Boolean(settings.aws_collection_id);

    return (
      <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Wrench className="h-4 w-4 text-primary" />
              <p className="text-sm font-semibold text-foreground">Operacao AWS</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={collectionProvisioned ? 'default' : 'secondary'}>
                {collectionProvisioned ? 'Collection provisionada' : 'Collection ausente'}
              </Badge>
              <Badge variant={awsOperationalContext ? 'outline' : 'secondary'}>
                {awsOperationalContext ? 'AWS operacional' : 'Somente lane local'}
              </Badge>
            </div>
            <p className="text-sm text-muted-foreground">
              Health, reindex, reconcile e teardown ficam disponiveis no painel sem editar banco manualmente.
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => healthMutation.mutate()}
              disabled={healthMutation.isPending}
            >
              <ShieldCheck className="mr-1.5 h-4 w-4" />
              {healthMutation.isPending ? 'Rodando health...' : 'Rodar health'}
            </Button>

            <Button
              type="button"
              variant="outline"
              onClick={() => reindexMutation.mutate()}
              disabled={reindexMutation.isPending}
            >
              <RefreshCcw className="mr-1.5 h-4 w-4" />
              {reindexMutation.isPending ? 'Enfileirando...' : 'Reindexar evento'}
            </Button>

            {awsOperationalContext ? (
              <Button
                type="button"
                variant="outline"
                onClick={() => reconcileMutation.mutate()}
                disabled={reconcileMutation.isPending}
              >
                <RefreshCcw className="mr-1.5 h-4 w-4" />
                {reconcileMutation.isPending ? 'Enfileirando...' : 'Reconciliar collection'}
              </Button>
            ) : null}

            {collectionProvisioned ? (
              <Button
                type="button"
                variant="destructive"
                onClick={() => deleteCollectionMutation.mutate()}
                disabled={deleteCollectionMutation.isPending}
              >
                <Trash2 className="mr-1.5 h-4 w-4" />
                {deleteCollectionMutation.isPending ? 'Apagando...' : 'Apagar collection AWS'}
              </Button>
            ) : null}
          </div>
        </div>

        {healthMutation.data ? (
          <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <p className="font-medium text-foreground">Health mais recente</p>
            <div className="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Backend</p>
                <p className="font-medium">{healthMutation.data.backend_key}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Status</p>
                <p className="font-medium">{healthMutation.data.status}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Collection</p>
                <p className="font-medium">{healthMutation.data.collection?.collection_id ?? 'n/a'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Ultimo check</p>
                <p className="font-medium">{healthMutation.data.checked_at}</p>
              </div>
            </div>
            {healthMutation.data.error_message ? (
              <p className="mt-3 text-sm text-destructive">{healthMutation.data.error_message}</p>
            ) : null}
          </div>
        ) : null}
      </div>
    );
  };

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center gap-2 text-base">
          <ScanFace className="h-4 w-4 text-primary" />
          FaceSearch por evento
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {settingsQuery.isLoading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando configuracoes de FaceSearch...
          </div>
        ) : settingsQuery.isError || !settingsQuery.data ? (
          <div className="rounded-2xl border border-dashed border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
            Nao foi possivel carregar os settings de FaceSearch deste evento.
          </div>
        ) : (
          <>
            {renderOperations(settingsQuery.data)}
            <EventFaceSearchSettingsForm
              settings={settingsQuery.data}
              eventModerationMode={eventModerationMode}
              isPending={updateMutation.isPending}
              onSubmit={(payload) => updateMutation.mutate(payload)}
            />
          </>
        )}
      </CardContent>
    </Card>
  );
}
