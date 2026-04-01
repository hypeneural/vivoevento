import { motion } from 'framer-motion';
import type { LucideIcon } from 'lucide-react';

interface StatsCardProps {
  title: string;
  value: string | number;
  icon: LucideIcon;
  change?: string;
  changeType?: 'positive' | 'negative' | 'neutral';
  description?: string;
}

export function StatsCard({ title, value, icon: Icon, change, changeType = 'neutral', description }: StatsCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      className="glass card-hover rounded-xl p-4 sm:p-5"
    >
      <div className="flex items-start justify-between">
        <div className="space-y-1 min-w-0">
          <p className="text-xs sm:text-sm text-muted-foreground truncate">{title}</p>
          <p className="text-xl sm:text-2xl font-bold tracking-tight">{value}</p>
          {change && (
            <p className={`text-[11px] sm:text-xs font-medium ${
              changeType === 'positive' ? 'text-success' :
              changeType === 'negative' ? 'text-destructive' :
              'text-muted-foreground'
            }`}>
              {change}
            </p>
          )}
          {description && <p className="text-[11px] sm:text-xs text-muted-foreground">{description}</p>}
        </div>
        <div className="rounded-lg bg-primary/10 p-2 sm:p-2.5 shrink-0">
          <Icon className="h-4 w-4 sm:h-5 sm:w-5 text-primary" />
        </div>
      </div>
    </motion.div>
  );
}
