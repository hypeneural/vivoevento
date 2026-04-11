const ROLE_LABELS: Record<string, string> = {
  'super-admin': 'Superadministrador',
  super_admin: 'Superadministrador',
  'platform-admin': 'Administrador da plataforma',
  platform_admin: 'Administrador da plataforma',
  'partner-owner': 'Proprietario',
  partner_owner: 'Proprietario',
  'partner-manager': 'Gerente / Secretaria',
  partner_manager: 'Gerente / Secretaria',
  'event-operator': 'Operar eventos',
  event_operator: 'Operar eventos',
  financial: 'Financeiro',
  financeiro: 'Financeiro',
  partner: 'Parceiro',
  client: 'Cliente',
  viewer: 'Acompanhar em leitura',
};

const ROLE_NAME_FALLBACKS: Record<string, string> = {
  'super admin': 'Superadministrador',
  'super administrador': 'Superadministrador',
  'owner da organizacao': 'Proprietario',
  'owner da organização': 'Proprietario',
  owner: 'Proprietario',
  'gerente da organizacao': 'Gerente / Secretaria',
  'gerente da organização': 'Gerente',
  manager: 'Gerente / Secretaria',
  'event operator': 'Operar eventos',
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

  return 'Acompanhar em leitura';
}

export function formatUserStatusLabel(status?: string | null): string {
  if (!status) return 'Nao informado';
  return STATUS_LABELS[status] ?? status;
}

export function formatThemeLabel(theme?: string | null): string {
  if (!theme) return 'Padrao';
  return THEME_LABELS[theme] ?? theme;
}
