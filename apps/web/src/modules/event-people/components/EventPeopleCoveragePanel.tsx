import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RefreshCcw, ShieldAlert, ShieldCheck } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { queryKeys } from '@/lib/query-client';

import { eventPeopleApi } from '../api';
import type { EventCoverageAlert, EventCoverageTarget, EventPeopleCoverageResponse } from '../types';

type OperationTone = 'neutral' | 'success' | 'warning' | 'danger';

interface PersistentOperationStatus {
  title: string;
  description: string;
  tone: OperationTone;
}

interface EventPeopleCoveragePanelProps {
  eventId: string;
  onStatusChange?: (status: PersistentOperationStatus | null) => void;
}

const stateLabels: Record<string, string> = {
  missing: 'Faltando',
  weak: 'Fraco',
  ok: 'Ok',
  strong: 'Forte',
};

function stateBadgeVariant(state: string) {
  if (state === 'missing') return 'destructive';
  if (state === 'weak') return 'outline';
  if (state === 'ok') return 'secondary';
  return 'default';
}

function targetSubtitle(target: EventCoverageTarget) {
  const stat = target.stat;
  const required = target.required_media_count ?? 0;
  const publishedRequired = target.required_published_media_count ?? 0;

  if (!stat) return 'Sem leitura projetada.';

  if (target.target_type === 'pair') {
    return `Fotos juntos: ${stat.joint_media_count ?? 0}/${required}`;
  }

  if (target.target_type === 'group') {
    return `Fotos do grupo: ${stat.media_count ?? 0}/${required}`;
  }

  const publishedLabel = publishedRequired > 0 ? ` · Publicadas: ${stat.published_media_count ?? 0}/${publishedRequired}` : '';
  return `Fotos da pessoa: ${stat.media_count ?? 0}/${required}${publishedLabel}`;
}

function isResolvedTarget(target: EventCoverageTarget) {
  const resolvedEntityCount = target.stat?.resolved_entity_count ?? 0;

  if (target.target_type === 'pair') {
    return resolvedEntityCount >= 2;
  }

  return resolvedEntityCount >= 1;
}

export function EventPeopleCoveragePanel({ eventId, onStatusChange }: EventPeopleCoveragePanelProps) {
  const queryClient = useQueryClient();

  const coverageQuery = useQuery({
    queryKey: queryKeys.eventPeople.coverage(eventId || 'none'),
    queryFn: () => eventPeopleApi.getCoverage(eventId),
    enabled: eventId !== '',
    staleTime: 20_000,
  });

  const refreshMutation = useMutation<EventPeopleCoverageResponse>({
    mutationFn: () => eventPeopleApi.refreshCoverage(eventId),
    onMutate: () => {
      onStatusChange?.({
        tone: 'neutral',
        title: 'Recalculando cobertura',
        description: 'A leitura de cobertura esta sendo atualizada.',
      });
    },
    onSuccess: (data) => {
      queryClient.setQueryData(queryKeys.eventPeople.coverage(eventId), data);
      onStatusChange?.({
        tone: 'success',
        title: 'Cobertura atualizada',
        description: 'Os alertas foram recalculados para a operacao.',
      });
    },
    onError: () => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao recalcular cobertura',
        description: 'Nao foi possivel atualizar os alertas agora.',
      });
    },
  });

  const summary = coverageQuery.data?.summary;
  const alerts = coverageQuery.data?.alerts ?? [];
  const targets = coverageQuery.data?.targets ?? [];

  const highlightTargets = useMemo(() => {
    if (alerts.length > 0) {
      return alerts
        .map((alert) => {
          const target = targets.find((item) => item.key === alert.target?.key);
          return target ? { target, alert } : null;
        })
        .filter((entry): entry is { target: EventCoverageTarget; alert: EventCoverageAlert } => entry !== null)
        .slice(0, 6);
    }

    return targets
      .filter((target) => isResolvedTarget(target) && ['missing', 'weak'].includes(target.stat?.coverage_state ?? 'missing'))
      .slice(0, 6)
      .map((target) => ({ target, alert: null }));
  }, [alerts, targets]);

  return (
    <Card className="border-border/60">
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="flex items-center gap-2">
          <ShieldAlert className="h-4 w-4 text-primary" />
          Cobertura importante
        </CardTitle>
        <Button
          variant="outline"
          size="sm"
          onClick={() => refreshMutation.mutate()}
          disabled={refreshMutation.isPending}
        >
          <RefreshCcw className={refreshMutation.isPending ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
          Recalcular
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Faltando</p>
            <p className="text-lg font-semibold">{summary?.missing ?? 0}</p>
          </div>
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Fraco</p>
            <p className="text-lg font-semibold">{summary?.weak ?? 0}</p>
          </div>
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Ok</p>
            <p className="text-lg font-semibold">{summary?.ok ?? 0}</p>
          </div>
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Forte</p>
            <p className="text-lg font-semibold">{summary?.strong ?? 0}</p>
          </div>
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Alertas</p>
            <p className="text-lg font-semibold">{summary?.active_alerts ?? 0}</p>
          </div>
        </div>

        {coverageQuery.isLoading ? (
          <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">
            Carregando cobertura...
          </div>
        ) : null}

        {!coverageQuery.isLoading && highlightTargets.length === 0 ? (
          <div className="flex items-center gap-2 rounded-2xl border border-border/60 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <ShieldCheck className="h-4 w-4" />
            Nenhum alerta critico. A cobertura esta equilibrada.
          </div>
        ) : null}

        {highlightTargets.map(({ target, alert }) => {
          const state = target.stat?.coverage_state ?? 'missing';
          return (
            <div key={target.key} className="rounded-2xl border border-border/60 bg-background px-4 py-3">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-medium">{target.label}</p>
                  <p className="text-xs text-muted-foreground">{targetSubtitle(target)}</p>
                  {alert?.summary ? <p className="mt-2 text-xs text-muted-foreground">{alert.summary}</p> : null}
                </div>
                <Badge variant={stateBadgeVariant(state)}>{stateLabels[state] ?? state}</Badge>
              </div>
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}
