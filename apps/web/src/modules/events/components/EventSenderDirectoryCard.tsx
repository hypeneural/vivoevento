import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertCircle, ArrowUpRight, Camera, Clock3, ShieldBan } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { buildSenderScopedPath } from '@/lib/sender-filters';

import type { EventIntakeBlacklistSenderSummary } from '../intake';
import {
  formatSenderDateTime,
  identityTypeLabel,
  initialsFromName,
  senderPrimaryLabel,
  senderSecondaryLabel,
} from '../sender-utils';

const BLOCK_DURATION_OPTIONS = [
  { value: '24h', label: '24h' },
  { value: '7d', label: '7 dias' },
  { value: '30d', label: '30 dias' },
  { value: 'forever', label: 'Sem prazo' },
] as const;

interface EventSenderDirectoryCardProps {
  eventId: number;
  senders: EventIntakeBlacklistSenderSummary[];
  canManageBlacklist: boolean;
  isBusy?: (sender: EventIntakeBlacklistSenderSummary) => boolean;
  onToggleBlock?: (
    sender: EventIntakeBlacklistSenderSummary,
    checked: boolean,
    duration: string,
  ) => void;
}

export function EventSenderDirectoryCard({
  eventId,
  senders,
  canManageBlacklist,
  isBusy,
  onToggleBlock,
}: EventSenderDirectoryCardProps) {
  const [durations, setDurations] = useState<Record<string, string>>({});

  const sortedSenders = useMemo(
    () => [...senders].sort((left, right) => right.media_count - left.media_count || right.inbound_count - left.inbound_count),
    [senders],
  );

  if (sortedSenders.length === 0) {
    return (
      <Card className="border-white/70 bg-white/90 shadow-sm">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Remetentes do evento</CardTitle>
          <CardDescription>Nenhum remetente relacionado ainda.</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            Assim que grupos ou DMs enviarem conteudo, os remetentes aparecem aqui com contagem de midias e atalho rapido para moderacao e galeria.
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="pb-2">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle className="text-base">Remetentes do evento</CardTitle>
            <CardDescription>
              Abra a moderacao ou a galeria ja filtradas por remetente e aplique bloqueio rapido sem sair do detalhe do evento.
            </CardDescription>
          </div>
          <Badge variant="outline">{sortedSenders.length} remetente(s)</Badge>
        </div>
      </CardHeader>
      <CardContent className="space-y-3">
        {sortedSenders.map((sender) => {
          const senderKey = `${sender.recommended_identity_type}:${sender.recommended_identity_value}`;
          const duration = durations[senderKey] ?? '7d';
          const busy = isBusy?.(sender) ?? false;

          return (
            <div
              key={senderKey}
              className="rounded-2xl border border-border/60 bg-background/70 p-4 shadow-sm"
            >
              <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                  <Avatar className="h-11 w-11 border border-border/60">
                    <AvatarImage src={sender.sender_avatar_url ?? undefined} alt={senderPrimaryLabel(sender)} />
                    <AvatarFallback>{initialsFromName(sender.sender_name)}</AvatarFallback>
                  </Avatar>

                  <div className="min-w-0 space-y-2">
                    <div className="space-y-1">
                      <p className="truncate text-sm font-semibold text-foreground">{senderPrimaryLabel(sender)}</p>
                      <p className="truncate text-xs text-muted-foreground">{senderSecondaryLabel(sender)}</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      <Badge variant="secondary" className="gap-1">
                        <Camera className="h-3.5 w-3.5" />
                        {sender.media_count} midia(s)
                      </Badge>
                      <Badge variant="outline">{sender.inbound_count} webhook(s)</Badge>
                      <Badge variant="outline">{identityTypeLabel(sender.recommended_identity_type)} padrao</Badge>
                      {sender.blocked ? (
                        <Badge variant="destructive" className="gap-1">
                          <ShieldBan className="h-3.5 w-3.5" />
                          Bloqueado
                        </Badge>
                      ) : null}
                    </div>

                    <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                      <span className="inline-flex items-center gap-1">
                        <Clock3 className="h-3.5 w-3.5" />
                        Ultima atividade: {formatSenderDateTime(sender.last_seen_at)}
                      </span>
                      {sender.blocked && sender.blocking_expires_at ? (
                        <span className="inline-flex items-center gap-1">
                          <AlertCircle className="h-3.5 w-3.5" />
                          Bloqueado ate {formatSenderDateTime(sender.blocking_expires_at)}
                        </span>
                      ) : null}
                    </div>
                  </div>
                </div>

                <div className="flex flex-col gap-3 xl:min-w-[360px]">
                  <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm" variant="outline">
                      <Link to={buildSenderScopedPath('/moderation', eventId, sender.recommended_identity_value)}>
                        Moderacao
                        <ArrowUpRight className="h-4 w-4" />
                      </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                      <Link to={buildSenderScopedPath('/gallery', eventId, sender.recommended_identity_value)}>
                        Galeria
                        <ArrowUpRight className="h-4 w-4" />
                      </Link>
                    </Button>
                  </div>

                  <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <Select
                      value={duration}
                      onValueChange={(value) => setDurations((current) => ({ ...current, [senderKey]: value }))}
                      disabled={!canManageBlacklist || busy}
                    >
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

                    <div className="flex items-center gap-3 rounded-full border border-border/60 bg-muted/40 px-3 py-2">
                      <span className="text-xs font-medium text-muted-foreground">Bloquear remetente</span>
                      <Switch
                        checked={sender.blocked}
                        disabled={!canManageBlacklist || busy}
                        onCheckedChange={(checked) => onToggleBlock?.(sender, checked, duration)}
                        aria-label={`Bloquear ${senderPrimaryLabel(sender)}`}
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}
