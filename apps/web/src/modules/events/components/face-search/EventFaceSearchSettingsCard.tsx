import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CircleAlert, Loader2, RefreshCcw, ScanFace, ShieldCheck, Trash2, Wrench } from 'lucide-react';

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { resolveEventFaceSearchOperationalStatus } from '../../face-search-status';
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
        title: 'Reconhecimento facial atualizado',
        description: 'As configuracoes do reconhecimento facial foram salvas.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao salvar o reconhecimento facial',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const healthMutation = useMutation({
    mutationFn: () => getEventFaceSearchHealth(eventId),
    onSuccess: (data) => {
      toast({
        title: 'Verificacao concluida',
        description: `A leitura da AWS terminou com situacao ${data.status}.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha na verificacao',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const reindexMutation = useMutation({
    mutationFn: () => reindexEventFaceSearch(eventId),
    onSuccess: async (data) => {
      await queryClient.invalidateQueries({ queryKey: ['event-face-search-settings', eventId] });
      await queryClient.invalidateQueries({ queryKey: ['event-detail', String(eventId)] });
      toast({
        title: 'Reindexacao iniciada',
        description: `${data.queued_media_count ?? 0} imagens entraram na fila de preparacao.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao preparar fotos antigas',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const reconcileMutation = useMutation({
    mutationFn: () => reconcileEventFaceSearch(eventId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-face-search-settings', eventId] });
      await queryClient.invalidateQueries({ queryKey: ['event-detail', String(eventId)] });
      toast({
        title: 'Conferencia enfileirada',
        description: 'A verificacao da estrutura AWS foi enviada para a fila operacional.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao conferir a indexacao',
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
        title: 'Estrutura AWS removida',
        description: 'A estrutura remota foi apagada e os metadados locais foram atualizados.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao apagar a estrutura AWS',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const operationalStatus = settingsQuery.data
    ? resolveEventFaceSearchOperationalStatus(settingsQuery.data)
    : null;

  const renderOperations = (settings: ApiEventFaceSearchSettings) => {
    const awsOperationalContext = settings.search_backend_key === 'aws_rekognition'
      || settings.recognition_enabled
      || Boolean(settings.aws_collection_id);
    const collectionProvisioned = Boolean(settings.aws_collection_id);
    const healthStatusLabel = healthMutation.data?.status === 'healthy'
      ? 'Saudavel'
      : healthMutation.data?.status === 'degraded'
        ? 'Instavel'
        : healthMutation.data?.status === 'unhealthy'
          ? 'Com falha'
          : healthMutation.data?.status ?? 'Sem leitura';

    return (
      <div className="space-y-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Wrench className="h-4 w-4 text-primary" />
              <p className="text-sm font-semibold text-foreground">Ferramentas tecnicas</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={collectionProvisioned ? 'default' : 'secondary'}>
                {collectionProvisioned ? 'Estrutura AWS pronta' : 'Estrutura AWS pendente'}
              </Badge>
              <Badge variant={awsOperationalContext ? 'outline' : 'secondary'}>
                {awsOperationalContext ? 'Busca principal na AWS' : 'Busca local apenas'}
              </Badge>
            </div>
            <p className="text-sm text-muted-foreground">
              Use este bloco apenas quando precisar verificar a AWS ou refazer a preparacao das fotos antigas.
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
              {healthMutation.isPending ? 'Verificando...' : 'Verificar AWS'}
            </Button>

            <Button
              type="button"
              variant="outline"
              onClick={() => reindexMutation.mutate()}
              disabled={reindexMutation.isPending}
            >
              <RefreshCcw className="mr-1.5 h-4 w-4" />
              {reindexMutation.isPending ? 'Enfileirando...' : 'Preparar fotos antigas'}
            </Button>

            {awsOperationalContext ? (
              <Button
                type="button"
                variant="outline"
                onClick={() => reconcileMutation.mutate()}
                disabled={reconcileMutation.isPending}
              >
                <RefreshCcw className="mr-1.5 h-4 w-4" />
                {reconcileMutation.isPending ? 'Enfileirando...' : 'Conferir indexacao'}
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
                {deleteCollectionMutation.isPending ? 'Apagando...' : 'Apagar estrutura AWS'}
              </Button>
            ) : null}
          </div>
        </div>

        {healthMutation.data ? (
          <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <p className="font-medium text-foreground">Ultima verificacao</p>
            <div className="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Motor principal</p>
                <p className="font-medium">{healthMutation.data.backend_key}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Situacao</p>
                <p className="font-medium">{healthStatusLabel}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Estrutura</p>
                <p className="font-medium">{healthMutation.data.collection?.collection_id ?? 'n/a'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Ultima verificacao</p>
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
          Reconhecimento facial do evento
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {settingsQuery.isLoading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando configuracoes do reconhecimento facial...
          </div>
        ) : settingsQuery.isError || !settingsQuery.data ? (
          <div className="rounded-2xl border border-dashed border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
            Nao foi possivel carregar as configuracoes do reconhecimento facial deste evento.
          </div>
        ) : (
          <>
            {operationalStatus ? (
              <Alert className="border-slate-200 bg-slate-50">
                <CircleAlert className="h-4 w-4" />
                <AlertTitle>{operationalStatus.label}</AlertTitle>
                <AlertDescription>
                  <p>{operationalStatus.description}</p>
                  <p className="mt-2 text-muted-foreground">
                    Use os controles abaixo para ligar a busca e liberar convidados. Ferramentas tecnicas ficam recolhidas.
                  </p>
                </AlertDescription>
              </Alert>
            ) : null}
            <EventFaceSearchSettingsForm
              settings={settingsQuery.data}
              eventModerationMode={eventModerationMode}
              isPending={updateMutation.isPending}
              onSubmit={(payload) => updateMutation.mutate(payload)}
            />
            <Accordion type="single" collapsible className="rounded-2xl border border-slate-200 bg-slate-50 px-4">
              <AccordionItem value="advanced-operations" className="border-b-0">
                <AccordionTrigger className="py-3 text-sm font-medium hover:no-underline">
                  Ferramentas tecnicas e diagnostico
                </AccordionTrigger>
                <AccordionContent className="pb-4 pt-1">
                  {renderOperations(settingsQuery.data)}
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </>
        )}
      </CardContent>
    </Card>
  );
}
