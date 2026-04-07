import type {
  User, Organization, EventItem, MediaItem, Partner, Client,
  PlanItem, AuditEntry, Notification, DashboardStats, UserSession,
} from '../types';

// ─── Default Branding ──────────────────────────────────────

const DEFAULT_BRANDING = {
  primaryColor: '#7c3aed',
  secondaryColor: '#3b82f6',
  mode: 'default' as const,
};

// ─── Organizations ─────────────────────────────────────────

export const mockOrganizations: Organization[] = [
  {
    id: 'org-1',
    name: 'Studio Lumière',
    slug: 'studio-lumiere',
    type: 'partner',
    plan: 'Pro Parceiro',
    logoEmoji: '📸',
    branding: { ...DEFAULT_BRANDING },
    enabledModules: ['dashboard', 'events', 'media', 'moderation', 'gallery', 'wall', 'play', 'hub', 'whatsapp', 'analytics', 'settings'],
    status: 'active',
  },
  {
    id: 'org-2',
    name: 'Agência Celebra',
    slug: 'agencia-celebra',
    type: 'agency',
    plan: 'Enterprise',
    logoEmoji: '🎉',
    branding: {
      primaryColor: '#0ea5e9',
      secondaryColor: '#8b5cf6',
      accentColor: '#f59e0b',
      mode: 'co-branding',
    },
    enabledModules: ['dashboard', 'events', 'media', 'moderation', 'gallery', 'wall', 'hub', 'whatsapp', 'partners', 'clients', 'plans', 'analytics', 'audit', 'settings'],
    status: 'active',
  },
  {
    id: 'org-3',
    name: 'Eventos Prime',
    slug: 'eventos-prime',
    type: 'host',
    plan: 'Evento Play',
    logoEmoji: '✨',
    branding: {
      primaryColor: '#10b981',
      secondaryColor: '#06b6d4',
      mode: 'white-label',
    },
    enabledModules: ['dashboard', 'events', 'media', 'moderation', 'gallery', 'play'],
    status: 'active',
  },
];

// ─── Users ─────────────────────────────────────────────────

export const mockUsers: User[] = [
  { id: 'u-1', name: 'Rafael Mendes', email: 'rafael@eventovivo.com', phone: '51999990001', role: 'super_admin', organizationId: 'org-1' },
  { id: 'u-2', name: 'Carolina Silva', email: 'carolina@studiolumiere.com', phone: '51999990002', role: 'partner_owner', organizationId: 'org-1' },
  { id: 'u-3', name: 'Bruno Costa', email: 'bruno@celebra.com', phone: '51999990003', role: 'partner_manager', organizationId: 'org-2' },
  { id: 'u-4', name: 'Juliana Oliveira', email: 'juliana@prime.com', phone: '51999990004', role: 'event_operator', organizationId: 'org-3' },
  { id: 'u-5', name: 'Lucas Ferreira', email: 'lucas@eventovivo.com', phone: '51999990005', role: 'viewer', organizationId: 'org-1' },
  { id: 'u-6', name: 'Mariana Alves', email: 'mariana@eventovivo.com', phone: '51999990006', role: 'platform_admin', organizationId: 'org-1' },
  { id: 'u-7', name: 'Pedro Santos', email: 'pedro@celebra.com', phone: '51999990007', role: 'financial', organizationId: 'org-2' },
];

// ─── Sessions (used by AuthProvider) ───────────────────────

export function buildMockSession(user: User): UserSession {
  const org = mockOrganizations.find(o => o.id === user.organizationId);
  const allPerms = [
    'dashboard.view', 'events.view', 'events.create', 'events.update',
    'channels.view', 'channels.manage', 'media.view', 'media.moderate', 'gallery.manage', 'wall.manage',
    'play.manage', 'hub.manage', 'partners.view.any', 'partners.manage.any',
    'clients.view', 'clients.manage', 'plans.view', 'billing.manage',
    'analytics.view', 'audit.view', 'settings.manage', 'branding.manage',
    'integrations.manage', 'team.manage',
  ];

  const rolePermMap: Record<string, string[]> = {
    super_admin: allPerms,
    platform_admin: allPerms.filter(p => !p.startsWith('billing')),
    partner_owner: allPerms.filter(p => !['audit.view', 'partners.manage.any'].includes(p)),
    partner_manager: ['dashboard.view', 'events.view', 'events.create', 'events.update', 'channels.view', 'channels.manage', 'media.view', 'media.moderate', 'gallery.manage', 'wall.manage', 'play.manage', 'hub.manage', 'analytics.view', 'settings.manage', 'team.manage'],
    event_operator: ['dashboard.view', 'events.view', 'channels.view', 'media.view', 'media.moderate', 'gallery.manage', 'wall.manage', 'play.manage'],
    financial: ['dashboard.view', 'plans.view', 'billing.manage', 'analytics.view'],
    partner: ['dashboard.view', 'events.view', 'media.view', 'gallery.manage', 'analytics.view'],
    viewer: ['dashboard.view', 'events.view', 'channels.view', 'gallery.manage'],
  };

  return {
    id: user.id,
    name: user.name,
    email: user.email,
    phone: user.phone,
    avatarUrl: user.avatarUrl,
    role: user.role,
    organizationId: user.organizationId,
    organizationName: org?.name ?? 'Desconhecida',
    permissions: rolePermMap[user.role] ?? [],
    enabledModules: org?.enabledModules ?? [],
    themePreference: 'light',
  };
}

// ─── Events ────────────────────────────────────────────────

const coverUrls = [
  'https://images.unsplash.com/photo-1519741497674-611481863552?w=600&h=300&fit=crop',
  'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&h=300&fit=crop',
  'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=600&h=300&fit=crop',
  'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&h=300&fit=crop',
  'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=600&h=300&fit=crop',
  'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=600&h=300&fit=crop',
];

const thumbUrls = [
  'https://images.unsplash.com/photo-1519741497674-611481863552?w=200&h=200&fit=crop',
  'https://images.unsplash.com/photo-1529634806980-85c3dd6d34ac?w=200&h=200&fit=crop',
  'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=200&h=200&fit=crop',
  'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=200&h=200&fit=crop',
  'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=200&h=200&fit=crop',
  'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?w=200&h=200&fit=crop',
  'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=200&h=200&fit=crop',
  'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=200&h=200&fit=crop',
];

export const mockEvents: EventItem[] = [
  { id: 'evt-1', name: 'Casamento Ana & Pedro', type: 'wedding', date: '2026-04-15', location: 'Espaço Villa Real, SP', organizationId: 'org-1', organizationName: 'Studio Lumière', status: 'active', photosReceived: 847, photosApproved: 723, modulesActive: ['Live', 'Wall', 'Play', 'Hub'], plan: 'Pro Parceiro', coverUrl: coverUrls[0], slug: 'casamento-ana-pedro', description: 'Casamento elegante ao ar livre', responsible: 'Carolina Silva' },
  { id: 'evt-2', name: 'Convenção TechForward 2026', type: 'corporate', date: '2026-04-20', location: 'Centro de Convenções, RJ', organizationId: 'org-2', organizationName: 'Agência Celebra', status: 'active', photosReceived: 1234, photosApproved: 1100, modulesActive: ['Live', 'Wall', 'Hub'], plan: 'Enterprise', coverUrl: coverUrls[3], slug: 'techforward-2026', description: 'Convenção anual de tecnologia', responsible: 'Bruno Costa' },
  { id: 'evt-3', name: 'Aniversário 15 anos Maria', type: 'birthday', date: '2026-04-10', location: 'Buffet Encanto, BH', organizationId: 'org-1', organizationName: 'Studio Lumière', status: 'finished', photosReceived: 432, photosApproved: 398, modulesActive: ['Live', 'Play'], plan: 'Evento Play', coverUrl: coverUrls[5], slug: 'aniversario-maria-15', description: 'Festa de 15 anos temática', responsible: 'Carolina Silva' },
  { id: 'evt-4', name: 'Festival Sunset Beats', type: 'festival', date: '2026-05-01', location: 'Praia de Maresias, SP', organizationId: 'org-2', organizationName: 'Agência Celebra', status: 'draft', photosReceived: 0, photosApproved: 0, modulesActive: ['Live', 'Wall', 'Play', 'Hub'], plan: 'Enterprise', coverUrl: coverUrls[1], slug: 'sunset-beats', description: 'Festival de música eletrônica na praia', responsible: 'Bruno Costa' },
  { id: 'evt-5', name: 'Lançamento Produto XYZ', type: 'corporate', date: '2026-04-25', location: 'Hotel Premium, SP', organizationId: 'org-3', organizationName: 'Eventos Prime', status: 'active', photosReceived: 156, photosApproved: 140, modulesActive: ['Live', 'Hub'], plan: 'Evento Play', coverUrl: coverUrls[2], slug: 'lancamento-xyz', description: 'Evento de lançamento corporativo', responsible: 'Juliana Oliveira' },
  { id: 'evt-6', name: 'Gala Beneficente 2026', type: 'party', date: '2026-03-28', location: 'Palácio dos Eventos, RJ', organizationId: 'org-1', organizationName: 'Studio Lumière', status: 'finished', photosReceived: 567, photosApproved: 510, modulesActive: ['Live', 'Wall', 'Hub'], plan: 'Pro Parceiro', coverUrl: coverUrls[4], slug: 'gala-beneficente', description: 'Gala anual beneficente', responsible: 'Rafael Mendes' },
  { id: 'evt-7', name: 'Workshop de Fotografia', type: 'conference', date: '2026-05-10', location: 'Espaço Criativo, SP', organizationId: 'org-3', organizationName: 'Eventos Prime', status: 'draft', photosReceived: 0, photosApproved: 0, modulesActive: ['Live'], plan: 'Teste Grátis', coverUrl: coverUrls[3], slug: 'workshop-foto', description: 'Workshop prático de fotografia de eventos', responsible: 'Juliana Oliveira' },
  { id: 'evt-8', name: 'Casamento Julia & Marcos', type: 'wedding', date: '2026-04-05', location: 'Fazenda Santa Clara, MG', organizationId: 'org-2', organizationName: 'Agência Celebra', status: 'paused', photosReceived: 289, photosApproved: 250, modulesActive: ['Live', 'Wall'], plan: 'Pro Parceiro', coverUrl: coverUrls[0], slug: 'casamento-julia-marcos', description: 'Casamento rústico na fazenda', responsible: 'Bruno Costa' },
];

// ─── Media ─────────────────────────────────────────────────

const senders = ['Maria S.', 'João P.', 'Ana L.', 'Carlos R.', 'Fernanda M.', 'Pedro H.', 'Luciana B.', 'Thiago N.'];
const channels: Array<MediaItem['channel']> = ['qrcode', 'link', 'whatsapp', 'upload', 'telegram'];
const statuses: Array<MediaItem['status']> = ['received', 'processing', 'pending_moderation', 'approved', 'rejected', 'published', 'error'];

export const mockMedia: MediaItem[] = Array.from({ length: 40 }, (_, i) => ({
  id: `media-${i + 1}`,
  eventId: mockEvents[i % mockEvents.length].id,
  eventName: mockEvents[i % mockEvents.length].name,
  thumbnailUrl: thumbUrls[i % thumbUrls.length],
  senderName: senders[i % senders.length],
  channel: channels[i % channels.length],
  status: statuses[i % statuses.length],
  createdAt: new Date(Date.now() - i * 3600000 * Math.random() * 5).toISOString(),
  fileType: i % 7 === 0 ? 'video' : 'image',
}));

// ─── Partners ──────────────────────────────────────────────

export const mockPartners: Partner[] = [
  { id: 'p-1', name: 'Studio Lumière', type: 'Fotógrafo', plan: 'Pro Parceiro', activeEvents: 4, revenue: 12500, status: 'active', teamSize: 5, logo: '📸' },
  { id: 'p-2', name: 'Agência Celebra', type: 'Agência', plan: 'Enterprise', activeEvents: 8, revenue: 45000, status: 'active', teamSize: 12, logo: '🎉' },
  { id: 'p-3', name: 'Eventos Prime', type: 'Cerimonialista', plan: 'Evento Play', activeEvents: 2, revenue: 3200, status: 'active', teamSize: 3, logo: '✨' },
  { id: 'p-4', name: 'Foto & Arte', type: 'Fotógrafo', plan: 'Pro Parceiro', activeEvents: 0, revenue: 8700, status: 'inactive', teamSize: 2, logo: '🎨' },
  { id: 'p-5', name: 'Click Moment', type: 'Fotógrafo', plan: 'Teste Grátis', activeEvents: 1, revenue: 0, status: 'trial', teamSize: 1, logo: '📷' },
];

// ─── Clients ───────────────────────────────────────────────

export const mockClients: Client[] = [
  { id: 'c-1', name: 'Ana & Pedro', type: 'Noivos', partnerId: 'p-1', partnerName: 'Studio Lumière', eventsCount: 1, plan: 'Pro Parceiro', status: 'active' },
  { id: 'c-2', name: 'TechForward Inc.', type: 'Empresa', partnerId: 'p-2', partnerName: 'Agência Celebra', eventsCount: 3, plan: 'Enterprise', status: 'active' },
  { id: 'c-3', name: 'Família Oliveira', type: 'Pessoa Física', partnerId: 'p-3', partnerName: 'Eventos Prime', eventsCount: 1, plan: 'Evento Play', status: 'active' },
  { id: 'c-4', name: 'Julia & Marcos', type: 'Noivos', partnerId: 'p-2', partnerName: 'Agência Celebra', eventsCount: 1, plan: 'Pro Parceiro', status: 'active' },
  { id: 'c-5', name: 'Corp Solutions', type: 'Empresa', partnerId: 'p-2', partnerName: 'Agência Celebra', eventsCount: 2, plan: 'Enterprise', status: 'inactive' },
];

// ─── Plans ─────────────────────────────────────────────────

export const mockPlans: PlanItem[] = [
  { id: 'plan-1', name: 'Teste Grátis', price: 0, cycle: 'per_event', features: ['1 evento', 'Até 100 fotos', 'Galeria básica', 'QR Code'], limits: { events: 1, photos: 100, storage: '500MB' }, modules: ['Live'] },
  { id: 'plan-2', name: 'Evento Play', price: 97, cycle: 'per_event', features: ['1 evento', 'Até 1.000 fotos', 'Galeria + Wall', 'Minigames', 'QR Code + Link'], limits: { events: 1, photos: 1000, storage: '5GB' }, modules: ['Live', 'Wall', 'Play'] },
  { id: 'plan-3', name: 'Pro Parceiro', price: 297, cycle: 'monthly', features: ['Até 10 eventos/mês', 'Fotos ilimitadas', 'Todos os módulos', 'White-label', 'Equipe até 5', 'Suporte prioritário'], limits: { events: 10, photos: 'Ilimitado', storage: '50GB' }, modules: ['Live', 'Wall', 'Play', 'Hub'], popular: true },
  { id: 'plan-4', name: 'Enterprise', price: 897, cycle: 'monthly', features: ['Eventos ilimitados', 'Fotos ilimitadas', 'Todos os módulos', 'White-label completo', 'Equipe ilimitada', 'API access', 'Suporte dedicado', 'SLA garantido'], limits: { events: 'Ilimitado', photos: 'Ilimitado', storage: '500GB' }, modules: ['Live', 'Wall', 'Play', 'Hub'] },
];

// ─── Audit ─────────────────────────────────────────────────

const auditActions = [
  { action: 'Aprovou foto', entityType: 'Mídia', entityName: 'IMG_4521.jpg' },
  { action: 'Criou evento', entityType: 'Evento', entityName: 'Casamento Ana & Pedro' },
  { action: 'Editou branding', entityType: 'Organização', entityName: 'Studio Lumière' },
  { action: 'Mudou plano', entityType: 'Assinatura', entityName: 'Pro Parceiro → Enterprise' },
  { action: 'Ativou wall', entityType: 'Wall', entityName: 'Convenção TechForward' },
  { action: 'Desativou jogo', entityType: 'Play', entityName: 'Puzzle - Aniversário Maria' },
  { action: 'Rejeitou foto', entityType: 'Mídia', entityName: 'IMG_2233.jpg' },
  { action: 'Adicionou membro', entityType: 'Equipe', entityName: 'Lucas Ferreira' },
  { action: 'Publicou hub', entityType: 'Hub', entityName: 'Festival Sunset Beats' },
  { action: 'Encerrou evento', entityType: 'Evento', entityName: 'Gala Beneficente 2026' },
];

export const mockAudit: AuditEntry[] = Array.from({ length: 30 }, (_, i) => {
  const a = auditActions[i % auditActions.length];
  const user = mockUsers[i % mockUsers.length];
  return {
    id: `audit-${i + 1}`,
    userId: user.id,
    userName: user.name,
    action: a.action,
    entityType: a.entityType,
    entityName: a.entityName,
    eventName: mockEvents[i % mockEvents.length].name,
    createdAt: new Date(Date.now() - i * 1800000).toISOString(),
  };
});

// ─── Notifications ─────────────────────────────────────────

export const mockNotifications: Notification[] = [
  { id: 'n-1', title: 'Novas fotos recebidas', message: '32 fotos aguardando moderação no evento Casamento Ana & Pedro', type: 'info', read: false, createdAt: new Date().toISOString() },
  { id: 'n-2', title: 'Wall ativo', message: 'O wall do evento Convenção TechForward está transmitindo ao vivo', type: 'success', read: false, createdAt: new Date(Date.now() - 3600000).toISOString() },
  { id: 'n-3', title: 'Limite de fotos próximo', message: 'O evento Lançamento XYZ atingiu 90% do limite de fotos', type: 'warning', read: true, createdAt: new Date(Date.now() - 7200000).toISOString() },
  { id: 'n-4', title: 'Erro no processamento', message: '3 fotos falharam no processamento do evento Festival Sunset', type: 'error', read: true, createdAt: new Date(Date.now() - 14400000).toISOString() },
];

// ─── Dashboard Stats ───────────────────────────────────────

export const mockDashboardStats: DashboardStats = {
  activeEvents: 3,
  photosToday: 423,
  photosApproved: 387,
  moderationRate: 91.5,
  gamesPlayed: 156,
  hubAccesses: 2340,
  estimatedRevenue: 18750,
  activePartners: 4,
};

export const mockUploadsPerHour = Array.from({ length: 24 }, (_, i) => ({
  hour: `${i.toString().padStart(2, '0')}:00`,
  uploads: Math.floor(Math.random() * 80 + 5),
}));

export const mockEventsByType = [
  { type: 'Casamento', count: 45, fill: 'hsl(258, 65%, 52%)' },
  { type: 'Corporativo', count: 32, fill: 'hsl(215, 75%, 50%)' },
  { type: 'Aniversário', count: 28, fill: 'hsl(152, 60%, 38%)' },
  { type: 'Festival', count: 15, fill: 'hsl(38, 92%, 50%)' },
  { type: 'Outro', count: 12, fill: 'hsl(0, 72%, 51%)' },
];

export const mockEngagement = [
  { module: 'Live', value: 89 },
  { module: 'Wall', value: 72 },
  { module: 'Play', value: 56 },
  { module: 'Hub', value: 94 },
];

export const mockAnalyticsData = Array.from({ length: 30 }, (_, i) => ({
  date: new Date(Date.now() - (29 - i) * 86400000).toISOString().split('T')[0],
  uploads: Math.floor(Math.random() * 200 + 50),
  hubVisits: Math.floor(Math.random() * 500 + 100),
  galleryViews: Math.floor(Math.random() * 300 + 80),
  gamesPlayed: Math.floor(Math.random() * 100 + 10),
}));
