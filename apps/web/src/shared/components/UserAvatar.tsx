import { cn } from '@/lib/utils';

interface UserAvatarProps {
  name: string;
  avatarUrl?: string | null;
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  className?: string;
}

const SIZES = {
  xs: 'h-6 w-6 text-[9px]',
  sm: 'h-8 w-8 text-[10px]',
  md: 'h-10 w-10 text-xs',
  lg: 'h-12 w-12 text-sm',
  xl: 'h-20 w-20 text-xl sm:h-24 sm:w-24',
};

export function UserAvatar({ name, avatarUrl, size = 'md', className }: UserAvatarProps) {
  const initials = name
    .split(' ')
    .map(n => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

  if (avatarUrl) {
    return (
      <img
        src={avatarUrl}
        alt={name}
        className={cn(
          'rounded-full object-cover shrink-0',
          SIZES[size],
          className,
        )}
        onError={(e) => {
          // Fallback to initials on error
          (e.target as HTMLImageElement).style.display = 'none';
          (e.target as HTMLImageElement).nextElementSibling?.classList.remove('hidden');
        }}
      />
    );
  }

  return (
    <div
      className={cn(
        'rounded-full gradient-primary flex items-center justify-center font-bold text-primary-foreground shrink-0',
        SIZES[size],
        className,
      )}
      title={name}
    >
      {initials}
    </div>
  );
}
