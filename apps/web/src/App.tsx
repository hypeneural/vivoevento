import { lazy, Suspense } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import {
  createBrowserRouter,
  createRoutesFromElements,
  Navigate,
  Outlet,
  Route,
  RouterProvider,
  ScrollRestoration,
  useLocation,
  useParams,
} from 'react-router-dom';
import { Toaster as Sonner } from '@/components/ui/sonner';
import { Toaster } from '@/components/ui/toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ThemeProvider } from '@/app/providers/ThemeProvider';
import { AuthProvider, useAuth } from '@/app/providers/AuthProvider';
import { BrandingProvider } from '@/app/providers/BrandingProvider';
import { ModuleGuard } from '@/app/guards/ModuleGuard';
import { AdminLayout } from '@/app/layouts/AdminLayout';
import { AdminWarmup } from '@/app/routing/AdminWarmup';
import { buildScrollRestorationKey } from '@/app/routing/scroll-restoration';
import { routeImports } from '@/app/routing/route-preload';
import { queryClient } from '@/lib/query-client';
import { buildWhatsAppInstancePath, WHATSAPP_SETTINGS_PATH } from '@/modules/whatsapp/paths';
import { resolveLoginReturnPath } from '@/modules/auth/login-navigation';
import { AppErrorBoundary } from '@/shared/components/AppErrorBoundary';
import { Loader2 } from 'lucide-react';

const LoginPage = lazy(routeImports.login);
const AiMediaRepliesPage = lazy(routeImports.aiMediaReplies);
const ProfilePage = lazy(routeImports.profile);
const DashboardPage = lazy(routeImports.dashboard);
const EventsListPage = lazy(routeImports.eventsList);
const CreateEventPage = lazy(routeImports.eventCreate);
const EventDetailPage = lazy(routeImports.eventDetail);
const EditEventPage = lazy(routeImports.eventEdit);
const MediaPage = lazy(routeImports.media);
const ModerationPage = lazy(routeImports.moderation);
const GalleryPage = lazy(routeImports.gallery);
const PublicGalleryPage = lazy(routeImports.publicGallery);
const WallPage = lazy(routeImports.wall);
const PlayPage = lazy(routeImports.play);
const PublicPlayHubPage = lazy(routeImports.publicPlayHub);
const PublicGamePage = lazy(routeImports.publicGame);
const HubPage = lazy(routeImports.hub);
const PublicHubPage = lazy(routeImports.publicHub);
const PublicFaceSearchPage = lazy(routeImports.publicFaceSearch);
const PublicEventCheckoutEntryPage = lazy(routeImports.publicEventCheckout);
const WhatsAppInstancesPage = lazy(routeImports.whatsappInstances);
const WhatsAppInstanceDetailPage = lazy(routeImports.whatsappInstanceDetail);
const PartnersPage = lazy(routeImports.partners);
const ClientsPage = lazy(routeImports.clients);
const PlansPage = lazy(routeImports.plans);
const AnalyticsPage = lazy(routeImports.analytics);
const AuditPage = lazy(routeImports.audit);
const SettingsPage = lazy(routeImports.settings);
const NotFound = lazy(routeImports.notFound);
const WallPlayerPage = lazy(routeImports.wallPlayer);
const PublicEventUploadPage = lazy(routeImports.publicEventUpload);

function FullScreenLoader() {
  return (
    <div className="flex items-center justify-center min-h-[100dvh] bg-background">
      <div className="flex flex-col items-center gap-3">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
        <p className="text-sm text-muted-foreground">Carregando...</p>
      </div>
    </div>
  );
}

function LegacyWhatsAppDetailRedirect() {
  const { id } = useParams<{ id: string }>();

  if (!id) {
    return <Navigate to={WHATSAPP_SETTINGS_PATH} replace />;
  }

  return <Navigate to={buildWhatsAppInstancePath(id)} replace />;
}

function LoginRoute() {
  const { isAuthenticated, isLoading } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return <FullScreenLoader />;
  }

  return isAuthenticated
    ? <Navigate to={resolveLoginReturnPath(location.search, '/')} replace />
    : <LoginPage />;
}

function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <FullScreenLoader />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return (
    <>
      <AdminWarmup />
      <Outlet />
    </>
  );
}

function RouterRoot() {
  const location = useLocation();

  return (
    <AppErrorBoundary resetKey={location.pathname}>
      <ScrollRestoration getKey={buildScrollRestorationKey} />
      <Suspense fallback={<FullScreenLoader />}>
        <Outlet />
      </Suspense>
    </AppErrorBoundary>
  );
}

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route element={<RouterRoot />}>
      <Route path="/login" element={<LoginRoute />} />
      <Route path="/wall/player/:code" element={<WallPlayerPage />} />
      <Route path="/upload/:code" element={<PublicEventUploadPage />} />
      <Route path="/checkout/evento" element={<PublicEventCheckoutEntryPage />} />
      <Route path="/e/:slug" element={<PublicHubPage />} />
      <Route path="/e/:slug/gallery" element={<PublicGalleryPage />} />
      <Route path="/e/:slug/find-me" element={<PublicFaceSearchPage />} />
      <Route path="/e/:slug/play" element={<PublicPlayHubPage />} />
      <Route path="/e/:slug/play/:gameSlug" element={<PublicGamePage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<AdminLayout />}>
          <Route index element={<DashboardPage />} />

          <Route path="events" element={<EventsListPage />} />
          <Route path="events/create" element={<CreateEventPage />} />
          <Route path="events/:id" element={<EventDetailPage />} />
          <Route path="events/:id/wall" element={<WallPage />} />
          <Route path="events/:id/play" element={<PlayPage />} />
          <Route path="events/:id/edit" element={<EditEventPage />} />

          <Route path="media" element={<MediaPage />} />
          <Route path="moderation" element={<ModerationPage />} />
          <Route path="gallery" element={<GalleryPage />} />
          <Route path="wall" element={<WallPage />} />
          <Route path="play" element={<PlayPage />} />
          <Route path="hub" element={<HubPage />} />
          <Route path="whatsapp" element={<Navigate to={WHATSAPP_SETTINGS_PATH} replace />} />
          <Route path="whatsapp/:id" element={<LegacyWhatsAppDetailRedirect />} />

          <Route path="profile" element={<ProfilePage />} />

          <Route
            path="ia/moderacao-de-midia"
            element={(
              <ModuleGuard moduleKey="settings" requiredPermissions={['settings.manage']}>
                <AiMediaRepliesPage />
              </ModuleGuard>
            )}
          />
          <Route path="ia/respostas-de-midia" element={<Navigate to="/ia/moderacao-de-midia" replace />} />
          <Route
            path="partners"
            element={(
              <ModuleGuard moduleKey="partners" requiredPermissions={['partners.manage.any', 'partners.view.any']}>
                <PartnersPage />
              </ModuleGuard>
            )}
          />
          <Route
            path="clients"
            element={(
              <ModuleGuard moduleKey="clients" requiredPermissions={['clients.view']}>
                <ClientsPage />
              </ModuleGuard>
            )}
          />
          <Route
            path="plans"
            element={(
              <ModuleGuard
                moduleKey="plans"
                requiredPermissions={['billing.view', 'billing.manage', 'billing.purchase', 'billing.manage_subscription', 'plans.view']}
              >
                <PlansPage />
              </ModuleGuard>
            )}
          />
          <Route
            path="analytics"
            element={(
              <ModuleGuard moduleKey="analytics" requiredPermissions={['analytics.view']}>
                <AnalyticsPage />
              </ModuleGuard>
            )}
          />
          <Route
            path="audit"
            element={(
              <ModuleGuard moduleKey="audit" requiredPermissions={['audit.view']}>
                <AuditPage />
              </ModuleGuard>
            )}
          />
          <Route
            path="settings"
            element={(
              <ModuleGuard moduleKey="settings" requiredPermissions={['settings.manage']}>
                <SettingsPage />
              </ModuleGuard>
            )}
          />
          <Route
            path="settings/whatsapp"
            element={(
              <ModuleGuard moduleKey="whatsapp" requiredPermissions={['channels.manage', 'channels.view']}>
                <WhatsAppInstancesPage />
              </ModuleGuard>
            )}
          />
          <Route
            path="settings/whatsapp/:id"
            element={(
              <ModuleGuard moduleKey="whatsapp" requiredPermissions={['channels.manage', 'channels.view']}>
                <WhatsAppInstanceDetailPage />
              </ModuleGuard>
            )}
          />

          <Route path="*" element={<NotFound />} />
        </Route>
      </Route>
    </Route>,
  ),
  {
    future: {
      v7_startTransition: true,
      v7_relativeSplatPath: true,
    },
  },
);

const App = () => (
  <QueryClientProvider client={queryClient}>
    <ThemeProvider>
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <AuthProvider>
          <BrandingProvider>
            <RouterProvider router={router} fallbackElement={<FullScreenLoader />} />
          </BrandingProvider>
        </AuthProvider>
      </TooltipProvider>
    </ThemeProvider>
  </QueryClientProvider>
);

export default App;
