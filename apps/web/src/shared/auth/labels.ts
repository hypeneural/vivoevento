const ROLE_LABELS: Record<string, string> = {
  'super-admin': 'Super Admin',
  super_admin: 'Super Admin',
  'platform-admin': 'Administrador da plataforma',
  platform_admin: 'Administrador da plataforma',
  'partner-owner': 'Propriet\u00E1rio',
  partner_owner: 'Propriet\u00E1rio',
  'partner-manager': 'Gerente',
  partner_manager: 'Gerente',
  'event-operator': 'Operador de evento',
  event_operator: 'Operador de evento',
  financial: 'Financeiro',
  financeiro: 'Financeiro',
  partner: 'Parceiro',
  client: 'Cliente',
  viewer: 'Visualizador',
};

const ROLE_NAME_FALLBACKS: Record<string, string> = {
  'owner da organizacao': 'Propriet\u00E1rio',
  'owner da organização': 'Propriet\u00E1rio',
  'gerente da organizacao': 'Gerente',
  'gerente da organização': 'Gerente',
  'admin da plataforma': 'Administrador da plataforma',
};

const STATUS_LABELS: Record<string, string> = {
  active: 'Ativo',
  inactive: 'Inativo',
  blocked: 'Bloqueado',
  suspended: 'Suspenso',
  pending: 'Pendente',
};

const THEME_LABELS: Record<string, string> = {
  light: 'Claro',
  dark: 'Escuro',
  system: 'Sistema',
};

export function formatRoleLabel(roleKey?: string | null, fallbackName?: string | null): string {
  if (roleKey && ROLE_LABELS[roleKey]) {
    return ROLE_LABELS[roleKey];
  }

  if (fallbackName) {
    const normalizedName = fallbackName.trim().toLowerCase();
    if (ROLE_NAME_FALLBACKS[normalizedName]) {
      return ROLE_NAME_FALLBACKS[normalizedName];
    }

    return fallbackName;
  }

  return 'Visualizador';
}

export function formatUserStatusLabel(status?: string | null): string {
  if (!status) return 'Nao informado';
  return STATUS_LABELS[status] ?? status;
}

export function formatThemeLabel(theme?: string | null): string {
  if (!theme) return 'Padrao';
  return THEME_LABELS[theme] ?? theme;
}
