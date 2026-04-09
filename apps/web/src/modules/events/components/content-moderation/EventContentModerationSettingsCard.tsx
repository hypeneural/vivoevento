import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, ShieldCheck } from 'lucide-react';
import { Link } from 'react-router-dom';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventDetail } from '@/lib/api-types';
import {
  getEventContentModerationSettings,
  updateEventContentModerationSettings,
  type UpdateEventContentModerationSettingsPayload,
} from '../../api';
import { EventContentModerationSettingsForm } from './EventContentModerationSettingsForm';

interface EventContentModerationSettingsCardProps {
  eventId: number;
  eventModerationMode: ApiEventDetail['moderation_mode'];
}

export function EventContentModerationSettingsCard({
  eventId,
  eventModerationMode,
}: EventContentModerationSettingsCardProps) {
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const settingsQuery = useQuery({
    queryKey: ['event-content-moderation-settings', eventId],
    queryFn: () => getEventContentModerationSettings(eventId),
  });

  const updateMutation = useMutation({
    mutationFn: (payload: UpdateEventContentModerationSettingsPayload) =>
      updateEventContentModerationSettings(eventId, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-content-moderation-settings', eventId] });
      await queryClient.invalidateQueries({ queryKey: ['event-detail', String(eventId)] });
      toast({
        title: 'Safety atualizado',
        description: 'As configuracoes de moderation por IA foram salvas.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao salvar safety',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center gap-2 text-base">
          <ShieldCheck className="h-4 w-4 text-primary" />
          Safety por evento
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
          <p className="font-medium text-foreground">Administração principal em IA &gt; Moderação de mídia</p>
          <p className="mt-1">
            Use esta seção para override por evento. A política global e o histórico operacional ficam centralizados em
            {' '}
            <Link to="/ia/moderacao-de-midia" className="font-medium text-primary underline underline-offset-4">
              IA &gt; Moderação de mídia
            </Link>
            .
          </p>
        </div>

        {settingsQuery.isLoading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando configuracoes de safety...
          </div>
        ) : settingsQuery.isError || !settingsQuery.data ? (
          <div className="rounded-2xl border border-dashed border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
            Nao foi possivel carregar os settings de safety deste evento.
          </div>
        ) : (
          <EventContentModerationSettingsForm
            settings={settingsQuery.data}
            eventModerationMode={eventModerationMode}
            isPending={updateMutation.isPending}
            onSubmit={(payload) => updateMutation.mutate(payload)}
          />
        )}
      </CardContent>
    </Card>
  );
}
