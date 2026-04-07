type AuditGender = 'm' | 'f';
type AuditSubjectKey = 'account' | 'avatar' | 'client' | 'event' | 'media' | 'organization' | 'password' | 'subscription' | 'system' | 'user';

const SUBJECT_LABELS: Record<AuditSubjectKey, { gender: AuditGender; label: string }> = {
  account: { gender: 'f', label: 'Conta' },
  avatar: { gender: 'f', label: 'Foto de perfil' },
  client: { gender: 'm', label: 'Cliente' },
  event: { gender: 'm', label: 'Evento' },
  media: { gender: 'f', label: 'Midia' },
  organization: { gender: 'f', label: 'Organizacao' },
  password: { gender: 'f', label: 'Senha' },
  subscription: { gender: 'f', label: 'Assinatura' },
  system: { gender: 'm', label: 'Sistema' },
  user: { gender: 'm', label: 'Usuario' },
};

const SUBJECT_ALIASES: Record<string, AuditSubjectKey> = {
  account: 'account',
  avatar: 'avatar',
  client: 'client',
  customer: 'client',
  event: 'event',
  media: 'media',
  organization: 'organization',
  org: 'organization',
  password: 'password',
  subscription: 'subscription',
  system: 'system',
  user: 'user',
};

const ACTION_LABELS: Record<string, Record<AuditGender, string>> = {
  approved: { m: 'aprovado', f: 'aprovada' },
  canceled: { m: 'cancelado', f: 'cancelada' },
  cancelled: { m: 'cancelado', f: 'cancelada' },
  completed: { m: 'concluido', f: 'concluida' },
  created: { m: 'criado', f: 'criada' },
  creted: { m: 'criado', f: 'criada' },
  deleted: { m: 'excluido', f: 'excluida' },
  initiated: { m: 'iniciado', f: 'iniciada' },
  invited: { m: 'convidado', f: 'convidada' },
  published: { m: 'publicado', f: 'publicada' },
  rejected: { m: 'rejeitado', f: 'rejeitada' },
  removed: { m: 'removido', f: 'removida' },
  restored: { m: 'restaurado', f: 'restaurada' },
  sent: { m: 'enviado', f: 'enviada' },
  synced: { m: 'sincronizado', f: 'sincronizada' },
  updated: { m: 'atualizado', f: 'atualizada' },
  validated: { m: 'validado', f: 'validada' },
  viewed: { m: 'visualizado', f: 'visualizada' },
};

const EXACT_DESCRIPTION_LABELS: Record<string, string> = {
  'branding da organizacao atualizado': 'Identidade visual da organizacao atualizada',
  'cadastro concluido com otp via whatsapp': 'Cadastro concluido com codigo de verificacao via WhatsApp',
  'checkout de assinatura iniciado': 'Pagamento da assinatura iniciado',
  'checkout publico confirmado e convertido em compra avulsa': 'Pagamento publico confirmado e convertido em compra avulsa',
  'checkout publico de evento iniciado': 'Pagamento publico do evento iniciado',
  'configuracao de facesearch atualizada': 'Configuracao da busca por selfie atualizada',
  'configuracao de media intelligence atualizada': 'Configuracao de inteligencia de midia atualizada',
  'configuracao de safety atualizada': 'Configuracao de seguranca atualizada',
  'entitlements comerciais recalculados': 'Recursos comerciais recalculados',
  'evento criado via jornada admin rapida': 'Evento criado na jornada administrativa rapida',
  'evento trial criado via jornada publica': 'Evento de teste criado na jornada publica',
  'hero image do hub atualizada': 'Imagem principal da pagina de links atualizada',
  'hub do evento atualizado': 'Pagina de links do evento atualizada',
  'logo de parceiro do hub enviada': 'Logo de parceiro da pagina de links enviada',
  'modelo do hub salvo': 'Modelo da pagina de links salvo',
  'otp de recuperacao de senha validado': 'Codigo de verificacao de recuperacao de senha validado',
  'pagamento de assinatura registrado pelo gateway': 'Pagamento da assinatura registrado pelo gateway de pagamento',
  'pedido cancelado pelo gateway': 'Pedido cancelado pelo gateway de pagamento',
  'senha redefinida com otp': 'Senha redefinida com codigo de verificacao',
};

const DESCRIPTION_REPLACEMENTS: Array<{ pattern: RegExp; replacement: string }> = [
  { pattern: /\bbranding\b/gi, replacement: 'identidade visual' },
  { pattern: /\bcheckout\b/gi, replacement: 'pagamento' },
  { pattern: /\bfacesearch\b/gi, replacement: 'busca por selfie' },
  { pattern: /\bhero image\b/gi, replacement: 'imagem principal' },
  { pattern: /\bhub\b/gi, replacement: 'pagina de links' },
  { pattern: /\bmedia intelligence\b/gi, replacement: 'inteligencia de midia' },
  { pattern: /\botp\b/gi, replacement: 'codigo de verificacao' },
  { pattern: /\bsafety\b/gi, replacement: 'seguranca' },
];

const ACTIVITY_EVENT_LABELS: Record<string, string> = {
  'auth.login': 'Login realizado',
  'auth.logout': 'Logout realizado',
  'auth.register': 'Cadastro realizado',
  'gallery.hidden': 'Midia ocultada da galeria',
  'gallery.published': 'Midia publicada na galeria',
  'media.approved': 'Midia aprovada',
  'media.deleted': 'Midia excluida',
  'media.featured_updated': 'Destaque da midia atualizado',
  'media.pinned_updated': 'Fixacao da midia atualizada',
  'media.rejected': 'Midia rejeitada',
  'media.reprocess_requested': 'Reprocessamento da midia solicitado',
  'moderation.media.created': 'Midia enviada para moderacao',
  'moderation.media.deleted': 'Midia removida da moderacao',
  'moderation.media.updated': 'Midia da moderacao atualizada',
  'play.leaderboard.updated': 'Ranking dos jogos atualizado',
  'wall.diagnostics.updated': 'Diagnostico do telao atualizado',
  'wall.media.deleted': 'Midia do telao removida',
  'wall.media.updated': 'Midia do telao atualizada',
  'wall.settings.updated': 'Configuracoes do telao atualizadas',
};

const EVENT_TOKEN_LABELS: Record<string, string> = {
  approved: 'aprovada',
  auth: 'Acesso',
  created: 'criada',
  deleted: 'excluida',
  diagnostics: 'diagnostico',
  featured: 'destaque',
  gallery: 'galeria',
  hidden: 'ocultada',
  leaderboard: 'ranking',
  login: 'login',
  logout: 'logout',
  media: 'midia',
  moderation: 'moderacao',
  pinned: 'fixacao',
  play: 'jogos',
  published: 'publicada',
  register: 'cadastro',
  rejected: 'rejeitada',
  reprocess: 'reprocessamento',
  requested: 'solicitado',
  settings: 'configuracoes',
  updated: 'atualizada',
  wall: 'telao',
};

const FIELD_LABELS: Record<string, string> = {
  avatar: 'Foto de perfil',
  batch_uuid: 'Codigo do lote',
  billing_cycle: 'Ciclo de cobranca',
  cancel_at_period_end: 'Cancelar no fim do periodo',
  caption: 'Legenda',
  client_id: 'ID do cliente',
  contact_email: 'E-mail de contato',
  contact_phone: 'Telefone de contato',
  created_at: 'Criado em',
  deleted_at: 'Removido em',
  description: 'Descricao',
  email: 'E-mail',
  enabled: 'Ativado',
  end_at: 'Fim',
  ends_at: 'Fim',
  event_id: 'ID do evento',
  event_type: 'Tipo de evento',
  featured: 'Destaque',
  legal_name: 'Razao social',
  moderation_mode: 'Modo de moderacao',
  name: 'Nome',
  organization_id: 'ID da organizacao',
  original_filename: 'Nome original do arquivo',
  partner_logo: 'Logo do parceiro',
  password: 'Senha',
  phone: 'Telefone',
  pinned: 'Fixado',
  plan_id: 'ID do plano',
  plan_key: 'Plano',
  published_at: 'Publicado em',
  role_key: 'Perfil de acesso',
  slug: 'Endereco',
  start_at: 'Inicio',
  starts_at: 'Inicio',
  status: 'Status',
  theme: 'Tema',
  timezone: 'Fuso horario',
  title: 'Titulo',
  trade_name: 'Nome fantasia',
  type: 'Tipo',
  updated_at: 'Atualizado em',
  url: 'Link',
  user_id: 'ID do usuario',
  visibility: 'Visibilidade',
};

const FIELD_TOKEN_LABELS: Record<string, string> = {
  active: 'ativo',
  at: 'em',
  billing: 'cobranca',
  cancel: 'cancelar',
  client: 'cliente',
  contact: 'contato',
  created: 'criado',
  cycle: 'ciclo',
  deleted: 'removido',
  description: 'descricao',
  email: 'e-mail',
  enabled: 'ativado',
  end: 'fim',
  ends: 'fim',
  event: 'evento',
  featured: 'destaque',
  hero: 'principal',
  id: 'ID',
  image: 'imagem',
  key: 'chave',
  legal: 'razao',
  logo: 'logo',
  mode: 'modo',
  moderation: 'moderacao',
  name: 'nome',
  organization: 'organizacao',
  original: 'original',
  partner: 'parceiro',
  password: 'senha',
  period: 'periodo',
  phone: 'telefone',
  pinned: 'fixado',
  plan: 'plano',
  published: 'publicado',
  role: 'perfil',
  slug: 'endereco',
  start: 'inicio',
  starts: 'inicio',
  status: 'status',
  theme: 'tema',
  timezone: 'fuso horario',
  title: 'titulo',
  trade: 'fantasia',
  type: 'tipo',
  updated: 'atualizado',
  url: 'link',
  user: 'usuario',
  visibility: 'visibilidade',
};

function normalizeCopyKey(value: string) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, ' ');
}

function capitalizeLabel(value: string) {
  if (!value) {
    return value;
  }

  return value.charAt(0).toUpperCase() + value.slice(1);
}

function resolveSubjectLabel(subjectType?: string | null, rawSubjectLabel?: string) {
  const subjectFromDescription = rawSubjectLabel ? SUBJECT_ALIASES[normalizeCopyKey(rawSubjectLabel)] : undefined;
  const subjectFromType = subjectType ? SUBJECT_LABELS[subjectType as AuditSubjectKey] : undefined;

  if (subjectFromDescription) {
    return SUBJECT_LABELS[subjectFromDescription];
  }

  return subjectFromType ?? null;
}

function translatePassiveDescription(description: string, subjectType?: string | null) {
  const match = normalizeCopyKey(description).match(/^(.+?) was ([a-z_]+)$/);

  if (!match) {
    return null;
  }

  const [, rawSubjectLabel, rawAction] = match;
  const subject = resolveSubjectLabel(subjectType, rawSubjectLabel);
  const action = ACTION_LABELS[rawAction];

  if (!subject || !action) {
    return null;
  }

  return `${subject.label} ${action[subject.gender]}`;
}

function applyDescriptionReplacements(description: string) {
  return DESCRIPTION_REPLACEMENTS.reduce((value, item) => value.replace(item.pattern, item.replacement), description)
    .replace(/\s+/g, ' ')
    .trim();
}

function humanizeEventToken(token: string) {
  const normalizedToken = normalizeCopyKey(token);
  const translated = EVENT_TOKEN_LABELS[normalizedToken];

  if (translated) {
    return translated;
  }

  return token.replace(/[_-]+/g, ' ').trim().toLowerCase();
}

function humanizeFieldToken(token: string) {
  const normalizedToken = normalizeCopyKey(token);
  const translated = FIELD_TOKEN_LABELS[normalizedToken];

  if (translated) {
    return translated;
  }

  return token.replace(/[_-]+/g, ' ').trim().toLowerCase();
}

export function formatAuditDescription(description: string, subjectType?: string | null) {
  const exactLabel = EXACT_DESCRIPTION_LABELS[normalizeCopyKey(description)];

  if (exactLabel) {
    return exactLabel;
  }

  const translatedPassiveDescription = translatePassiveDescription(description, subjectType);

  if (translatedPassiveDescription) {
    return translatedPassiveDescription;
  }

  return applyDescriptionReplacements(description);
}

export function formatAuditEventLabel(activityEvent: string | null | undefined) {
  if (!activityEvent) {
    return '';
  }

  const exactLabel = ACTIVITY_EVENT_LABELS[normalizeCopyKey(activityEvent)];

  if (exactLabel) {
    return exactLabel;
  }

  const label = activityEvent
    .split('.')
    .filter(Boolean)
    .map((part) => part
      .split('_')
      .filter(Boolean)
      .map(humanizeEventToken)
      .join(' '))
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim();

  return capitalizeLabel(label);
}

export function formatAuditFieldLabel(field: string) {
  const exactLabel = FIELD_LABELS[normalizeCopyKey(field)];

  if (exactLabel) {
    return exactLabel;
  }

  const label = field
    .split('_')
    .filter(Boolean)
    .map(humanizeFieldToken)
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim();

  return capitalizeLabel(label);
}
