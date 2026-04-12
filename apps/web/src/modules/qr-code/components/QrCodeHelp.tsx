import { CircleHelp } from 'lucide-react';

import { FormLabel } from '@/components/ui/form';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

export function QrHelpTooltip({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <TooltipProvider delayDuration={0}>
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            className="inline-flex h-5 w-5 items-center justify-center rounded-full text-muted-foreground transition hover:bg-slate-100 hover:text-foreground"
            aria-label={`Ajuda sobre ${title}`}
          >
            <CircleHelp className="h-3.5 w-3.5" />
          </button>
        </TooltipTrigger>
        <TooltipContent side="top" align="start" className="max-w-xs rounded-xl px-4 py-3 text-sm">
          <p className="font-medium">{title}</p>
          <p className="mt-1 text-xs leading-5 text-muted-foreground">{description}</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}

export function QrFieldLabel({
  label,
  description,
}: {
  label: string;
  description?: string;
}) {
  return (
    <div className="flex items-center gap-1.5">
      <FormLabel className="m-0">{label}</FormLabel>
      {description ? <QrHelpTooltip title={label} description={description} /> : null}
    </div>
  );
}
