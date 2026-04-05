import { useAuth } from '@/app/providers/AuthProvider';
import { formatRoleLabel } from '@/shared/auth/labels';

/**
 * Hook for permission checks.
 *
 * @example
 * const { can, hasRole, canAccessModule, hasFeature } = usePermissions();
 */
export function usePermissions() {
  const { meUser, can, hasRole, canAccessModule, hasFeature } = useAuth();

  return {
    can,
    hasRole,
    canAccessModule,
    hasFeature,

    /** Whether user is a platform admin (super-admin or platform-admin) */
    isPlatformAdmin: meUser?.role.key === 'super-admin' || meUser?.role.key === 'platform-admin',

    /** Whether user is an org owner */
    isOrgOwner: meUser?.role.key === 'partner-owner',

    /** Current role key */
    roleKey: meUser?.role.key ?? 'viewer',

    /** Current role display name */
    roleName: formatRoleLabel(meUser?.role.key, meUser?.role.name),
  };
}
