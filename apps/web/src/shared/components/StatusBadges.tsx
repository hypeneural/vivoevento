import { Badge } from '@/components/ui/badge';
import type { EventStatus, MediaStatus, MediaChannel } from '@/shared/types';
import { EVENT_STATUS_LABELS, MEDIA_STATUS_LABELS, CHANNEL_LABELS } from '@/shared/types';

const eventStatusColors: Record<EventStatus, string> = {
  draft: 'bg-muted text-muted-foreground',
  active: 'bg-success/15 text-success border-success/20',
  paused: 'bg-warning/15 text-warning border-warning/20',
  finished: 'bg-primary/15 text-primary border-primary/20',
  archived: 'bg-muted text-muted-foreground',
};

export function EventStatusBadge({ status }: { status: EventStatus }) {
  return (
    <Badge variant="outline" className={`${eventStatusColors[status]} text-xs`}>
      {EVENT_STATUS_LABELS[status]}
    </Badge>
  );
}

const mediaStatusColors: Record<MediaStatus, string> = {
  received: 'bg-accent/15 text-accent border-accent/20',
  processing: 'bg-warning/15 text-warning border-warning/20',
  pending_moderation: 'bg-warning/15 text-warning border-warning/20',
  approved: 'bg-success/15 text-success border-success/20',
  rejected: 'bg-destructive/15 text-destructive border-destructive/20',
  published: 'bg-primary/15 text-primary border-primary/20',
  error: 'bg-destructive/15 text-destructive border-destructive/20',
};

export function MediaStatusBadge({ status }: { status: MediaStatus }) {
  return (
    <Badge variant="outline" className={`${mediaStatusColors[status]} text-xs`}>
      {MEDIA_STATUS_LABELS[status]}
    </Badge>
  );
}

const channelColors: Record<MediaChannel, string> = {
  qrcode: 'bg-primary/15 text-primary border-primary/20',
  link: 'bg-accent/15 text-accent border-accent/20',
  whatsapp: 'bg-success/15 text-success border-success/20',
  upload: 'bg-muted text-muted-foreground',
  telegram: 'bg-accent/15 text-accent border-accent/20',
};

export function ChannelBadge({ channel }: { channel: MediaChannel }) {
  return (
    <Badge variant="outline" className={`${channelColors[channel]} text-xs`}>
      {CHANNEL_LABELS[channel]}
    </Badge>
  );
}
