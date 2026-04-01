import type { ReactNode } from 'react';
import { useAuth } from '@/app/providers/AuthProvider';
import { FeatureLock } from '@/shared/components/FeatureLock';

interface ModuleGuardProps {
  moduleKey: string;
  children: ReactNode;
}

/**
 * Route-level guard: renders children only if the organization has the module enabled.
 * Shows FeatureLock overlay if module is not available.
 */
export function ModuleGuard({ moduleKey, children }: ModuleGuardProps) {
  const { canAccessModule } = useAuth();

  if (!canAccessModule(moduleKey)) {
    return <FeatureLock moduleKey={moduleKey} />;
  }

  return <>{children}</>;
}
