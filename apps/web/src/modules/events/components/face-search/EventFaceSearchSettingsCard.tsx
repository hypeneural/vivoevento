import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, ScanFace } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventDetail } from '@/lib/api-types';
import {
  getEventFaceSearchSettings,
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
          <EventFaceSearchSettingsForm
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
