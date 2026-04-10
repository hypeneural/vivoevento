type RouteLoader = () => Promise<unknown>;

export const routeImports = {
  login: () => import('@/modules/auth/LoginPage'),
  myEvents: () => import('@/modules/auth/MyEventsPage'),
  eventWorkspaceLayout: () => import('@/modules/auth/EventWorkspaceLayout'),
  eventWorkspaceHome: () => import('@/modules/auth/EventWorkspaceHomePage'),
  eventWorkspaceModule: () => import('@/modules/auth/EventWorkspaceModulePage'),
  aiMediaReplies: () => import('@/modules/ai/MediaAutomaticRepliesPage'),
  profile: () => import('@/modules/auth/ProfilePage'),
  dashboard: () => import('@/modules/dashboard/DashboardPage'),
  eventsList: () => import('@/modules/events/EventsListPage'),
  eventCreate: () => import('@/modules/events/CreateEventPage'),
  eventDetail: () => import('@/modules/events/EventDetailPage'),
  eventEdit: () => import('@/modules/events/EditEventPage'),
  media: () => import('@/modules/media/MediaPage'),
  moderation: () => import('@/modules/moderation/ModerationPage'),
  gallery: () => import('@/modules/gallery/GalleryPage'),
  publicGallery: () => import('@/modules/gallery/PublicGalleryPage'),
  wall: () => import('@/modules/wall/WallPage'),
  play: () => import('@/modules/play/PlayPage'),
  publicPlayHub: () => import('@/modules/play/pages/PublicPlayHubPage'),
  publicGame: () => import('@/modules/play/pages/PublicGamePage'),
  hub: () => import('@/modules/hub/HubPage'),
  publicHub: () => import('@/modules/hub/PublicHubPage'),
  publicFaceSearch: () => import('@/modules/face-search/PublicFaceSearchPage'),
  publicEventCheckout: () => import('@/modules/billing/PublicEventCheckoutEntryPage'),
  whatsappInstances: () => import('@/modules/whatsapp/WhatsAppInstancesPage'),
  whatsappInstanceDetail: () => import('@/modules/whatsapp/WhatsAppInstanceDetailPage'),
  partners: () => import('@/modules/partners/PartnersPage'),
  clients: () => import('@/modules/clients/ClientsPage'),
  plans: () => import('@/modules/plans/PlansPage'),
  analytics: () => import('@/modules/analytics/AnalyticsPage'),
  audit: () => import('@/modules/audit/AuditPage'),
  settings: () => import('@/modules/settings/SettingsPage'),
  notFound: () => import('@/pages/NotFound'),
  wallPlayer: () => import('@/modules/wall/player/WallPlayerPage'),
  publicEventUpload: () => import('@/modules/upload/PublicEventUploadPage'),
} as const satisfies Record<string, RouteLoader>;

const adminPreloadMatchers: Array<{ pattern: RegExp; loaders: RouteLoader[] }> = [
  {
    pattern: /^\/$/,
    loaders: [routeImports.dashboard],
  },
  {
    pattern: /^\/events(?:\/|$)/,
    loaders: [
      routeImports.eventsList,
      routeImports.eventCreate,
      routeImports.eventDetail,
      routeImports.eventEdit,
    ],
  },
  {
    pattern: /^\/ia(?:\/|$)/,
    loaders: [routeImports.aiMediaReplies],
  },
  {
    pattern: /^\/(?:settings\/whatsapp|whatsapp)(?:\/|$)/,
    loaders: [routeImports.whatsappInstances, routeImports.whatsappInstanceDetail],
  },
  {
    pattern: /^\/dashboard(?:\/|$)/,
    loaders: [routeImports.dashboard],
  },
  {
    pattern: /^\/my-events(?:\/|$)/,
    loaders: [
      routeImports.myEvents,
      routeImports.eventWorkspaceLayout,
      routeImports.eventWorkspaceHome,
      routeImports.eventWorkspaceModule,
    ],
  },
];

export function preloadRouteForPath(path: string) {
  const normalizedPath = path.split(/[?#]/, 1)[0] ?? path;
  const match = adminPreloadMatchers.find(({ pattern }) => pattern.test(normalizedPath));

  if (!match) {
    return;
  }

  match.loaders.forEach((loader) => {
    void loader();
  });
}

export function preloadCommonAdminRoutes() {
  return Promise.allSettled([
    routeImports.dashboard(),
    routeImports.eventsList(),
    routeImports.eventCreate(),
    routeImports.myEvents(),
  ]);
}
