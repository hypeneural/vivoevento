/**
 * Auth Service — Handles all authentication operations.
 *
 * Supports both mock and real API modes via VITE_USE_MOCK env var.
 *
 * API Contract:
 *   POST /api/v1/auth/login           { login, password, device_name }
 *   POST /api/v1/auth/logout
 *   POST /api/v1/auth/forgot-password { login }
 *   POST /api/v1/auth/reset-password  { login, code, password, password_confirmation }
 *   GET  /api/v1/auth/me
 *   PATCH /api/v1/auth/me             { name?, phone?, preferences? }
 *   POST /api/v1/auth/me/avatar       FormData { avatar: File }
 *   DELETE /api/v1/auth/me/avatar
 *   GET  /api/v1/access/matrix
 */

import { api, setToken, removeToken, getToken, hasToken } from '@/lib/api';
import type {
  LoginPayload, LoginResponse,
  ForgotPasswordPayload, ForgotPasswordResponse,
  ResetPasswordPayload, ResetPasswordResponse,
  MeResponse, MeUser, MeOrganization, MeAccess, MeSubscription,
  AccessMatrixResponse,
} from '@/lib/api-types';
import { mockUsers, mockOrganizations, buildMockSession } from '@/shared/mock/data';

const USE_MOCK = import.meta.env.VITE_USE_MOCK !== 'false';

// ─── Persistence ───────────────────────────────────────────

const SESSION_KEY = 'eventovivo_session';

export function persistSession(session: MeResponse): void {
  localStorage.setItem(SESSION_KEY, JSON.stringify(session));
}

export function getPersistedSession(): MeResponse | null {
  try {
    const str = localStorage.getItem(SESSION_KEY);
    if (!str || !hasToken()) return null;
    return JSON.parse(str) as MeResponse;
  } catch {
    clearSession();
    return null;
  }
}

export function clearSession(): void {
  localStorage.removeItem(SESSION_KEY);
  removeToken();
}

// ─── Mock Helpers ──────────────────────────────────────────

function buildMockMeResponse(userId: string): MeResponse | null {
  const user = mockUsers.find(u => u.id === userId);
  if (!user) return null;

  const org = mockOrganizations.find(o => o.id === user.organizationId);
  const session = buildMockSession(user);

  return {
    user: {
      id: parseInt(user.id) || 1,
      name: user.name,
      email: user.email,
      phone: user.phone || null,
      avatar_url: user.avatarUrl || null,
      status: 'active',
      role: {
        key: user.role.replace(/_/g, '-'),
        name: user.role,
      },
      permissions: session.permissions,
      preferences: {
        theme: 'light',
        timezone: 'America/Sao_Paulo',
        locale: 'pt-BR',
      },
      last_login_at: new Date().toISOString(),
    },
    organization: org ? {
      id: parseInt(org.id) || 1,
      uuid: org.id,
      type: org.type || 'partner',
      name: org.tradeName || org.name,
      slug: org.slug || org.name.toLowerCase().replace(/\s+/g, '-'),
      status: org.status,
      logo_url: org.logoUrl || null,
      branding: {
        primary_color: org.branding?.primaryColor || null,
        secondary_color: org.branding?.secondaryColor || null,
        subdomain: null,
        custom_domain: null,
      },
    } : null,
    access: {
      accessible_modules: session.enabledModules,
      modules: session.enabledModules.map(m => ({ key: m, enabled: true, visible: true })),
      feature_flags: {
        live_gallery: true,
        wall: session.enabledModules.includes('wall'),
        play_memory: session.enabledModules.includes('play'),
        play_puzzle: session.enabledModules.includes('play'),
        hub: session.enabledModules.includes('hub'),
        white_label: false,
        whatsapp_ingestion: true,
        analytics_advanced: session.enabledModules.includes('analytics'),
        custom_domain: false,
      },
    },
    subscription: {
      plan_key: 'pro-parceiro',
      plan_name: 'Pro Parceiro',
      billing_type: 'recurring',
      status: 'active',
      trial_ends_at: null,
      renews_at: '2026-05-01T00:00:00.000Z',
    },
  };
}

const delay = (ms: number) => new Promise(r => setTimeout(r, ms));

// ─── Service ───────────────────────────────────────────────

export const authService = {
  /**
   * Login with phone or email + password.
   * Returns the full session (MeResponse).
   */
  async login(payload: LoginPayload): Promise<MeResponse> {
    if (USE_MOCK) {
      await delay(600);

      // Find user by email or phone
      const loginLower = payload.login.toLowerCase();
      const loginDigits = payload.login.replace(/\D/g, '');

      const user = mockUsers.find(u =>
        u.email.toLowerCase() === loginLower ||
        u.phone?.replace(/\D/g, '') === loginDigits ||
        u.phone?.replace(/\D/g, '').endsWith(loginDigits)
      );

      if (!user) {
        throw { status: 422, message: 'WhatsApp ou senha incorretos.' };
      }

      const session = buildMockMeResponse(user.id);
      if (!session) throw { status: 500, message: 'Erro interno' };

      const mockToken = `mock_token_${user.id}_${Date.now()}`;
      setToken(mockToken);
      persistSession(session);
      return session;
    }

    // Real API: login → get token → fetch full session
    const loginResult = await api.post<LoginResponse>('/auth/login', {
      body: {
        login: payload.login,
        password: payload.password,
        device_name: payload.device_name || 'web-panel',
      },
    });

    setToken(loginResult.token);

    // Now fetch the full session
    return this.getSession();
  },

  /**
   * Login with mock user (dev only)
   */
  async loginMock(userId: string): Promise<MeResponse> {
    await delay(300);
    const session = buildMockMeResponse(userId);
    if (!session) throw { status: 404, message: 'Usuário não encontrado' };

    setToken(`mock_token_${userId}_${Date.now()}`);
    persistSession(session);
    return session;
  },

  /**
   * Logout — revokes current token.
   */
  async logout(): Promise<void> {
    if (!USE_MOCK && hasToken()) {
      try {
        await api.post('/auth/logout');
      } catch {
        // Ignore logout errors
      }
    }
    clearSession();
  },

  /**
   * Get full session from /auth/me.
   * This is the primary bootstrap call.
   */
  async getSession(): Promise<MeResponse> {
    if (!hasToken()) throw { status: 401, message: 'Não autenticado' };

    if (USE_MOCK) {
      const persisted = getPersistedSession();
      if (persisted) return persisted;
      throw { status: 401, message: 'Sessão expirada' };
    }

    const session = await api.get<MeResponse>('/auth/me');
    persistSession(session);
    return session;
  },

  /**
   * Refresh only access matrix (lighter than full /me).
   */
  async refreshAccess(): Promise<AccessMatrixResponse> {
    if (USE_MOCK) {
      await delay(200);
      const session = getPersistedSession();
      return {
        roles: [session?.user.role || { key: 'viewer', name: 'Visualizador' }],
        permissions: session?.user.permissions || [],
        modules: session?.access.modules || [],
        features: session?.access.feature_flags || {},
      };
    }

    return api.get<AccessMatrixResponse>('/access/matrix');
  },

  /**
   * Update profile (name, phone, preferences).
   */
  async updateProfile(data: {
    name?: string;
    phone?: string;
    preferences?: { theme?: 'light' | 'dark'; locale?: string };
  }): Promise<MeResponse> {
    if (USE_MOCK) {
      await delay(400);
      const session = getPersistedSession();
      if (!session) throw { status: 401, message: 'Não autenticado' };

      if (data.name) session.user.name = data.name;
      if (data.phone) session.user.phone = data.phone;
      if (data.preferences) {
        session.user.preferences = { ...session.user.preferences, ...data.preferences };
      }
      persistSession(session);
      return session;
    }

    const session = await api.patch<MeResponse>('/auth/me', { body: data });
    persistSession(session);
    return session;
  },

  /**
   * Request password reset code via WhatsApp or email.
   */
  async forgotPassword(payload: ForgotPasswordPayload): Promise<ForgotPasswordResponse> {
    if (USE_MOCK) {
      await delay(800);
      const loginDigits = payload.login.replace(/\D/g, '');
      const isPhone = loginDigits.length >= 10;
      return {
        message: 'Se encontrarmos sua conta, enviaremos um código de recuperação.',
        method: isPhone ? 'whatsapp' : 'email',
        expires_in: 900,
      };
    }

    return api.post<ForgotPasswordResponse>('/auth/forgot-password', {
      body: { login: payload.login },
    });
  },

  /**
   * Reset password with verification code.
   * Returns auto-login session.
   */
  async resetPassword(payload: ResetPasswordPayload): Promise<MeResponse> {
    if (USE_MOCK) {
      await delay(600);

      // In mock, any 6-digit code works
      if (payload.code.length !== 6) {
        throw { status: 422, message: 'Código inválido.' };
      }

      // Find user and auto-login
      const loginDigits = payload.login.replace(/\D/g, '');
      const user = mockUsers.find(u =>
        u.email.toLowerCase() === payload.login.toLowerCase() ||
        u.phone?.replace(/\D/g, '').endsWith(loginDigits)
      );

      if (!user) throw { status: 422, message: 'Código inválido ou expirado.' };

      const session = buildMockMeResponse(user.id);
      if (!session) throw { status: 500, message: 'Erro interno' };

      setToken(`mock_token_${user.id}_${Date.now()}`);
      persistSession(session);
      return session;
    }

    const result = await api.post<ResetPasswordResponse>('/auth/reset-password', {
      body: payload,
    });

    setToken(result.token);
    return this.getSession();
  },

  /**
   * Upload user avatar (multipart/form-data).
   */
  async uploadAvatar(file: File): Promise<{ avatar_path: string; avatar_url: string }> {
    if (USE_MOCK) {
      await delay(600);
      // In mock mode, generate a data URL from the file
      const url = URL.createObjectURL(file);
      const session = getPersistedSession();
      if (session) {
        session.user.avatar_url = url;
        persistSession(session);
      }
      return { avatar_path: 'mock/avatar.jpg', avatar_url: url };
    }

    const formData = new FormData();
    formData.append('avatar', file);
    return api.upload<{ avatar_path: string; avatar_url: string }>('/auth/me/avatar', formData);
  },

  /**
   * Delete user avatar.
   */
  async deleteAvatar(): Promise<void> {
    if (USE_MOCK) {
      await delay(300);
      const session = getPersistedSession();
      if (session) {
        session.user.avatar_url = null;
        persistSession(session);
      }
      return;
    }

    await api.delete('/auth/me/avatar');
  },

  /** Check if a token exists */
  hasToken,

  /** Get persisted session */
  getPersistedSession,
};

export default authService;
