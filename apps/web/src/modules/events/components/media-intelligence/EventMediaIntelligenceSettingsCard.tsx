import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BrainCircuit, Loader2 } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventDetail } from '@/lib/api-types';
import { aiMediaRepliesService } from '@/modules/ai/api';
import {
  getEventMediaIntelligenceSettings,
  updateEventMediaIntelligenceSettings,
  type UpdateEventMediaIntelligenceSettingsPayload,
} from '../../api';
import { EventMediaIntelligenceSettingsForm } from './EventMediaIntelligenceSettingsForm';

interface EventMediaIntelligenceSettingsCardProps {
  eventId: number;
  eventModerationMode: ApiEventDetail['moderation_mode'];
}

export function EventMediaIntelligenceSettingsCard({
  eventId,
  eventModerationMode,
}: EventMediaIntelligenceSettingsCardProps) {
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const settingsQuery = useQuery({
    queryKey: ['event-media-intelligence-settings', eventId],
    queryFn: () => getEventMediaIntelligenceSettings(eventId),
  });
  const presetsQuery = useQuery({
    queryKey: ['ia-media-reply-presets', 'event-form'],
    queryFn: () => aiMediaRepliesService.listPresets(),
  });

  const updateMutation = useMutation({
    mutationFn: (payload: UpdateEventMediaIntelligenceSettingsPayload) =>
      updateEventMediaIntelligenceSettings(eventId, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-media-intelligence-settings', eventId] });
      await queryClient.invalidateQueries({ queryKey: ['event-detail', String(eventId)] });
      toast({
        title: 'VLM atualizado',
        description: 'As configuracoes de MediaIntelligence foram salvas.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao salvar VLM',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center gap-2 text-base">
          <BrainCircuit className="h-4 w-4 text-primary" />
          MediaIntelligence por evento
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {settingsQuery.isLoading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando configuracoes de VLM...
          </div>
        ) : settingsQuery.isError || !settingsQuery.data ? (
          <div className="rounded-2xl border border-dashed border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
            Nao foi possivel carregar os settings de MediaIntelligence deste evento.
          </div>
        ) : (
          <EventMediaIntelligenceSettingsForm
            settings={settingsQuery.data}
            eventModerationMode={eventModerationMode}
            presets={presetsQuery.data ?? []}
            isPending={updateMutation.isPending}
            onSubmit={(payload) => updateMutation.mutate(payload)}
          />
        )}
      </CardContent>
    </Card>
  );
}
