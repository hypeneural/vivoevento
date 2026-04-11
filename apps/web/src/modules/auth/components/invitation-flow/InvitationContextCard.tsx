import { Shield } from 'lucide-react';

type InvitationContextCardProps = {
  badge: string;
  title: string;
  description: string;
  className?: string;
};

export function InvitationContextCard({
  badge,
  title,
  description,
  className,
}: InvitationContextCardProps) {
  return (
    <div className={className}>
      <div className="flex items-start gap-3">
        <div className="rounded-xl bg-primary/10 p-2 text-primary">
          <Shield className="h-4 w-4" />
        </div>
        <div className="space-y-1">
          <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-primary/80">
            {badge}
          </p>
          <p className="text-sm font-medium text-foreground">{title}</p>
          <p className="text-sm text-muted-foreground">{description}</p>
        </div>
      </div>
    </div>
  );
}
