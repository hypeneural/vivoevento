import { useAuth } from '@/app/providers/AuthProvider';

/**
 * Hook for checking module access.
 */
export function useModuleAccess() {
  const { meAccess, meEntitlements, canAccessModule, hasFeature } = useAuth();

  return {
    /** Quick check: can user access module? */
    canAccess: canAccessModule,

    /** Check feature flag from plan */
    hasFeature,

    /** All accessible modules */
    accessibleModules: meAccess?.accessible_modules ?? [],

    /** Feature flags */
    featureFlags: meAccess?.feature_flags ?? {},

    /** Resolved organization entitlements */
    entitlements: meEntitlements,
  };
}
