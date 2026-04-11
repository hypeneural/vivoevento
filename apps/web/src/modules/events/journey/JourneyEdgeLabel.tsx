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
        'nodrag nopan absolute rounded-full border border-white/80 bg-white/90 px-2.5 py-1 text-[10px] font-semibold shadow-sm backdrop-blur-sm',
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
