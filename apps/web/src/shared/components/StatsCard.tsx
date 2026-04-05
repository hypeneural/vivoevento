import { motion } from 'framer-motion';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface StatsCardProps {
  title: string;
  value: string | number;
  icon: LucideIcon;
  change?: string;
  changeType?: 'positive' | 'negative' | 'neutral';
  description?: string;
  iconColor?: string;
  iconBg?: string;
}

export function StatsCard({
  title,
  value,
  icon: Icon,
  change,
  changeType = 'neutral',
  description,
  iconColor,
  iconBg,
}: StatsCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      className="glass card-hover rounded-xl p-3.5 sm:p-4"
    >
      <div className="flex items-start justify-between gap-2">
        <div className="space-y-0.5 min-w-0 flex-1">
          <p className="text-[11px] sm:text-xs text-muted-foreground font-medium truncate">{title}</p>
          <p className="text-lg sm:text-xl font-bold tracking-tight tabular-nums">{value}</p>
          {change && (
            <p className={cn(
              'text-[10px] sm:text-[11px] font-medium leading-tight',
              changeType === 'positive' && 'text-emerald-500',
              changeType === 'negative' && 'text-red-400',
              changeType === 'neutral' && 'text-muted-foreground',
            )}>
              {change}
            </p>
          )}
          {description && <p className="text-[10px] sm:text-[11px] text-muted-foreground">{description}</p>}
        </div>
        <div className={cn('rounded-lg p-2 shrink-0', iconBg || 'bg-primary/10')}>
          <Icon className={cn('h-4 w-4', iconColor || 'text-primary')} />
        </div>
      </div>
    </motion.div>
  );
}
