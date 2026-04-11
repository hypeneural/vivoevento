import type { ReactNode } from 'react';
import { CheckCircle2, Loader2 } from 'lucide-react';

type AuthFlowSuccessStateProps = {
  title: string;
  description: string;
  statusLabel?: string;
  icon?: ReactNode;
};

export function AuthFlowSuccessState({
  title,
  description,
  statusLabel = 'Redirecionando...',
  icon,
}: AuthFlowSuccessStateProps) {
  return (
    <div className="space-y-5 text-center sm:space-y-6">
      <div className="space-y-3">
        <div className="inline-flex">
          {icon ?? (
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-green-500/10">
              <CheckCircle2 className="h-8 w-8 text-green-500" />
            </div>
          )}
        </div>
        <h2 className="text-lg font-semibold sm:text-xl">{title}</h2>
        <p className="text-sm text-muted-foreground">{description}</p>
        <div className="flex items-center justify-center gap-2 pt-2">
          <Loader2 className="h-4 w-4 animate-spin text-primary" />
          <span className="text-xs text-muted-foreground">{statusLabel}</span>
        </div>
      </div>
    </div>
  );
}
