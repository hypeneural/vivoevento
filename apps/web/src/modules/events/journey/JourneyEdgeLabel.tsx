import { cn } from '@/lib/utils';

interface JourneyEdgeLabelProps {
  label: string;
  x: number;
  y: number;
  className: string;
}

export function JourneyEdgeLabel({
  label,
  x,
  y,
  className,
}: JourneyEdgeLabelProps) {
  return (
    <div
      className={cn(
        'nodrag nopan absolute rounded-full border px-2 py-1 text-[11px] font-medium',
        className,
      )}
      style={{
        transform: `translate(-50%, -50%) translate(${x}px, ${y}px)`,
        pointerEvents: 'all',
      }}
    >
      {label}
    </div>
  );
}
