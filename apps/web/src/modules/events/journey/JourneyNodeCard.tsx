import {
  Archive,
  BellRing,
  Bot,
  Film,
  Heart,
  ImagePlus,
  Images,
  Link2,
  MessageCircle,
  MessageSquareText,
  Printer,
  ScanSearch,
  Send,
  ShieldAlert,
  ShieldCheck,
  Split,
  Tv,
  UserRoundX,
  Users,
  type LucideIcon,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

import type { JourneyGraphNode } from './buildJourneyGraph';
import {
  describeJourneyEditability,
  describeJourneyStage,
  describeJourneyStatus,
  getJourneyNodeCopy,
  humanizeJourneyBranchLabel,
  humanizeJourneyEditability,
  humanizeJourneyStageLabel,
  humanizeJourneyStatusLabel,
} from './journeyCopy';

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
  nodeId: string;
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

function resolveJourneyNodeIcon(nodeId: string, stage: JourneyGraphNode['stage']): LucideIcon {
  switch (nodeId) {
    case 'entry_whatsapp_direct':
      return MessageCircle;
    case 'entry_whatsapp_groups':
      return Users;
    case 'entry_telegram':
      return Send;
    case 'entry_public_upload':
      return Link2;
    case 'entry_sender_blacklist':
      return UserRoundX;
    case 'processing_receive_feedback':
      return BellRing;
    case 'processing_download_media':
      return Images;
    case 'processing_prepare_variants':
      return ImagePlus;
    case 'processing_safety_ai':
      return ShieldAlert;
    case 'processing_media_intelligence':
      return Bot;
    case 'decision_event_moderation_mode':
      return Split;
    case 'decision_safety_result':
      return ShieldCheck;
    case 'decision_context_gate':
      return ScanSearch;
    case 'decision_media_type':
      return Film;
    case 'decision_caption_presence':
      return MessageSquareText;
    case 'output_reaction_final':
      return Heart;
    case 'output_reply_text':
      return MessageSquareText;
    case 'output_gallery':
      return Images;
    case 'output_wall':
      return Tv;
    case 'output_print':
      return Printer;
    case 'output_silence':
      return Archive;
    default:
      return stage === 'decision' ? Split : MessageSquareText;
  }
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
        label: humanizeJourneyStatusLabel(status),
        className: 'border-emerald-200 bg-emerald-50 text-emerald-800',
      };
    case 'inactive':
      return {
        label: humanizeJourneyStatusLabel(status),
        className: 'border-slate-200 bg-slate-50 text-slate-600',
      };
    case 'locked':
      return {
        label: humanizeJourneyStatusLabel(status),
        className: 'border-amber-200 bg-amber-50 text-amber-800',
      };
    case 'required':
      return {
        label: humanizeJourneyStatusLabel(status),
        className: 'border-sky-200 bg-sky-50 text-sky-800',
      };
    case 'unavailable':
    default:
      return {
        label: humanizeJourneyStatusLabel(status),
        className: 'border-slate-200 bg-slate-50 text-slate-500',
      };
  }
}

export function JourneyNodeCard({
  nodeId,
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
  const humanizedBranchLabels = branchLabels
    .map((branchLabel, index) => humanizeJourneyBranchLabel(`${index}`, branchLabel))
    .filter((branchLabel): branchLabel is string => Boolean(branchLabel));
  const visibleBranchLabels = humanizedBranchLabels.slice(0, 2);
  const extraBranchCount = Math.max(humanizedBranchLabels.length - visibleBranchLabels.length, 0);
  const isDecisionNode = kind === 'decision';
  const icon = resolveJourneyNodeIcon(nodeId, stage);
  const nodeCopy = getJourneyNodeCopy({ id: nodeId, label, description, summary });
  const stageLabel = humanizeJourneyStageLabel(stage);
  const editabilityLabel = humanizeJourneyEditability(editable);
  const Icon = icon;

  return (
    <div
      className={cn(
        'h-full w-full overflow-hidden rounded-[26px] border bg-white/98 p-5 shadow-[0_16px_40px_-28px_rgba(15,23,42,0.35)] transition-all',
        accent.borderClassName,
        highlighted && 'ring-2 ring-emerald-400/70 ring-offset-2',
        selected && 'ring-2 ring-primary ring-offset-2',
      )}
      data-node-kind={kind}
    >
      <div className="flex h-full min-h-0 flex-col">
        <div className="flex items-start justify-between gap-3">
          <div className="flex min-w-0 items-start gap-3">
            <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border shadow-sm', accent.borderClassName, accent.surfaceClassName)}>
              <Icon className="h-[18px] w-[18px] text-foreground/75" />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="nopan inline-flex">
                    <Badge variant="outline" className={cn('px-2 py-0.5 text-[11px] font-medium', accent.badgeClassName)}>
                      {stageLabel}
                    </Badge>
                  </span>
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-xs rounded-xl px-3 py-2 text-xs">
                  {describeJourneyStage(stage)}
                </TooltipContent>
              </Tooltip>
              <h3 className="text-[15px] font-semibold leading-5 text-foreground">{nodeCopy.label}</h3>
              <p className="line-clamp-4 text-[12px] leading-6 text-muted-foreground">{nodeCopy.description}</p>
            </div>
          </div>
          <Tooltip>
            <TooltipTrigger asChild>
              <span className="nopan inline-flex">
                <Badge
                  variant="outline"
                  className={cn('max-w-[8.5rem] shrink-0 whitespace-normal px-2 py-0.5 text-center text-[11px] font-medium leading-4', statusMeta.className)}
                >
                  {statusMeta.label}
                </Badge>
              </span>
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs rounded-xl px-3 py-2 text-xs">
              {describeJourneyStatus(status)}
            </TooltipContent>
          </Tooltip>
        </div>

        <p className="mt-3 line-clamp-3 text-[13px] leading-6 text-foreground/85">
          {nodeCopy.summary}
        </p>

        {isDecisionNode ? (
          <div className={cn('mt-4 rounded-2xl border px-3.5 py-3', accent.borderClassName, accent.surfaceClassName)}>
            <div className="flex items-center justify-between gap-3">
              <span className="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                Possiveis resultados
              </span>
              <Badge variant="outline" className="border-white/80 bg-white/85 px-2 py-0.5 text-[10px]">
                {humanizedBranchLabels.length} caminhos
              </Badge>
            </div>
            <div className="mt-2.5 flex flex-wrap gap-1.5">
              {visibleBranchLabels.map((branchLabel) => (
                <Badge
                  key={branchLabel}
                  variant="outline"
                  className="border-white/80 bg-white/85 px-2 py-0.5 text-[10px] font-medium text-slate-700"
                >
                  {branchLabel}
                </Badge>
              ))}
              {extraBranchCount > 0 ? (
                <Badge variant="outline" className="border-white/80 bg-white/85 px-2 py-0.5 text-[10px] text-slate-700">
                  +{extraBranchCount}
                </Badge>
              ) : null}
            </div>
          </div>
        ) : null}

        <div className="mt-auto flex flex-wrap gap-1.5 pt-3.5">
          <Tooltip>
            <TooltipTrigger asChild>
              <span className="nopan inline-flex">
                <Badge variant="outline" className="max-w-[8rem] whitespace-normal px-2 py-0.5 text-[10px] leading-4 text-slate-600">
                  {editabilityLabel}
                </Badge>
              </span>
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs rounded-xl px-3 py-2 text-xs">
              {describeJourneyEditability(editable)}
            </TooltipContent>
          </Tooltip>
          {warningCount > 0 ? (
            <Badge
              variant="outline"
              className="border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] text-amber-800"
            >
              {warningCount} alerta{warningCount > 1 ? 's' : ''}
            </Badge>
          ) : null}
        </div>
      </div>
    </div>
  );
}
