import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';

import { EventPublicLinkQrEditorShell } from './EventPublicLinkQrEditorShell';
import { useEventPublicLinkQrEditorState } from './api';

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
  const stateQuery = useEventPublicLinkQrEditorState({
    enabled: open && Boolean(link.qr_value),
    eventId,
    link,
    effectiveBranding,
  });

  if (!stateQuery.data) {
    return null;
  }

  return (
    <EventPublicLinkQrEditorShell
      open={open}
      onOpenChange={onOpenChange}
      state={stateQuery.data}
      isLoading={stateQuery.isFetching}
    />
  );
}
