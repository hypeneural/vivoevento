import type { ReactNode } from 'react';
import { useAuth } from '@/app/providers/AuthProvider';

interface PermissionBoundaryProps {
  /** Permission(s) required to render children */
  permission: string | string[];
  /** 'any' = at least one, 'all' = all required */
  mode?: 'any' | 'all';
  /** What to render instead if no permission (default: nothing) */
  fallback?: ReactNode;
  children: ReactNode;
}

/**
 * Inline permission boundary — use inside pages to conditionally
 * render buttons, sections, or actions based on permissions.
 *
 * @example
 * <PermissionBoundary permission="events.create">
 *   <Button>Novo Evento</Button>
 * </PermissionBoundary>
 */
export function PermissionBoundary({ permission, mode = 'any', fallback = null, children }: PermissionBoundaryProps) {
  const { can } = useAuth();

  const perms = Array.isArray(permission) ? permission : [permission];
  const hasAccess = mode === 'all'
    ? perms.every(p => can(p))
    : perms.some(p => can(p));

  if (!hasAccess) return <>{fallback}</>;
  return <>{children}</>;
}
