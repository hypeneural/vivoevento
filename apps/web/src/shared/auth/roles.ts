// ═══════════════════════════════════════════════════════════
// ROLES — Evento Vivo RBAC System
// ═══════════════════════════════════════════════════════════

import type { UserRole } from '@/shared/types';

/** All system roles with hierarchy levels */
export const ROLE_HIERARCHY: Record<UserRole, number> = {
  super_admin: 100,
  platform_admin: 90,
  partner_owner: 70,
  partner_manager: 60,
  financial: 50,
  event_operator: 40,
  partner: 30,
  viewer: 10,
};

/** Check if roleA has equal or higher authority than roleB */
export function hasMinRole(userRole: UserRole, requiredRole: UserRole): boolean {
  return (ROLE_HIERARCHY[userRole] ?? 0) >= (ROLE_HIERARCHY[requiredRole] ?? 0);
}

/** Roles that can manage other team members */
export const TEAM_MANAGEMENT_ROLES: UserRole[] = [
  'super_admin', 'platform_admin', 'partner_owner', 'partner_manager',
];

/** Roles that are platform-level (not org-specific) */
export const PLATFORM_ROLES: UserRole[] = ['super_admin', 'platform_admin'];

/** Roles that are organization-level */
export const ORG_ROLES: UserRole[] = [
  'partner_owner', 'partner_manager', 'event_operator', 'financial', 'partner', 'viewer',
];
