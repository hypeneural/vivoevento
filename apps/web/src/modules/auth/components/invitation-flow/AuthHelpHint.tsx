import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { Shield } from 'lucide-react';

type AuthHelpHintProps = {
  children: ReactNode;
  icon?: LucideIcon;
};

export function AuthHelpHint({
  children,
  icon: Icon = Shield,
}: AuthHelpHintProps) {
  return (
    <div className="flex items-start gap-2.5 rounded-xl border border-border/30 bg-muted/30 p-3">
      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
      <div className="text-[10px] leading-relaxed text-muted-foreground sm:text-xs">
        {children}
      </div>
    </div>
  );
}
