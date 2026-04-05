// ═══════════════════════════════════════════════════════════
// MODULE ACCESS — Evento Vivo Feature Gating
// Controls which modules are accessible based on
// user permissions AND organization plan/config
// ═══════════════════════════════════════════════════════════

export type ModuleAccessResult = 'granted' | 'no_permission' | 'not_enabled' | 'upgrade_required';

export interface SystemModule {
  key: string;
  label: string;
  description: string;
  /** The permission needed to access this module */
  requiredPermission?: string;
  /** Whether this module requires org-level enablement */
  requiresOrgModule: boolean;
}

/** All system modules definition */
export const SYSTEM_MODULES: Record<string, SystemModule> = {
  dashboard: { key: 'dashboard', label: 'Dashboard', description: 'Visão geral da plataforma', requiredPermission: 'dashboard.view', requiresOrgModule: false },
  events: { key: 'events', label: 'Eventos', description: 'Gestão de eventos', requiredPermission: 'events.view', requiresOrgModule: true },
  media: { key: 'media', label: 'Mídias', description: 'Gestão de mídias recebidas', requiredPermission: 'media.view', requiresOrgModule: true },
  moderation: { key: 'moderation', label: 'Moderação', description: 'Moderação de conteúdo', requiredPermission: 'media.moderate', requiresOrgModule: true },
  gallery: { key: 'gallery', label: 'Galeria', description: 'Galeria de fotos ao vivo', requiredPermission: 'gallery.manage', requiresOrgModule: true },
  wall: { key: 'wall', label: 'Wall', description: 'Slideshow para telão', requiredPermission: 'wall.manage', requiresOrgModule: true },
  play: { key: 'play', label: 'Play', description: 'Minigames interativos', requiredPermission: 'play.manage', requiresOrgModule: true },
  hub: { key: 'hub', label: 'Hub', description: 'Página oficial do evento', requiredPermission: 'hub.manage', requiresOrgModule: true },
  whatsapp: { key: 'whatsapp', label: 'WhatsApp', description: 'Instâncias e conexão de WhatsApp', requiredPermission: 'channels.manage', requiresOrgModule: true },
  partners: { key: 'partners', label: 'Parceiros', description: 'Gestão de parceiros', requiredPermission: 'partners.view', requiresOrgModule: false },
  clients: { key: 'clients', label: 'Clientes', description: 'Gestão de clientes', requiredPermission: 'clients.view', requiresOrgModule: true },
  plans: { key: 'plans', label: 'Planos', description: 'Planos e billing', requiredPermission: 'plans.view', requiresOrgModule: false },
  analytics: { key: 'analytics', label: 'Analytics', description: 'Métricas e relatórios', requiredPermission: 'analytics.view', requiresOrgModule: true },
  audit: { key: 'audit', label: 'Auditoria', description: 'Log de ações', requiredPermission: 'audit.view', requiresOrgModule: false },
  settings: { key: 'settings', label: 'Configurações', description: 'Configurações do sistema', requiredPermission: 'settings.manage', requiresOrgModule: false },
};

/**
 * Resolve module access considering user permissions + org modules.
 */
export function resolveModuleAccess(
  moduleKey: string,
  userPermissions: string[],
  orgEnabledModules: string[],
  isSuperAdmin: boolean = false,
): ModuleAccessResult {
  const moduleDef = SYSTEM_MODULES[moduleKey];
  if (!moduleDef) return 'not_enabled';

  // Super admin bypasses all checks
  if (isSuperAdmin) return 'granted';

  // Check user permission
  if (moduleDef.requiredPermission && !userPermissions.includes(moduleDef.requiredPermission)) {
    return 'no_permission';
  }

  // Check org module enablement
  if (moduleDef.requiresOrgModule && !orgEnabledModules.includes(moduleKey)) {
    return 'upgrade_required';
  }

  return 'granted';
}
