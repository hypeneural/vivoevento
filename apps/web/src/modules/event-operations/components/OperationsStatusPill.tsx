import type { ReactNode } from 'react';

export type OperationsStatusPillTone = 'healthy' | 'attention' | 'critical' | 'neutral';

interface OperationsStatusPillProps {
  label: ReactNode;
  value: ReactNode;
  tone?: OperationsStatusPillTone;
  urgent?: boolean;
}

const toneClasses: Record<OperationsStatusPillTone, string> = {
  healthy: 'border-emerald-300/30 bg-emerald-300/10 text-emerald-100',
  attention: 'border-amber-300/40 bg-amber-300/10 text-amber-100',
  critical: 'border-red-300/40 bg-red-400/10 text-red-100',
  neutral: 'border-white/15 bg-white/10 text-slate-100',
};

export function OperationsStatusPill({
  label,
  value,
  tone = 'neutral',
  urgent = false,
}: OperationsStatusPillProps) {
  const role = urgent ? 'alert' : 'status';

  return (
    <div
      role={role}
      className={`rounded-2xl border px-4 py-3 text-sm ${toneClasses[tone]}`}
    >
      <span className="block text-[0.68rem] font-semibold uppercase tracking-[0.24em] opacity-80">
        {label}
      </span>
      <span className="mt-1 block font-semibold">{value}</span>
    </div>
  );
}
