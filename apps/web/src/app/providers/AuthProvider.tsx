import React, { createContext, useContext, useState, useCallback, useEffect, useMemo } from 'react';
import type { MeResponse, MeUser, MeOrganization, MeAccess, MeSubscription, MeResolvedEntitlements, LoginPayload } from '@/lib/api-types';
import { authService, clearSession } from '@/modules/auth/services/auth.service';
import { mockUsers } from '@/shared/mock/data';

// ─── Context ───────────────────────────────────────────────

interface AuthContextType {
  // Session data — comes directly from MeResponse
  meUser: MeUser | null;
  meOrganization: MeOrganization | null;
  meAccess: MeAccess | null;
  meSubscription: MeSubscription | null;
  meEntitlements: MeResolvedEntitlements | null;

  // Convenience
  isAuthenticated: boolean;
  isLoading: boolean;

  // Auth actions
  login: (payload: LoginPayload) => Promise<void>;
  loginMock: (userId: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshSession: () => Promise<void>;

  // Permissions (convenience)
  can: (permission: string) => boolean;
  hasRole: (role: string) => boolean;
  canAccessModule: (moduleKey: string) => boolean;
  hasFeature: (featureKey: string) => boolean;

  // Dev helpers
  availableUsers: Array<{ id: string; name: string; role: string; email: string; phone?: string }>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// ─── Provider ──────────────────────────────────────────────

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [session, setSession] = useState<MeResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // ─── Hydrate session from persisted state or API
  const hydrateFromSession = useCallback((me: MeResponse) => {
    setSession(me);
  }, []);

  // Hydrate on mount
  useEffect(() => {
    const persisted = authService.getPersistedSession();
    if (persisted) {
      hydrateFromSession(persisted);
    }
    setIsLoading(false);
  }, [hydrateFromSession]);

  // Listen for 401 events from API client
  useEffect(() => {
    const handleUnauthorized = () => {
      setSession(null);
      clearSession();
    };
    window.addEventListener('auth:unauthorized', handleUnauthorized);
    return () => window.removeEventListener('auth:unauthorized', handleUnauthorized);
  }, []);

  // ─── Actions ─────────────────────────────────────────────

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

  // ─── Permission Helpers ──────────────────────────────────

  const can = useCallback((permission: string) => {
    if (!session) return false;
    if (session.user.role.key === 'super-admin') return true;
    return session.user.permissions.includes(permission);
  }, [session]);

  const hasRole = useCallback((role: string) => {
    return session?.user.role.key === role;
  }, [session]);

  const canAccessModule = useCallback((moduleKey: string) => {
    if (!session) return false;
    if (session.user.role.key === 'super-admin') return true;
    return session.access.accessible_modules.includes(moduleKey);
  }, [session]);

  const hasFeature = useCallback((featureKey: string) => {
    if (!session) return false;
    return session.access.feature_flags[featureKey] === true;
  }, [session]);

  // ─── Memoized Value ──────────────────────────────────────

  const value = useMemo<AuthContextType>(() => ({
    meUser: session?.user ?? null,
    meOrganization: session?.organization ?? null,
    meAccess: session?.access ?? null,
    meSubscription: session?.subscription ?? null,
    meEntitlements: session?.access.entitlements ?? null,
    isAuthenticated: !!session,
    isLoading,
    login,
    loginMock,
    logout,
    refreshSession,
    can,
    hasRole,
    canAccessModule,
    hasFeature,
    availableUsers: mockUsers.map(u => ({
      id: u.id,
      name: u.name,
      role: u.role,
      email: u.email,
      phone: u.phone,
    })),
  }), [session, isLoading, login, loginMock, logout, refreshSession, can, hasRole, canAccessModule, hasFeature]);

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
