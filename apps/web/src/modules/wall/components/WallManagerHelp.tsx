import { type ReactNode } from 'react';
import { CircleHelp } from 'lucide-react';

import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

import { HELP_TEXTS, type WallHelpKey } from '../manager-config';

export function HelpTooltip({ helpKey }: { helpKey: WallHelpKey }) {
  const help = HELP_TEXTS[helpKey];

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <button
          type="button"
          className="inline-flex h-5 w-5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <CircleHelp className="h-3.5 w-3.5" />
          <span className="sr-only">{help.title}</span>
        </button>
      </TooltipTrigger>
      <TooltipContent side="top" align="start" className="max-w-sm rounded-xl px-4 py-3">
        <div className="space-y-2">
          <p className="text-sm font-semibold">{help.title}</p>
          <p className="text-sm leading-relaxed text-muted-foreground">{help.description}</p>
          <p className="text-xs leading-relaxed text-muted-foreground">
            <span className="font-semibold text-foreground">Por que isso existe:</span>{' '}
            {help.why}
          </p>
        </div>
      </TooltipContent>
    </Tooltip>
  );
}

export function HelpLabel({
  children,
  helpKey,
  className = 'text-xs',
}: {
  children: ReactNode;
  helpKey: WallHelpKey;
  className?: string;
}) {
  return (
    <div className="flex items-center gap-1.5">
      <Label className={className}>{children}</Label>
      <HelpTooltip helpKey={helpKey} />
    </div>
  );
}
