import type { MouseEvent } from 'react';
import { Check, CheckCircle2, Copy, ImageIcon, Loader2, Pin, ShieldBan, Star, X, XCircle } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { ApiEventMediaItem } from '@/lib/api-types';
import { ChannelBadge, MediaStatusBadge } from '@/shared/components/StatusBadges';
import { cn } from '@/lib/utils';

import { formatShortTime, getOrientationLabel, isVideoAsset } from '../utils';

export type ModerationMediaAction = 'approve' | 'reject' | 'favorite' | 'pin';

interface MediaActionButtonProps {
  label: string;
  icon: typeof Check;
  active?: boolean;
  busy?: boolean;
  disabled?: boolean;
  onClick: () => void;
  tone?: 'approve' | 'reject' | 'favorite' | 'pin';
}

function MediaActionButton({
  label,
  icon: Icon,
  active = false,
  busy = false,
  disabled = false,
  onClick,
  tone = 'approve',
}: MediaActionButtonProps) {
  const toneClassName = tone === 'approve'
    ? 'border-emerald-200/70 text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10'
    : tone === 'reject'
      ? 'border-rose-200/70 text-rose-700 hover:border-rose-300 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10'
      : tone === 'favorite'
        ? active
          ? 'border-amber-300 bg-amber-500 text-white hover:bg-amber-500/90'
          : 'border-amber-200/70 text-amber-700 hover:border-amber-300 hover:bg-amber-50 dark:border-amber-500/30 dark:text-amber-300 dark:hover:bg-amber-500/10'
        : active
          ? 'border-sky-300 bg-sky-600 text-white hover:bg-sky-600/90'
          : 'border-sky-200/70 text-sky-700 hover:border-sky-300 hover:bg-sky-50 dark:border-sky-500/30 dark:text-sky-300 dark:hover:bg-sky-500/10';

  return (
    <Button
      type="button"
      size="sm"
      variant="outline"
      className={cn('justify-start rounded-2xl border bg-background/80 text-xs', toneClassName)}
      disabled={disabled || busy}
      onClick={onClick}
    >
      {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Icon className="h-4 w-4" />}
      {label}
    </Button>
  );
}

function getStatusBorderClass(media: ApiEventMediaItem) {
  if (media.status === 'rejected') {
    return 'border-rose-500/70 shadow-rose-500/10';
  }

  if (media.status === 'approved' || media.status === 'published') {
    return 'border-emerald-500/70 shadow-emerald-500/10';
  }

  if (media.status === 'pending_moderation') {
    return 'border-amber-400/70 shadow-amber-500/10';
  }

  return 'border-border/60 shadow-black/5';
}

interface ModerationMediaCardProps {
  media: ApiEventMediaItem;
  focused: boolean;
  checked: boolean;
  canModerate: boolean;
  isBusy: (action?: ModerationMediaAction) => boolean;
  onOpen: () => void;
  onToggleChecked: (event: MouseEvent<HTMLElement>) => void;
  onAction: (action: ModerationMediaAction) => void;
}

export function ModerationMediaCard({
  media,
  focused,
  checked,
  canModerate,
  isBusy,
  onOpen,
  onToggleChecked,
  onAction,
}: ModerationMediaCardProps) {
  const canApprove = canModerate && media.status !== 'approved' && media.status !== 'published';
  const canReject = canModerate && media.status !== 'rejected';
  const showsVideoPreview = isVideoAsset(media, media.thumbnail_url);

  return (
    <article
      id={`moderation-media-${media.id}`}
      data-media-card-id={media.id}
      className={cn(
        'group flex h-[30rem] flex-col overflow-hidden rounded-[28px] border bg-background/92 shadow-sm transition-all',
        getStatusBorderClass(media),
        focused && 'ring-2 ring-primary/25',
        checked && 'ring-2 ring-primary/55 ring-offset-2 ring-offset-background',
      )}
    >
      <div className="relative overflow-hidden bg-muted">
        <button type="button" className="block h-60 w-full text-left sm:h-64" onClick={onOpen}>
          {media.thumbnail_url ? (
            showsVideoPreview ? (
              <video
                src={media.preview_url || media.thumbnail_url}
                className="h-full w-full object-cover"
                muted
                playsInline
                preload="metadata"
              />
            ) : (
              <img
                src={media.thumbnail_url}
                alt={media.caption || media.event_title || 'Midia do evento'}
                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.015]"
                loading="lazy"
                decoding="async"
                sizes="(max-width: 640px) 100vw, (max-width: 1536px) 50vw, 33vw"
              />
            )
          ) : (
            <div className="flex h-60 items-center justify-center bg-gradient-to-br from-slate-200 via-slate-100 to-white text-slate-500 dark:from-slate-900 dark:via-slate-800 dark:to-slate-700 sm:h-64">
              <ImageIcon className="h-10 w-10" />
            </div>
          )}
        </button>

        <div className="absolute left-3 top-3 z-10 rounded-full border border-white/70 bg-black/45 p-1.5 text-white backdrop-blur">
          <Checkbox
            checked={checked}
            className="border-white bg-transparent data-[state=checked]:bg-white data-[state=checked]:text-black"
            aria-label={checked ? 'Desmarcar midia' : 'Selecionar midia'}
            onClick={onToggleChecked}
          />
        </div>

        <div className="pointer-events-none absolute inset-x-0 top-0 flex items-center justify-end gap-1 p-3">
          {media.status === 'approved' || media.status === 'published' ? (
            <Badge className="border-0 bg-emerald-600/95 text-white">
              <CheckCircle2 className="h-3.5 w-3.5" />
            </Badge>
          ) : null}
          {media.status === 'rejected' ? (
            <Badge className="border-0 bg-rose-600/95 text-white">
              <XCircle className="h-3.5 w-3.5" />
            </Badge>
          ) : null}
          {media.is_featured ? (
            <Badge className="border-0 bg-amber-500/95 text-white">
              <Star className="h-3.5 w-3.5 fill-current" />
            </Badge>
          ) : null}
          {media.is_pinned ? (
            <Badge className="border-0 bg-sky-600/95 text-white">
              <Pin className="h-3.5 w-3.5" />
            </Badge>
          ) : null}
        </div>
      </div>

      <div className="flex flex-1 flex-col p-4">
        <div className="min-h-0 space-y-2">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold">{media.event_title || 'Evento sem titulo'}</p>
              <p className="truncate text-xs text-muted-foreground">{media.sender_name || 'Convidado'}</p>
            </div>
            <span className="shrink-0 text-[11px] font-medium text-muted-foreground">
              {formatShortTime(media.created_at)}
            </span>
          </div>

          <div className="flex flex-wrap gap-2">
            <MediaStatusBadge status={media.status as never} />
            <ChannelBadge channel={media.channel as never} />
            <Badge variant="outline">{getOrientationLabel(media.orientation)}</Badge>
            {media.sender_blocked ? (
              <Badge variant="outline" className="border-rose-300/60 bg-rose-500/10 text-rose-700 dark:text-rose-300">
                <ShieldBan className="h-3.5 w-3.5" />
                Remetente bloqueado
              </Badge>
            ) : null}
            {media.is_duplicate_candidate ? (
              <Badge variant="outline" className="border-amber-300/60 bg-amber-500/10 text-amber-700 dark:text-amber-300">
                <Copy className="h-3.5 w-3.5" />
                Possivel duplicata
              </Badge>
            ) : null}
          </div>

          {media.caption ? (
            <p className="line-clamp-2 text-sm text-muted-foreground">{media.caption}</p>
          ) : (
            <p className="text-sm text-muted-foreground">Sem legenda enviada.</p>
          )}
        </div>

        <div className="mt-auto grid grid-cols-2 gap-2 pt-4">
          <MediaActionButton
            label="Aprovar"
            icon={Check}
            tone="approve"
            busy={isBusy('approve')}
            disabled={!canApprove}
            onClick={() => onAction('approve')}
          />
          <MediaActionButton
            label="Reprovar"
            icon={X}
            tone="reject"
            busy={isBusy('reject')}
            disabled={!canReject}
            onClick={() => onAction('reject')}
          />
          <MediaActionButton
            label={media.is_featured ? 'Favorita' : 'Favoritar'}
            icon={Star}
            tone="favorite"
            active={!!media.is_featured}
            busy={isBusy('favorite')}
            disabled={!canModerate}
            onClick={() => onAction('favorite')}
          />
          <MediaActionButton
            label={media.is_pinned ? 'Fixada' : 'Fixar'}
            icon={Pin}
            tone="pin"
            active={!!media.is_pinned}
            busy={isBusy('pin')}
            disabled={!canModerate}
            onClick={() => onAction('pin')}
          />
        </div>
      </div>
    </article>
  );
}

export { MediaActionButton };
