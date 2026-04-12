// ═══════════════════════════════════════════════════════════
// PERMISSIONS — Evento Vivo RBAC System
// Granular permissions aligned with backend
// ═══════════════════════════════════════════════════════════

import type { UserRole } from '@/shared/types';

/** All system permissions as constants */
export const PERMISSIONS = {
  // Dashboard
  DASHBOARD_VIEW: 'dashboard.view',

  // Events
  EVENTS_VIEW: 'events.view',
  EVENTS_CREATE: 'events.create',
  EVENTS_UPDATE: 'events.update',

  // Channels / WhatsApp
  CHANNELS_VIEW: 'channels.view',
  CHANNELS_MANAGE: 'channels.manage',

  // Media
  MEDIA_VIEW: 'media.view',
  MEDIA_MODERATE: 'media.moderate',

  // Modules
  GALLERY_MANAGE: 'gallery.manage',
  GALLERY_BUILDER_MANAGE: 'gallery.builder.manage',
  WALL_MANAGE: 'wall.manage',
  PLAY_MANAGE: 'play.manage',
  HUB_MANAGE: 'hub.manage',

  // Business
  PARTNERS_VIEW: 'partners.view.any',
  PARTNERS_MANAGE: 'partners.manage.any',
  CLIENTS_VIEW: 'clients.view',
  CLIENTS_MANAGE: 'clients.manage',

  // Billing
  PLANS_VIEW: 'plans.view',
  BILLING_MANAGE: 'billing.manage',
  BILLING_MANAGE_SUBSCRIPTION: 'billing.manage_subscription',

  // Admin
  ANALYTICS_VIEW: 'analytics.view',
  AUDIT_VIEW: 'audit.view',
  SETTINGS_MANAGE: 'settings.manage',
  BRANDING_MANAGE: 'branding.manage',
  INTEGRATIONS_MANAGE: 'integrations.manage',
  TEAM_MANAGE: 'team.manage',
} as const;

export type Permission = typeof PERMISSIONS[keyof typeof PERMISSIONS];

/** All permissions as a flat array */
export const ALL_PERMISSIONS: Permission[] = Object.values(PERMISSIONS);

/**
 * Default permissions per role.
 * In production, these come from the backend API.
 * This map serves as a fallback and for mock mode.
 */
export const ROLE_DEFAULT_PERMISSIONS: Record<UserRole, Permission[]> = {
  super_admin: ALL_PERMISSIONS,

  platform_admin: ALL_PERMISSIONS.filter(p => p !== 'billing.manage'),

  partner_owner: [
    'dashboard.view', 'events.view', 'events.create', 'events.update',
    'channels.view', 'channels.manage',
    'media.view', 'media.moderate', 'gallery.manage', 'gallery.builder.manage', 'wall.manage',
    'play.manage', 'hub.manage', 'clients.view', 'clients.manage',
    'plans.view', 'analytics.view', 'settings.manage', 'branding.manage',
    'integrations.manage', 'team.manage',
  ],

  partner_manager: [
    'dashboard.view', 'events.view', 'events.create', 'events.update',
    'channels.view', 'channels.manage',
    'media.view', 'media.moderate', 'gallery.manage', 'gallery.builder.manage', 'wall.manage',
    'play.manage', 'hub.manage', 'analytics.view', 'settings.manage',
    'team.manage',
  ],

  event_operator: [
    'dashboard.view', 'events.view', 'channels.view', 'media.view', 'media.moderate',
    'gallery.manage', 'gallery.builder.manage', 'wall.manage', 'play.manage',
  ],

  financial: [
    'dashboard.view', 'plans.view', 'billing.manage', 'billing.manage_subscription', 'analytics.view',
  ],

  partner: [
    'dashboard.view', 'events.view', 'media.view', 'gallery.manage',
    'analytics.view',
  ],

  viewer: [
    'dashboard.view', 'events.view', 'channels.view', 'gallery.manage',
  ],
};

/** Check if a user with given permissions can perform an action */
export function checkPermission(
  userPermissions: string[],
  required: string | string[],
  mode: 'any' | 'all' = 'any'
): boolean {
  // Wildcard — super admin shortcut
  if (userPermissions.includes('*')) return true;

  const requiredPerms = Array.isArray(required) ? required : [required];

  if (mode === 'all') {
    return requiredPerms.every(p => userPermissions.includes(p));
  }
  return requiredPerms.some(p => userPermissions.includes(p));
}
