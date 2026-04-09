import { CheckCircle2, Circle } from 'lucide-react';

import { cn } from '@/lib/utils';

type StepSummaryRowProps = {
  label: string;
  summary: string;
  active?: boolean;
  completed?: boolean;
};

export function StepSummaryRow({
  label,
  summary,
  active = false,
  completed = false,
}: StepSummaryRowProps) {
  return (
    <div className="flex items-start gap-3 text-left">
      <span className={cn(
        'mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full',
        completed ? 'text-emerald-600' : active ? 'text-primary' : 'text-muted-foreground',
      )}
      >
        {completed ? <CheckCircle2 className="h-4 w-4" /> : <Circle className="h-4 w-4" />}
      </span>
      <span className="space-y-1">
        <span className="block text-sm font-semibold">{label}</span>
        <span className="block text-xs text-muted-foreground">{summary}</span>
      </span>
    </div>
  );
}
