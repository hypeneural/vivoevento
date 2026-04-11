import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useToast } from '@/hooks/use-toast';
import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import type { EventPublicLinkQrConfig } from '@/modules/qr-code/support/qrTypes';

import { EventPublicLinkQrEditorShell } from './EventPublicLinkQrEditorShell';
import {
  getEventPublicLinkQrEditorQueryKey,
  getEventPublicLinkQrListQueryKey,
  listEventPublicLinkQrEditorStates,
  resetEventPublicLinkQrEditorState,
  uploadEventPublicLinkQrLogoAsset,
  updateEventPublicLinkQrEditorState,
  useEventPublicLinkQrEditorState,
} from './api';

interface EventPublicLinkQrEditorProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  eventId: string | number;
  link: ApiEventPublicLink;
  effectiveBranding?: ApiEventEffectiveBranding | null;
}

export default function EventPublicLinkQrEditor({
  open,
  onOpenChange,
  eventId,
  link,
  effectiveBranding,
}: EventPublicLinkQrEditorProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const queryKey = getEventPublicLinkQrEditorQueryKey(eventId, link.key);
  const listQueryKey = getEventPublicLinkQrListQueryKey(eventId);
  const stateQuery = useEventPublicLinkQrEditorState({
    enabled: open && Boolean(link.qr_value),
    eventId,
    link,
    effectiveBranding,
  });
  const listQuery = useQuery({
    queryKey: listQueryKey,
    enabled: open && Boolean(link.qr_value),
    staleTime: 300_000,
    queryFn: async () => listEventPublicLinkQrEditorStates(eventId),
  });

  const saveMutation = useMutation({
    mutationFn: (config: EventPublicLinkQrConfig) => updateEventPublicLinkQrEditorState(eventId, link.key, config),
    onMutate: async (nextConfig) => {
      await queryClient.cancelQueries({ queryKey });
      await queryClient.cancelQueries({ queryKey: listQueryKey });

      const previousState = queryClient.getQueryData(queryKey);

      if (previousState && typeof previousState === 'object') {
        const optimisticState = {
          ...(previousState as Record<string, unknown>),
          config: nextConfig,
          configSource: 'saved',
          hasSavedConfig: true,
          updatedAt: new Date().toISOString(),
        };

        queryClient.setQueryData(queryKey, optimisticState);

        const list = queryClient.getQueryData(listQueryKey) as Array<Record<string, unknown>> | undefined;
        if (Array.isArray(list)) {
          queryClient.setQueryData(listQueryKey, list.map((item) => (
            item?.linkKey === link.key
              ? {
                ...item,
                config: nextConfig,
                configSource: 'saved',
                hasSavedConfig: true,
                updatedAt: optimisticState.updatedAt,
              }
              : item
          )));
        }
      }

      return { previousState };
    },
    onSuccess: (nextState) => {
      queryClient.setQueryData(queryKey, nextState);
      toast({
        title: 'QR atualizado',
        description: `A configuracao visual de ${link.label} foi salva.`,
      });
    },
    onError: (error: Error, _config, context) => {
      if (context?.previousState) {
        queryClient.setQueryData(queryKey, context.previousState);
      }
      toast({
        title: 'Falha ao salvar QR',
        description: error.message,
        variant: 'destructive',
      });
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey });
      void queryClient.invalidateQueries({ queryKey: listQueryKey });
    },
  });

  const resetMutation = useMutation({
    mutationFn: () => resetEventPublicLinkQrEditorState(eventId, link.key),
    onSuccess: (nextState) => {
      queryClient.setQueryData(queryKey, nextState);
      toast({
        title: 'QR restaurado',
        description: `O estilo padrao de ${link.label} foi reaberto.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao restaurar QR',
        description: error.message,
        variant: 'destructive',
      });
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey });
      void queryClient.invalidateQueries({ queryKey: listQueryKey });
    },
  });

  const uploadLogoMutation = useMutation({
    mutationFn: ({ file, previousPath }: { file: File; previousPath?: string | null }) =>
      uploadEventPublicLinkQrLogoAsset(file, previousPath),
    onSuccess: () => {
      toast({
        title: 'Logo enviada',
        description: 'A nova logo ficou pronta para usar no QR deste link.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao enviar logo',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  if (!stateQuery.data) {
    return null;
  }

  return (
    <EventPublicLinkQrEditorShell
      open={open}
      onOpenChange={onOpenChange}
      state={stateQuery.data}
      availableStyles={listQuery.data ?? []}
      isLoading={stateQuery.isFetching}
      isSaving={saveMutation.isPending}
      isResetting={resetMutation.isPending}
      isUploadingLogo={uploadLogoMutation.isPending}
      onSave={(config) => saveMutation.mutateAsync(config)}
      onResetToDefault={() => resetMutation.mutateAsync()}
      onUploadCustomLogo={(file, previousPath) => uploadLogoMutation.mutateAsync({ file, previousPath })}
    />
  );
}
