import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '@/app/providers/AuthProvider';

interface PermissionGuardProps {
  permission: string | string[];
  mode?: 'any' | 'all';
  fallback?: ReactNode;
  children: ReactNode;
}

export function PermissionGuard({ permission, mode = 'any', fallback, children }: PermissionGuardProps) {
  const { can, isAuthenticated } = useAuth();

  if (!isAuthenticated) return <Navigate to="/login" replace />;

  const perms = Array.isArray(permission) ? permission : [permission];
  const hasAccess = mode === 'all'
    ? perms.every(p => can(p))
    : perms.some(p => can(p));

  if (!hasAccess) {
    return fallback ? <>{fallback}</> : <Navigate to="/" replace />;
  }

  return <>{children}</>;
}
