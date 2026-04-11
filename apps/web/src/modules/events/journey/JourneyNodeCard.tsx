import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

import type { JourneyGraphNode } from './buildJourneyGraph';

export interface JourneyStageAccent {
  borderClassName: string;
  surfaceClassName: string;
  badgeClassName: string;
  handleColor: string;
}

interface JourneyNodeStatusMeta {
  label: string;
  className: string;
}

export interface JourneyNodeCardProps {
  stage: JourneyGraphNode['stage'];
  kind: JourneyGraphNode['kind'];
  status: JourneyGraphNode['status'];
  label: string;
  description: string;
  summary: string;
  editable: boolean;
  warningCount: number;
  branchLabels: string[];
  highlighted: boolean;
  selected: boolean;
}

export function resolveJourneyStageAccent(stage: JourneyGraphNode['stage']): JourneyStageAccent {
  switch (stage) {
    case 'entry':
      return {
        borderClassName: 'border-sky-200',
        surfaceClassName: 'bg-sky-50/90',
        badgeClassName: 'border-sky-200 bg-sky-100 text-sky-800',
        handleColor: '#0ea5e9',
      };
    case 'processing':
      return {
        borderClassName: 'border-violet-200',
        surfaceClassName: 'bg-violet-50/90',
        badgeClassName: 'border-violet-200 bg-violet-100 text-violet-800',
        handleColor: '#8b5cf6',
      };
    case 'decision':
      return {
        borderClassName: 'border-amber-200',
        surfaceClassName: 'bg-amber-50/90',
        badgeClassName: 'border-amber-200 bg-amber-100 text-amber-800',
        handleColor: '#f59e0b',
      };
    case 'output':
    default:
      return {
        borderClassName: 'border-emerald-200',
        surfaceClassName: 'bg-emerald-50/90',
        badgeClassName: 'border-emerald-200 bg-emerald-100 text-emerald-800',
        handleColor: '#10b981',
      };
  }
}

export function resolveJourneyNodeStatusMeta(status: JourneyGraphNode['status']): JourneyNodeStatusMeta {
  switch (status) {
    case 'active':
      return {
        label: 'Ativo',
        className: 'border-emerald-200 bg-emerald-50 text-emerald-800',
      };
    case 'inactive':
      return {
        label: 'Desativado',
        className: 'border-slate-200 bg-slate-50 text-slate-600',
      };
    case 'locked':
      return {
        label: 'Bloqueado pelo pacote',
        className: 'border-amber-200 bg-amber-50 text-amber-800',
      };
    case 'required':
      return {
        label: 'Obrigatorio',
        className: 'border-sky-200 bg-sky-50 text-sky-800',
      };
    case 'unavailable':
    default:
      return {
        label: 'Indisponivel',
        className: 'border-slate-200 bg-slate-50 text-slate-500',
      };
  }
}

export function JourneyNodeCard({
  stage,
  kind,
  status,
  label,
  description,
  summary,
  editable,
  warningCount,
  branchLabels,
  highlighted,
  selected,
}: JourneyNodeCardProps) {
  const accent = resolveJourneyStageAccent(stage);
  const statusMeta = resolveJourneyNodeStatusMeta(status);
  const visibleBranchLabels = branchLabels.slice(0, 3);
  const extraBranchCount = Math.max(branchLabels.length - visibleBranchLabels.length, 0);
  const isDecisionNode = kind === 'decision';

  return (
    <div
      className={cn(
        'h-full w-full rounded-[22px] border bg-white/95 p-4 shadow-sm transition-all',
        accent.borderClassName,
        highlighted && 'ring-2 ring-emerald-400/70 ring-offset-2',
        selected && 'ring-2 ring-primary ring-offset-2',
      )}
      data-node-kind={kind}
    >
      <div className="flex h-full flex-col">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1">
            <Badge variant="outline" className={cn('text-[11px] font-medium', accent.badgeClassName)}>
              {stage === 'entry' && 'Entrada'}
              {stage === 'processing' && 'Processamento'}
              {stage === 'decision' && 'Decisao'}
              {stage === 'output' && 'Saida'}
            </Badge>
            <h3 className="text-sm font-semibold text-foreground">{label}</h3>
            <p className="text-xs text-muted-foreground">{description}</p>
          </div>
          <Badge variant="outline" className={cn('max-w-[10rem] whitespace-normal text-center text-[11px] font-medium', statusMeta.className)}>
            {statusMeta.label}
          </Badge>
        </div>

        <p className="mt-3 line-clamp-3 text-sm leading-5 text-foreground/90">
          {summary}
        </p>

        {isDecisionNode ? (
          <div className={cn('mt-3 rounded-2xl border px-3 py-2.5', accent.borderClassName, accent.surfaceClassName)}>
            <div className="flex items-center justify-between gap-3">
              <span className="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                Caminhos da decisao
              </span>
              <Badge variant="outline" className="border-white/80 bg-white/80 text-[10px]">
                {branchLabels.length} saida(s)
              </Badge>
            </div>
            <div className="mt-2 flex flex-wrap gap-1.5">
              {visibleBranchLabels.map((branchLabel) => (
                <Badge
                  key={branchLabel}
                  variant="outline"
                  className="border-white/80 bg-white/80 text-[10px] font-medium text-slate-700"
                >
                  {branchLabel}
                </Badge>
              ))}
              {extraBranchCount > 0 ? (
                <Badge variant="outline" className="border-white/80 bg-white/80 text-[10px] text-slate-700">
                  +{extraBranchCount}
                </Badge>
              ) : null}
            </div>
          </div>
        ) : null}

        <div className="mt-auto flex flex-wrap gap-2 pt-3">
          <Badge variant="outline" className="text-[11px] text-slate-600">
            {editable ? 'Opcional' : 'Automatico'}
          </Badge>
          {warningCount > 0 ? (
            <Badge
              variant="outline"
              className="border-amber-200 bg-amber-50 text-[11px] text-amber-800"
            >
              {warningCount} alerta{warningCount > 1 ? 's' : ''}
            </Badge>
          ) : null}
        </div>
      </div>
    </div>
  );
}
