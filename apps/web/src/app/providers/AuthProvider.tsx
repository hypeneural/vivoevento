import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

import type {
  LoginPayload,
  MeAccess,
  MeActiveContext,
  MeOrganization,
  MeResolvedEntitlements,
  MeResponse,
  MeSubscription,
  MeUser,
  MeWorkspaces,
} from '@/lib/api-types';
import { AUTH_USE_MOCK, authService, clearSession } from '@/modules/auth/services/auth.service';
import { mockUsers } from '@/shared/mock/data';

interface AuthContextType {
  meUser: MeUser | null;
  meOrganization: MeOrganization | null;
  activeContext: MeActiveContext | null;
  workspaces: MeWorkspaces;
  meAccess: MeAccess | null;
  meSubscription: MeSubscription | null;
  meEntitlements: MeResolvedEntitlements | null;
  preferredHomePath: string;
  isEventOnlySession: boolean;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (payload: LoginPayload) => Promise<void>;
  loginMock: (userId: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshSession: () => Promise<void>;
  setOrganizationContext: (organizationId: number) => Promise<void>;
  setEventContext: (eventId: number) => Promise<void>;
  can: (permission: string) => boolean;
  hasRole: (role: string) => boolean;
  canAccessModule: (moduleKey: string) => boolean;
  hasFeature: (featureKey: string) => boolean;
  availableUsers: Array<{ id: string; name: string; role: string; email: string; phone?: string }>;
}

const EMPTY_WORKSPACES: MeWorkspaces = {
  organizations: [],
  event_accesses: [],
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [session, setSession] = useState<MeResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const hydrateFromSession = useCallback((me: MeResponse) => {
    setSession(me);
  }, []);

  useEffect(() => {
    const persisted = authService.getPersistedSession();
    if (persisted) {
      hydrateFromSession(persisted);
    }
    setIsLoading(false);
  }, [hydrateFromSession]);

  useEffect(() => {
    const handleUnauthorized = () => {
      setSession(null);
      clearSession();
    };

    window.addEventListener('auth:unauthorized', handleUnauthorized);

    return () => window.removeEventListener('auth:unauthorized', handleUnauthorized);
  }, []);

  const login = useCallback(async (payload: LoginPayload) => {
    setIsLoading(true);
    try {
      const me = await authService.login(payload);
      hydrateFromSession(me);
    } finally {
      setIsLoading(false);
    }
  }, [hydrateFromSession]);

  const loginMock = useCallback(async (userId: string) => {
    setIsLoading(true);
    try {
      const me = await authService.loginMock(userId);
      hydrateFromSession(me);
    } finally {
      setIsLoading(false);
    }
  }, [hydrateFromSession]);

  const logout = useCallback(async () => {
    setIsLoading(true);
    try {
      await authService.logout();
    } finally {
      setSession(null);
      setIsLoading(false);
    }
  }, []);

  const refreshSession = useCallback(async () => {
    try {
      const me = await authService.getSession();
      hydrateFromSession(me);
    } catch {
      setSession(null);
      clearSession();
    }
  }, [hydrateFromSession]);

  const setOrganizationContext = useCallback(async (organizationId: number) => {
    const me = await authService.setOrganizationContext(organizationId);
    hydrateFromSession(me);
  }, [hydrateFromSession]);

  const setEventContext = useCallback(async (eventId: number) => {
    const me = await authService.setEventContext(eventId);
    hydrateFromSession(me);
  }, [hydrateFromSession]);

  const can = useCallback((permission: string) => {
    if (!session) return false;
    if (session.user.role.key === 'super-admin' || session.user.role.key === 'platform-admin') return true;
    return session.user.permissions.includes(permission);
  }, [session]);

  const hasRole = useCallback((role: string) => {
    return session?.user.role.key === role;
  }, [session]);

  const canAccessModule = useCallback((moduleKey: string) => {
    if (!session) return false;
    if (session.user.role.key === 'super-admin' || session.user.role.key === 'platform-admin') return true;
    return session.access.accessible_modules.includes(moduleKey);
  }, [session]);

  const hasFeature = useCallback((featureKey: string) => {
    if (!session) return false;
    return session.access.feature_flags[featureKey] === true;
  }, [session]);

  const workspaces = session?.workspaces ?? EMPTY_WORKSPACES;
  const activeContext = session?.active_context ?? null;
  const isEventOnlySession = !session?.organization
    && workspaces.organizations.length === 0
    && workspaces.event_accesses.length > 0;

  const preferredHomePath = useMemo(() => {
    if (!session) {
      return '/';
    }

    if (activeContext?.entry_path) {
      return activeContext.entry_path;
    }

    if (workspaces.event_accesses.length === 1 && workspaces.organizations.length === 0) {
      return workspaces.event_accesses[0]?.entry_path ?? '/my-events';
    }

    if (workspaces.event_accesses.length > 1 && workspaces.organizations.length === 0) {
      return '/my-events';
    }

    return '/';
  }, [activeContext, session, workspaces.event_accesses, workspaces.organizations.length]);

  const value = useMemo<AuthContextType>(() => ({
    meUser: session?.user ?? null,
    meOrganization: session?.organization ?? null,
    activeContext,
    workspaces,
    meAccess: session?.access ?? null,
    meSubscription: session?.subscription ?? null,
    meEntitlements: session?.access.entitlements ?? null,
    preferredHomePath,
    isEventOnlySession,
    isAuthenticated: !!session,
    isLoading,
    login,
    loginMock,
    logout,
    refreshSession,
    setOrganizationContext,
    setEventContext,
    can,
    hasRole,
    canAccessModule,
    hasFeature,
    availableUsers: AUTH_USE_MOCK
      ? mockUsers.map((user) => ({
          id: user.id,
          name: user.name,
          role: user.role,
          email: user.email,
          phone: user.phone,
        }))
      : [],
  }), [
    session,
    activeContext,
    workspaces,
    preferredHomePath,
    isEventOnlySession,
    isLoading,
    login,
    loginMock,
    logout,
    refreshSession,
    setOrganizationContext,
    setEventContext,
    can,
    hasRole,
    canAccessModule,
    hasFeature,
  ]);

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
}
