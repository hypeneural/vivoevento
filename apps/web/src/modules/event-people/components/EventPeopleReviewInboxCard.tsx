import { AlertTriangle, ArrowUpRight, CheckCircle2, ScanFace, Sparkles, UsersRound } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/shared/components/EmptyState';

import type { EventPersonReviewQueueItem } from '../types';

function queueTypeLabel(type: string) {
  switch (type) {
    case 'identity_conflict':
      return 'Conflito';
    case 'cluster_suggestion':
      return 'Sugestao';
    case 'coverage_gap':
      return 'Cobertura';
    default:
      return 'Quem e esta pessoa?';
  }
}

function queueStatusTone(status: string) {
  if (status === 'conflict') return 'destructive';
  if (status === 'resolved') return 'secondary';

  return 'outline';
}

export interface EventPeopleReviewInboxCardProps {
  eventName?: string | null;
  items: EventPersonReviewQueueItem[];
  isLoading?: boolean;
  isError?: boolean;
  isPendingUi?: boolean;
  onOpenItem: (item: EventPersonReviewQueueItem) => void;
}

export function EventPeopleReviewInboxCard({
  eventName,
  items,
  isLoading = false,
  isError = false,
  isPendingUi = false,
  onOpenItem,
}: EventPeopleReviewInboxCardProps) {
  const pendingCount = items.filter((item) => item.status === 'pending').length;
  const conflictCount = items.filter((item) => item.status === 'conflict').length;

  return (
    <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div className="space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="outline">Revisao guiada</Badge>
            {pendingCount > 0 ? <Badge variant="outline">{pendingCount} pendentes</Badge> : null}
            {conflictCount > 0 ? <Badge variant="destructive">{conflictCount} conflitos</Badge> : null}
            {isPendingUi ? <Badge variant="secondary">Atualizando localmente</Badge> : null}
          </div>
          <div className="space-y-1">
            <p className="text-lg font-semibold">Organizar pessoas do evento</p>
            <p className="text-sm text-muted-foreground">
              {eventName
                ? `Revise os rostos que ainda precisam de nome ou ajuste em ${eventName}.`
                : 'Selecione um evento para revisar os rostos detectados e nomear as pessoas.'}
            </p>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-2 text-sm sm:min-w-[260px]">
          <div className="rounded-2xl border border-border/50 bg-background/60 p-3">
            <div className="flex items-center gap-2 text-muted-foreground">
              <ScanFace className="h-4 w-4" />
              <span>Pendencias</span>
            </div>
            <p className="mt-2 text-2xl font-semibold">{pendingCount + conflictCount}</p>
          </div>
          <div className="rounded-2xl border border-border/50 bg-background/60 p-3">
            <div className="flex items-center gap-2 text-muted-foreground">
              <UsersRound className="h-4 w-4" />
              <span>Na fila</span>
            </div>
            <p className="mt-2 text-2xl font-semibold">{items.length}</p>
          </div>
        </div>
      </div>

      <div className="mt-5">
        {isLoading ? (
          <div className="grid gap-3 lg:grid-cols-2">
            {Array.from({ length: 4 }).map((_, index) => (
              <div key={index} className="h-28 animate-pulse rounded-2xl border border-border/50 bg-muted/40" />
            ))}
          </div>
        ) : null}

        {!isLoading && isError ? (
          <div className="rounded-2xl border border-destructive/30 bg-destructive/5 px-4 py-6 text-sm text-destructive">
            Nao foi possivel carregar a inbox de revisao agora.
          </div>
        ) : null}

        {!isLoading && !isError && items.length === 0 ? (
          <div className="rounded-3xl border border-border/50 bg-background/50">
            <EmptyState
              icon={CheckCircle2}
              title="Nenhuma pendencia prioritaria"
              description="Quando houver rostos sem nome ou conflitos de identidade, eles aparecerao aqui."
            />
          </div>
        ) : null}

        {!isLoading && !isError && items.length > 0 ? (
          <div className="grid gap-3 lg:grid-cols-2">
            {items.map((item) => {
              const isConflict = item.type === 'identity_conflict' || item.status === 'conflict';

              return (
                <article key={item.id} className="rounded-2xl border border-border/60 bg-background/70 p-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={queueStatusTone(item.status) as 'outline' | 'secondary' | 'destructive'}>
                      {queueTypeLabel(item.type)}
                    </Badge>
                    <Badge variant="outline">prioridade {item.priority}</Badge>
                    {item.face?.face_index !== undefined ? (
                      <Badge variant="outline">rosto #{item.face.face_index + 1}</Badge>
                    ) : null}
                  </div>

                  <div className="mt-3 space-y-1">
                    <p className="font-medium">{item.payload.question || 'Quem e esta pessoa?'}</p>
                    <p className="text-sm text-muted-foreground">
                      Midia #{item.face?.event_media_id ?? item.payload.event_media_id ?? 'n/a'}
                      {item.person?.display_name ? ` - atual: ${item.person.display_name}` : ''}
                    </p>
                  </div>

                  {Array.isArray(item.payload.candidate_people) && item.payload.candidate_people.length > 0 ? (
                    <div className="mt-3 flex flex-wrap gap-2">
                      {item.payload.candidate_people.slice(0, 3).map((candidate) => (
                        <Badge key={candidate.id} variant="secondary">
                          {candidate.display_name}
                        </Badge>
                      ))}
                    </div>
                  ) : null}

                  <div className="mt-4 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                      {isConflict ? <AlertTriangle className="h-4 w-4" /> : <Sparkles className="h-4 w-4" />}
                      <span>{isConflict ? 'Precisa de ajuste' : 'Pronto para confirmar'}</span>
                    </div>
                    <Button type="button" size="sm" onClick={() => onOpenItem(item)}>
                      {isConflict ? 'Resolver agora' : 'Quem e esta pessoa?'}
                      <ArrowUpRight className="h-4 w-4" />
                    </Button>
                  </div>
                </article>
              );
            })}
          </div>
        ) : null}
      </div>
    </section>
  );
}

export default EventPeopleReviewInboxCard;
