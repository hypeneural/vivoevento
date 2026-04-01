import { useAuth } from '@/app/providers/AuthProvider';

/**
 * Convenience hook for session data.
 *
 * @example
 * const { meUser, meOrganization, isAuthenticated } = useSession();
 */
export function useSession() {
  const { meUser, meOrganization, meSubscription, isAuthenticated, isLoading } = useAuth();

  return {
    meUser,
    meOrganization,
    meSubscription,
    isAuthenticated,
    isLoading,

    /** Organization name */
    orgName: meOrganization?.name || '',

    /** User initials for avatar */
    userInitials: meUser?.name
      .split(' ')
      .map(n => n[0])
      .join('')
      .slice(0, 2)
      .toUpperCase() || '',
  };
}
