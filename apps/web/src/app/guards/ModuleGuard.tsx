import type { ReactNode } from 'react';
import { useAuth } from '@/app/providers/AuthProvider';
import { EmptyState } from '@/shared/components/EmptyState';
import { FeatureLock } from '@/shared/components/FeatureLock';

interface ModuleGuardProps {
  moduleKey: string;
  requiredPermissions?: string[];
  children: ReactNode;
}

/**
 * Route-level guard for module availability plus permission checks.
 */
export function ModuleGuard({ moduleKey, requiredPermissions = [], children }: ModuleGuardProps) {
  const { canAccessModule, can } = useAuth();

  const hasPermission = requiredPermissions.length === 0
    || requiredPermissions.some((permission) => can(permission));

  if (!hasPermission) {
    return (
      <EmptyState
        title="Acesso indisponivel"
        description="Sua sessao atual nao possui permissao para acessar este modulo."
      />
    );
  }

  if (!canAccessModule(moduleKey)) {
    return <FeatureLock moduleKey={moduleKey} />;
  }

  return <>{children}</>;
}
