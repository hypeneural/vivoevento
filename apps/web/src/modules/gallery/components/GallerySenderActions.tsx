import { ShieldBan, ShieldCheck } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { ApiEventMediaDetail, ApiEventMediaItem } from '@/lib/api-types';

const BLOCK_DURATION_OPTIONS = [
  { value: '24h', label: '24h' },
  { value: '7d', label: '7 dias' },
  { value: '30d', label: '30 dias' },
  { value: 'forever', label: 'Sem prazo' },
] as const;

interface GallerySenderActionsProps {
  media: ApiEventMediaItem | ApiEventMediaDetail;
  canManage: boolean;
  busy?: boolean;
  duration: string;
  onDurationChange: (value: string) => void;
  onToggle: (checked: boolean) => void;
  compact?: boolean;
}

export function GallerySenderActions({
  media,
  canManage,
  busy = false,
  duration,
  onDurationChange,
  onToggle,
  compact = false,
}: GallerySenderActionsProps) {
  if (!media.sender_blacklist_enabled) {
    return null;
  }

  return (
    <div className={`flex ${compact ? 'items-center gap-2' : 'flex-col gap-3 rounded-2xl border border-border/60 bg-background/70 p-4'}`}>
      <div className="flex flex-wrap items-center gap-2">
        {media.sender_blocked ? (
          <Badge variant="destructive" className="gap-1">
            <ShieldBan className="h-3.5 w-3.5" />
            Remetente bloqueado
          </Badge>
        ) : (
          <Badge variant="outline" className="gap-1">
            <ShieldCheck className="h-3.5 w-3.5" />
            Remetente ativo
          </Badge>
        )}
      </div>

      <div className={`flex ${compact ? 'items-center gap-2' : 'flex-col gap-2 sm:flex-row sm:items-center sm:justify-between'}`}>
        {!compact ? (
          <Select value={duration} onValueChange={onDurationChange} disabled={!canManage || busy}>
            <SelectTrigger className="w-full sm:w-36">
              <SelectValue placeholder="Bloqueio" />
            </SelectTrigger>
            <SelectContent>
              {BLOCK_DURATION_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : null}

        <Button
          type="button"
          size={compact ? 'sm' : 'default'}
          variant={media.sender_blocked ? 'outline' : 'destructive'}
          disabled={!canManage || busy}
          onClick={() => onToggle(!media.sender_blocked)}
        >
          {media.sender_blocked ? (
            <>
              <ShieldCheck className="h-4 w-4" />
              Desbloquear
            </>
          ) : (
            <>
              <ShieldBan className="h-4 w-4" />
              Bloquear
            </>
          )}
        </Button>
      </div>
    </div>
  );
}
