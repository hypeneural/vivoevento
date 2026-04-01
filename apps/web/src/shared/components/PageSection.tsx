import type { ReactNode } from 'react';

interface PageSectionProps {
  title: string;
  description?: string;
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
}

/**
 * Reusable page section with title, description, and optional actions.
 * Use inside pages to group related content.
 */
export function PageSection({ title, description, actions, children, className = '' }: PageSectionProps) {
  return (
    <section className={`space-y-4 ${className}`}>
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-base font-semibold tracking-tight">{title}</h2>
          {description && <p className="text-xs sm:text-sm text-muted-foreground mt-0.5">{description}</p>}
        </div>
        {actions && <div className="flex items-center gap-2">{actions}</div>}
      </div>
      {children}
    </section>
  );
}
