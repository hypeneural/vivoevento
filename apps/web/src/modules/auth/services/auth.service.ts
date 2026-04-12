/**
 * Auth Service — Handles all authentication operations.
 *
 * Supports both mock and real API modes via VITE_USE_MOCK env var.
 *
 * API Contract:
 *   POST /api/v1/auth/login           { login, password, device_name }
 *   POST /api/v1/auth/logout
 *   POST /api/v1/auth/forgot-password { login }
 *   POST /api/v1/auth/forgot-password/resend-otp { session_token }
 *   POST /api/v1/auth/forgot-password/verify-otp { session_token, code }
 *   POST /api/v1/auth/reset-password  { session_token, password, password_confirmation }
 *   GET  /api/v1/auth/me
 *   PATCH /api/v1/auth/me             { name?, phone?, preferences? }
 *   PATCH /api/v1/auth/me/password    { current_password, password, password_confirmation }
 *   POST /api/v1/auth/me/avatar       FormData { avatar: File }
 *   DELETE /api/v1/auth/me/avatar
 *   GET  /api/v1/access/matrix
 */

import { api, setToken, removeToken, hasToken } from '@/lib/api';
import type {
  LoginPayload, LoginResponse,
  RegisterRequestOtpPayload, RegisterRequestOtpResponse,
  RegisterResendOtpPayload, RegisterResendOtpResponse,
  RegisterVerifyOtpPayload, RegisterVerifyOtpResponse,
  ForgotPasswordPayload, ForgotPasswordResponse, ForgotPasswordRequestOtpResponse,
  ForgotPasswordResendOtpPayload, ForgotPasswordResendOtpResponse,
  ForgotPasswordVerifyOtpPayload, ForgotPasswordVerifyOtpResponse,
  ResetPasswordPayload, ResetPasswordResponse, ResetPasswordWithOtpPayload,
  UpdatePasswordPayload, MessageResponse, AvatarUploadResponse,
  MeResponse, MeUser, MeOrganization, MeAccess, MeSubscription, MeResolvedEntitlements,
  AccessMatrixResponse,
} from '@/lib/api-types';
import { mockUsers, mockOrganizations, buildMockSession } from '@/shared/mock/data';
import { formatRoleLabel } from '@/shared/auth/labels';

export const AUTH_USE_MOCK = import.meta.env.VITE_USE_MOCK !== 'false';
const USE_MOCK = AUTH_USE_MOCK;

// ─── Persistence ───────────────────────────────────────────

const SESSION_KEY = 'eventovivo_session';
const MOCK_SIGNUP_OTP_KEY = 'eventovivo_mock_signup_otp';
const MOCK_FORGOT_OTP_KEY = 'eventovivo_mock_forgot_otp';

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

type MockSignupOtpState = {
  session_token: string;
  name: string;
  phone: string;
  journey: 'partner_signup' | 'trial_event' | 'single_event_checkout' | 'admin_assisted';
  code: string;
  resend_available_at: number;
};

type MockForgotOtpState = {
  session_token: string;
  login: string;
  method: 'whatsapp' | 'email';
  destination_masked: string;
  code: string;
  resend_available_at: number;
  user_id: string | null;
  verified: boolean;
};

// ─── Mock Helpers ──────────────────────────────────────────

function buildMockMeResponse(userId: string): MeResponse | null {
  const user = mockUsers.find(u => u.id === userId);
  if (!user) return null;

  const org = mockOrganizations.find(o => o.id === user.organizationId);
  const session = buildMockSession(user);

  const entitlements: MeResolvedEntitlements = {
    version: 1,
    organization_type: org?.type || 'partner',
    modules: {
      live_gallery: true,
      wall: session.enabledModules.includes('wall'),
      play: session.enabledModules.includes('play'),
      hub: session.enabledModules.includes('hub'),
      whatsapp_ingestion: true,
      analytics_advanced: session.enabledModules.includes('analytics'),
    },
    limits: {
      max_active_events: 10,
      retention_days: 90,
    },
    branding: {
      white_label: false,
      custom_domain: false,
    },
    source_summary: [{
      source_type: 'subscription',
      status: 'active',
      plan_id: 1,
      plan_key: 'pro-parceiro',
      plan_name: 'Pro Parceiro',
      billing_cycle: 'monthly',
      starts_at: '2026-04-01T00:00:00.000Z',
      trial_ends_at: null,
      renews_at: '2026-05-01T00:00:00.000Z',
      ends_at: null,
      canceled_at: null,
      cancel_at_period_end: false,
      cancellation_effective_at: null,
      active: true,
    }],
  };

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
        name: formatRoleLabel(user.role.replace(/_/g, '-'), user.role),
      },
      permissions: session.permissions,
      preferences: {
        theme: 'light',
        timezone: 'America/Sao_Paulo',
        locale: 'pt-BR',
        email_notifications: true,
        push_notifications: false,
        compact_mode: false,
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
    active_context: org ? {
      type: 'organization',
      organization_id: parseInt(org.id) || 1,
      event_id: null,
      role_key: user.role.replace(/_/g, '-'),
      role_label: formatRoleLabel(user.role.replace(/_/g, '-'), user.role),
      capabilities: [],
      entry_path: '/',
    } : null,
    workspaces: {
      organizations: org ? [{
        organization_id: parseInt(org.id) || 1,
        organization_uuid: org.id,
        organization_name: org.tradeName || org.name,
        organization_slug: org.slug || org.name.toLowerCase().replace(/\s+/g, '-'),
        organization_type: org.type || 'partner',
        organization_status: org.status,
        role_key: user.role.replace(/_/g, '-'),
        role_label: formatRoleLabel(user.role.replace(/_/g, '-'), user.role),
        is_owner: user.role === 'partner_owner',
        entry_path: '/',
      }] : [],
      event_accesses: [],
    },
    access: {
      accessible_modules: session.enabledModules,
      modules: session.enabledModules.map(m => ({ key: m, enabled: true, visible: true })),
      feature_flags: {
        live_gallery: entitlements.modules.live_gallery,
        wall: entitlements.modules.wall,
        play_memory: entitlements.modules.play,
        play_puzzle: entitlements.modules.play,
        hub: entitlements.modules.hub,
        white_label: entitlements.branding.white_label,
        whatsapp_ingestion: entitlements.modules.whatsapp_ingestion,
        analytics_advanced: entitlements.modules.analytics_advanced,
        custom_domain: entitlements.branding.custom_domain,
      },
      entitlements,
    },
    subscription: {
      plan_key: 'pro-parceiro',
      plan_name: 'Pro Parceiro',
      billing_type: 'recurring',
      status: 'active',
      trial_ends_at: null,
      renews_at: '2026-05-01T00:00:00.000Z',
      ends_at: null,
      canceled_at: null,
      cancel_at_period_end: false,
      cancellation_effective_at: null,
    },
  };
}

function resolveMockJourney(journey?: MockSignupOtpState['journey']) {
  switch (journey) {
    case 'single_event_checkout':
      return {
        organizationType: 'direct_customer',
        nextPath: '/events/create',
        description: 'Sua conta ja esta pronta. Agora configure o seu evento para seguir com a contratacao.',
      };
    case 'trial_event':
      return {
        organizationType: 'partner',
        nextPath: '/events/create',
        description: 'Sua conta ja esta pronta. Agora crie seu evento teste para validar a experiencia.',
      };
    case 'admin_assisted':
      return {
        organizationType: 'partner',
        nextPath: '/events',
        description: 'Seu acesso inicial ja esta pronto. Continue para revisar ou configurar o evento.',
      };
    case 'partner_signup':
    default:
      return {
        organizationType: 'partner',
        nextPath: '/plans',
        description: 'Sua conta ja esta pronta. Agora escolha um plano para ativar seu primeiro evento.',
      };
  }
}

function buildMockSignupSession(
  name: string,
  phone: string,
  journey: MockSignupOtpState['journey'] = 'partner_signup',
): MeResponse {
  const generatedId = Date.now();
  const normalizedPhone = phone.startsWith('55') ? phone : `55${phone}`;
  const journeyConfig = resolveMockJourney(journey);
  const slug = name
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    || 'nova-conta';

  const permissions = [
    'organizations.view', 'organizations.update',
    'users.view', 'users.manage',
    'clients.view', 'clients.create', 'clients.update', 'clients.delete',
    'events.view', 'events.create', 'events.update', 'events.publish', 'events.archive',
    'events.activate', 'events.manage_branding', 'events.manage_team',
    'channels.view', 'channels.manage',
    'media.view', 'media.moderate', 'media.delete',
    'gallery.view', 'gallery.manage', 'gallery.builder.manage',
    'wall.view', 'wall.manage',
    'play.view', 'play.manage',
    'hub.view', 'hub.manage',
    'billing.view', 'billing.manage', 'billing.purchase', 'billing.manage_subscription',
    'analytics.view',
    'settings.manage',
    'white_label.manage',
    'notifications.view', 'notifications.manage',
  ];

  const accessibleModules = [
    'dashboard',
    'events',
    'media',
    'moderation',
    'gallery',
    'wall',
    'play',
    'hub',
    'whatsapp',
    'clients',
    'plans',
    'analytics',
    'settings',
  ];

  const entitlements: MeResolvedEntitlements = {
    version: 1,
    organization_type: journeyConfig.organizationType,
    modules: {
      live_gallery: true,
      wall: true,
      play: true,
      hub: true,
      whatsapp_ingestion: true,
      analytics_advanced: true,
    },
    limits: {
      max_active_events: journey === 'single_event_checkout' ? 1 : 10,
      retention_days: 30,
    },
    branding: {
      white_label: false,
      custom_domain: false,
    },
    source_summary: [],
  };

  return {
    user: {
      id: generatedId,
      name,
      email: `wa+${normalizedPhone}@eventovivo.local`,
      phone: normalizedPhone,
      avatar_url: null,
      status: 'active',
      role: {
        key: 'partner-owner',
        name: 'Proprietario',
      },
      permissions,
      preferences: {
        theme: 'light',
        timezone: 'America/Sao_Paulo',
        locale: 'pt-BR',
        email_notifications: true,
        push_notifications: false,
        compact_mode: false,
      },
      last_login_at: new Date().toISOString(),
    },
    organization: {
      id: generatedId,
      uuid: `mock-org-${slug}`,
      type: journeyConfig.organizationType,
      name,
      slug,
      status: 'active',
      logo_url: null,
      branding: {
        primary_color: null,
        secondary_color: null,
        subdomain: null,
        custom_domain: null,
      },
    },
    active_context: {
      type: 'organization',
      organization_id: generatedId,
      event_id: null,
      role_key: 'partner-owner',
      role_label: 'Proprietario',
      capabilities: [],
      entry_path: '/',
    },
    workspaces: {
      organizations: [{
        organization_id: generatedId,
        organization_uuid: `mock-org-${slug}`,
        organization_name: name,
        organization_slug: slug,
        organization_type: journeyConfig.organizationType,
        organization_status: 'active',
        role_key: 'partner-owner',
        role_label: 'Proprietario',
        is_owner: true,
        entry_path: '/',
      }],
      event_accesses: [],
    },
    access: {
      accessible_modules: accessibleModules,
      modules: accessibleModules.map(key => ({ key, enabled: true, visible: true })),
      feature_flags: {
        live_gallery: entitlements.modules.live_gallery,
        wall: entitlements.modules.wall,
        play_memory: entitlements.modules.play,
        play_puzzle: entitlements.modules.play,
        hub: entitlements.modules.hub,
        white_label: entitlements.branding.white_label,
        whatsapp_ingestion: entitlements.modules.whatsapp_ingestion,
        analytics_advanced: entitlements.modules.analytics_advanced,
        custom_domain: entitlements.branding.custom_domain,
      },
      entitlements,
    },
    subscription: null,
  };
}

function getMockSignupOtpState(): MockSignupOtpState | null {
  try {
    const raw = localStorage.getItem(MOCK_SIGNUP_OTP_KEY);
    if (!raw) return null;
    return JSON.parse(raw) as MockSignupOtpState;
  } catch {
    localStorage.removeItem(MOCK_SIGNUP_OTP_KEY);
    return null;
  }
}

function persistMockSignupOtpState(state: MockSignupOtpState): void {
  localStorage.setItem(MOCK_SIGNUP_OTP_KEY, JSON.stringify(state));
}

function clearMockSignupOtpState(): void {
  localStorage.removeItem(MOCK_SIGNUP_OTP_KEY);
}

function getMockForgotOtpState(): MockForgotOtpState | null {
  try {
    const raw = localStorage.getItem(MOCK_FORGOT_OTP_KEY);
    if (!raw) return null;
    return JSON.parse(raw) as MockForgotOtpState;
  } catch {
    localStorage.removeItem(MOCK_FORGOT_OTP_KEY);
    return null;
  }
}

function persistMockForgotOtpState(state: MockForgotOtpState): void {
  localStorage.setItem(MOCK_FORGOT_OTP_KEY, JSON.stringify(state));
}

function clearMockForgotOtpState(): void {
  localStorage.removeItem(MOCK_FORGOT_OTP_KEY);
}

function maskPhone(phone: string): string {
  const normalizedPhone = phone.startsWith('55') ? phone : `55${phone}`;
  return `+55 (${normalizedPhone.slice(2, 4)}) *****${normalizedPhone.slice(-4)}`;
}

function maskEmail(email: string): string {
  const [localPart, domain] = email.split('@');
  if (!localPart || !domain) return email;

  const prefix = localPart.slice(0, 1);
  const suffix = localPart.length > 2 ? localPart.slice(-1) : '';
  const hidden = '*'.repeat(Math.max(localPart.length - prefix.length - suffix.length, 2));

  return `${prefix}${hidden}${suffix}@${domain}`;
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
   * Start signup by requesting an OTP via WhatsApp.
   */
  async requestRegisterOtp(payload: RegisterRequestOtpPayload): Promise<RegisterRequestOtpResponse> {
    if (USE_MOCK) {
      await delay(700);

      const digits = payload.phone.replace(/\D/g, '');
      const normalizedPhone = digits.startsWith('55') ? digits : `55${digits}`;
      const alreadyExists = mockUsers.some(user =>
        user.phone?.replace(/\D/g, '') === normalizedPhone ||
        user.phone?.replace(/\D/g, '') === digits
      );

      if (alreadyExists) {
        throw {
          status: 422,
          message: 'Este WhatsApp ja possui cadastro.',
          validationErrors: {
            phone: ['Este WhatsApp ja possui cadastro.'],
          },
        };
      }

      const state: MockSignupOtpState = {
        session_token: `mock_signup_${Date.now()}`,
        name: payload.name.trim(),
        phone: normalizedPhone,
        journey: payload.journey ?? 'partner_signup',
        code: '123456',
        resend_available_at: Date.now() + 30000,
      };

      persistMockSignupOtpState(state);

      return {
        message: 'Enviamos um codigo de 6 digitos para o seu WhatsApp.',
        session_token: state.session_token,
        delivery: 'whatsapp',
        phone_masked: `+55 (${normalizedPhone.slice(2, 4)}) *****${normalizedPhone.slice(-4)}`,
        expires_in: 900,
        resend_in: 30,
        debug_code: state.code,
      };
    }

    return api.post<RegisterRequestOtpResponse>('/auth/register/request-otp', {
      body: payload,
    });
  },

  /**
   * Resend signup OTP after cooldown.
   */
  async resendRegisterOtp(payload: RegisterResendOtpPayload): Promise<RegisterResendOtpResponse> {
    if (USE_MOCK) {
      await delay(500);

      const state = getMockSignupOtpState();
      if (!state || state.session_token !== payload.session_token) {
        throw {
          status: 422,
          message: 'Sessao expirada. Solicite um novo codigo.',
          validationErrors: {
            session_token: ['Sessao expirada. Solicite um novo codigo.'],
          },
        };
      }

      const secondsRemaining = Math.ceil((state.resend_available_at - Date.now()) / 1000);
      if (secondsRemaining > 0) {
        throw {
          status: 429,
          message: `Aguarde ${secondsRemaining}s para reenviar o codigo.`,
        };
      }

      const nextState: MockSignupOtpState = {
        ...state,
        code: '123456',
        resend_available_at: Date.now() + 30000,
      };

      persistMockSignupOtpState(nextState);

      return {
        message: 'Enviamos um codigo de 6 digitos para o seu WhatsApp.',
        session_token: nextState.session_token,
        delivery: 'whatsapp',
        phone_masked: `+55 (${nextState.phone.slice(2, 4)}) *****${nextState.phone.slice(-4)}`,
        expires_in: 900,
        resend_in: 30,
        debug_code: nextState.code,
      };
    }

    return api.post<RegisterResendOtpResponse>('/auth/register/resend-otp', {
      body: payload,
    });
  },

  /**
   * Verify signup OTP and store the auth token.
   * The full session is fetched later by the caller to preserve the welcome screen.
   */
  async verifyRegisterOtp(payload: RegisterVerifyOtpPayload): Promise<RegisterVerifyOtpResponse> {
    if (USE_MOCK) {
      await delay(600);

      const state = getMockSignupOtpState();
      if (!state || state.session_token !== payload.session_token) {
        throw {
          status: 422,
          message: 'Sessao expirada. Solicite um novo codigo.',
          validationErrors: {
            session_token: ['Sessao expirada. Solicite um novo codigo.'],
          },
        };
      }

      if (payload.code !== state.code) {
        throw {
          status: 422,
          message: 'Codigo invalido.',
          validationErrors: {
            code: ['Codigo invalido.'],
          },
        };
      }

      const token = `mock_signup_token_${Date.now()}`;
      const session = buildMockSignupSession(state.name, state.phone, state.journey);
      const journeyConfig = resolveMockJourney(state.journey);

      setToken(token);
      persistSession(session);
      clearMockSignupOtpState();

      return {
        message: 'WhatsApp validado com sucesso.',
        user: {
          id: session.user.id,
          name: session.user.name,
          email: session.user.email,
          phone: session.user.phone,
          avatar_path: null,
          status: session.user.status,
          email_verified_at: null,
          last_login_at: session.user.last_login_at,
          created_at: new Date().toISOString(),
          roles: ['partner-owner'],
          organizations: session.organization ? [{
            id: session.organization.id,
            name: session.organization.name,
            slug: session.organization.slug,
            role_key: 'partner-owner',
            is_owner: true,
          }] : [],
        },
        token,
        onboarding: {
          title: `Bem-vindo, ${state.name}!`,
          description: journeyConfig.description,
          next_path: journeyConfig.nextPath,
        },
      };
    }

    const result = await api.post<RegisterVerifyOtpResponse>('/auth/register/verify-otp', {
      body: payload,
    });

    setToken(result.token);
    return result;
  },

  /**
   * Login with mock user (dev only)
   */
  async loginMock(userId: string): Promise<MeResponse> {
    if (!USE_MOCK) {
      throw { status: 403, message: 'Acesso rapido indisponivel neste ambiente.' };
    }

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
        entitlements: session?.access.entitlements || {
          version: 1,
          organization_type: null,
          modules: {
            live_gallery: true,
            wall: false,
            play: false,
            hub: true,
            whatsapp_ingestion: false,
            analytics_advanced: false,
          },
          limits: {
            max_active_events: null,
            retention_days: null,
          },
          branding: {
            white_label: false,
            custom_domain: false,
          },
          source_summary: [],
        },
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
    preferences?: {
      theme?: 'light' | 'dark';
      locale?: string;
      email_notifications?: boolean;
      push_notifications?: boolean;
      compact_mode?: boolean;
    };
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
   * Update the password for the authenticated user.
   */
  async updatePassword(payload: UpdatePasswordPayload): Promise<MessageResponse> {
    if (USE_MOCK) {
      await delay(400);

      if (payload.current_password !== 'password') {
        throw {
          status: 422,
          message: 'A senha atual informada nao confere.',
          validationErrors: {
            current_password: ['A senha atual informada nao confere.'],
          },
        };
      }

      return { message: 'Senha atualizada com sucesso.' };
    }

    return api.patch<MessageResponse>('/auth/me/password', {
      body: payload,
    });
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
   * Start forgot-password OTP flow.
   */
  async requestForgotPasswordOtp(payload: ForgotPasswordPayload): Promise<ForgotPasswordRequestOtpResponse> {
    if (USE_MOCK) {
      await delay(800);

      const rawLogin = payload.login.trim();
      const loginDigits = rawLogin.replace(/\D/g, '');
      const isPhone = loginDigits.length >= 10 && !rawLogin.includes('@');
      const normalizedLogin = isPhone
        ? (loginDigits.startsWith('55') ? loginDigits : `55${loginDigits}`)
        : rawLogin.toLowerCase();

      const existingState = getMockForgotOtpState();
      const secondsRemaining = existingState && existingState.login === normalizedLogin
        ? Math.ceil((existingState.resend_available_at - Date.now()) / 1000)
        : 0;

      if (existingState && existingState.login === normalizedLogin && secondsRemaining > 0) {
        return {
          message: 'Se encontrarmos sua conta, vamos enviar um codigo de 6 digitos para confirmar sua identidade.',
          session_token: existingState.session_token,
          method: existingState.method,
          destination_masked: existingState.destination_masked,
          expires_in: 900,
          resend_in: secondsRemaining,
          debug_code: existingState.code,
        };
      }

      const user = mockUsers.find(u =>
        isPhone
          ? (
              u.phone?.replace(/\D/g, '') === normalizedLogin
              || u.phone?.replace(/\D/g, '') === loginDigits
            )
          : u.email.toLowerCase() === normalizedLogin
      );

      const state: MockForgotOtpState = {
        session_token: `mock_forgot_${Date.now()}`,
        login: normalizedLogin,
        method: isPhone ? 'whatsapp' : 'email',
        destination_masked: isPhone ? maskPhone(normalizedLogin) : maskEmail(normalizedLogin),
        code: '123456',
        resend_available_at: Date.now() + 30000,
        user_id: user?.id ?? null,
        verified: false,
      };

      persistMockForgotOtpState(state);

      return {
        message: 'Se encontrarmos sua conta, vamos enviar um codigo de 6 digitos para confirmar sua identidade.',
        session_token: state.session_token,
        method: state.method,
        destination_masked: state.destination_masked,
        expires_in: 900,
        resend_in: 30,
        debug_code: state.code,
      };
    }

    return api.post<ForgotPasswordRequestOtpResponse>('/auth/forgot-password', {
      body: payload,
    });
  },

  /**
   * Resend forgot-password OTP.
   */
  async resendForgotPasswordOtp(payload: ForgotPasswordResendOtpPayload): Promise<ForgotPasswordResendOtpResponse> {
    if (USE_MOCK) {
      await delay(500);

      const state = getMockForgotOtpState();
      if (!state || state.session_token !== payload.session_token) {
        throw {
          status: 422,
          message: 'Sessao expirada. Solicite um novo codigo.',
          validationErrors: {
            session_token: ['Sessao expirada. Solicite um novo codigo.'],
          },
        };
      }

      const secondsRemaining = Math.ceil((state.resend_available_at - Date.now()) / 1000);
      if (secondsRemaining > 0) {
        throw {
          status: 429,
          message: `Aguarde ${secondsRemaining}s para reenviar o codigo.`,
        };
      }

      const nextState: MockForgotOtpState = {
        ...state,
        code: '123456',
        resend_available_at: Date.now() + 30000,
        verified: false,
      };

      persistMockForgotOtpState(nextState);

      return {
        message: 'Se encontrarmos sua conta, vamos enviar um codigo de 6 digitos para confirmar sua identidade.',
        session_token: nextState.session_token,
        method: nextState.method,
        destination_masked: nextState.destination_masked,
        expires_in: 900,
        resend_in: 30,
        debug_code: nextState.code,
      };
    }

    return api.post<ForgotPasswordResendOtpResponse>('/auth/forgot-password/resend-otp', {
      body: payload,
    });
  },

  /**
   * Verify forgot-password OTP before showing the new password form.
   */
  async verifyForgotPasswordOtp(payload: ForgotPasswordVerifyOtpPayload): Promise<ForgotPasswordVerifyOtpResponse> {
    if (USE_MOCK) {
      await delay(600);

      const state = getMockForgotOtpState();
      if (!state || state.session_token !== payload.session_token) {
        throw {
          status: 422,
          message: 'Sessao expirada. Solicite um novo codigo.',
          validationErrors: {
            session_token: ['Sessao expirada. Solicite um novo codigo.'],
          },
        };
      }

      if (!state.user_id || payload.code !== state.code) {
        throw {
          status: 422,
          message: 'Codigo invalido.',
          validationErrors: {
            code: ['Codigo invalido.'],
          },
        };
      }

      persistMockForgotOtpState({
        ...state,
        verified: true,
      });

      return {
        message: 'Codigo validado com sucesso.',
        session_token: state.session_token,
        method: state.method,
        destination_masked: state.destination_masked,
      };
    }

    return api.post<ForgotPasswordVerifyOtpResponse>('/auth/forgot-password/verify-otp', {
      body: payload,
    });
  },

  /**
   * Complete forgot-password reset after OTP validation.
   */
  async resetPasswordWithOtp(payload: ResetPasswordWithOtpPayload): Promise<MeResponse> {
    if (USE_MOCK) {
      await delay(600);

      const state = getMockForgotOtpState();
      if (!state || state.session_token !== payload.session_token) {
        throw {
          status: 422,
          message: 'Sessao expirada. Solicite um novo codigo.',
          validationErrors: {
            session_token: ['Sessao expirada. Solicite um novo codigo.'],
          },
        };
      }

      if (!state.verified) {
        throw {
          status: 422,
          message: 'Valide o codigo antes de redefinir a senha.',
          validationErrors: {
            session_token: ['Valide o codigo antes de redefinir a senha.'],
          },
        };
      }

      const user = mockUsers.find(u => u.id === state.user_id);
      if (!user) throw { status: 422, message: 'Sessao expirada. Solicite um novo codigo.' };

      const session = buildMockMeResponse(user.id);
      if (!session) throw { status: 500, message: 'Erro interno' };

      setToken(`mock_token_${user.id}_${Date.now()}`);
      persistSession(session);
      clearMockForgotOtpState();
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
  async uploadAvatar(file: File): Promise<AvatarUploadResponse> {
    if (USE_MOCK) {
      await delay(600);
      const url = URL.createObjectURL(file);
      const session = getPersistedSession();
      if (session) {
        session.user.avatar_url = url;
        persistSession(session);
      }
      return { avatar_path: 'mock/avatar.webp', avatar_url: url };
    }

    const formData = new FormData();
    formData.append('avatar', file);
    return api.upload<AvatarUploadResponse>('/auth/me/avatar', formData);
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

  async setOrganizationContext(organizationId: number): Promise<MeResponse> {
    if (USE_MOCK) {
      await delay(200);
      const session = getPersistedSession();
      if (!session) throw { status: 401, message: 'Nao autenticado' };

      const workspace = session.workspaces.organizations.find(item => item.organization_id === organizationId);
      if (!workspace) {
        throw { status: 403, message: 'Contexto de organizacao indisponivel.' };
      }

      session.active_context = {
        type: 'organization',
        organization_id: workspace.organization_id,
        event_id: null,
        role_key: workspace.role_key,
        role_label: workspace.role_label,
        capabilities: [],
        entry_path: '/',
      };
      session.organization = {
        id: workspace.organization_id,
        uuid: workspace.organization_uuid,
        type: workspace.organization_type ?? 'partner',
        name: workspace.organization_name,
        slug: workspace.organization_slug,
        status: workspace.organization_status ?? 'active',
        logo_url: session.organization?.id === workspace.organization_id ? session.organization.logo_url : null,
        branding: session.organization?.id === workspace.organization_id
          ? session.organization.branding
          : {
              primary_color: null,
              secondary_color: null,
              subdomain: null,
              custom_domain: null,
            },
      };
      persistSession(session);
      return session;
    }

    const session = await api.post<MeResponse>('/auth/context/organization', {
      body: { organization_id: organizationId },
    });
    persistSession(session);
    return session;
  },

  async setEventContext(eventId: number): Promise<MeResponse> {
    if (USE_MOCK) {
      await delay(200);
      const session = getPersistedSession();
      if (!session) throw { status: 401, message: 'Nao autenticado' };

      const workspace = session.workspaces.event_accesses.find(item => item.event_id === eventId);
      if (!workspace) {
        throw { status: 403, message: 'Contexto de evento indisponivel.' };
      }

      session.active_context = {
        type: 'event',
        organization_id: workspace.organization_id,
        event_id: workspace.event_id,
        role_key: workspace.role_key,
        role_label: workspace.role_label,
        capabilities: workspace.capabilities,
        entry_path: workspace.entry_path,
      };
      session.organization = null;
      persistSession(session);
      return session;
    }

    const session = await api.post<MeResponse>('/auth/context/event', {
      body: { event_id: eventId },
    });
    persistSession(session);
    return session;
  },

  /** Check if a token exists */
  hasToken,

  /** Get persisted session */
  getPersistedSession,
};

export default authService;
