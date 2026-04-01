import { lazy, Suspense } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Route, Routes, Navigate } from 'react-router-dom';
import { Toaster as Sonner } from '@/components/ui/sonner';
import { Toaster } from '@/components/ui/toaster';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ThemeProvider } from '@/app/providers/ThemeProvider';
import { AuthProvider, useAuth } from '@/app/providers/AuthProvider';
import { BrandingProvider } from '@/app/providers/BrandingProvider';
import { AdminLayout } from '@/app/layouts/AdminLayout';
import { queryClient } from '@/lib/query-client';
import { Loader2 } from 'lucide-react';

// ─── Lazy-loaded Pages (code splitting) ────────────────────

const LoginPage = lazy(() => import('@/modules/auth/LoginPage'));
const ProfilePage = lazy(() => import('@/modules/auth/ProfilePage'));
const DashboardPage = lazy(() => import('@/modules/dashboard/DashboardPage'));
const EventsListPage = lazy(() => import('@/modules/events/EventsListPage'));
const CreateEventPage = lazy(() => import('@/modules/events/CreateEventPage'));
const EventDetailPage = lazy(() => import('@/modules/events/EventDetailPage'));
const MediaPage = lazy(() => import('@/modules/media/MediaPage'));
const ModerationPage = lazy(() => import('@/modules/moderation/ModerationPage'));
const GalleryPage = lazy(() => import('@/modules/gallery/GalleryPage'));
const WallPage = lazy(() => import('@/modules/wall/WallPage'));
const PlayPage = lazy(() => import('@/modules/play/PlayPage'));
const HubPage = lazy(() => import('@/modules/hub/HubPage'));
const PartnersPage = lazy(() => import('@/modules/partners/PartnersPage'));
const ClientsPage = lazy(() => import('@/modules/clients/ClientsPage'));
const PlansPage = lazy(() => import('@/modules/plans/PlansPage'));
const AnalyticsPage = lazy(() => import('@/modules/analytics/AnalyticsPage'));
const AuditPage = lazy(() => import('@/modules/audit/AuditPage'));
const SettingsPage = lazy(() => import('@/modules/settings/SettingsPage'));
const NotFound = lazy(() => import('@/pages/NotFound'));

// Public pages (no auth required)
const WallPlayerPage = lazy(() => import('@/modules/wall/player/WallPlayerPage'));

// ─── Loading Fallback ──────────────────────────────────────

function PageLoader() {
  return (
    <div className="flex items-center justify-center min-h-[50vh]">
      <Loader2 className="h-6 w-6 animate-spin text-primary" />
    </div>
  );
}

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

// ─── Route Guards ──────────────────────────────────────────

function ProtectedRoutes() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) return <FullScreenLoader />;
  if (!isAuthenticated) return <Navigate to="/login" replace />;

  return (
    <Suspense fallback={<PageLoader />}>
      <Routes>
        <Route element={<AdminLayout />}>
          <Route index element={<DashboardPage />} />

          {/* Events */}
          <Route path="events" element={<EventsListPage />} />
          <Route path="events/create" element={<CreateEventPage />} />
          <Route path="events/:id" element={<EventDetailPage />} />

          {/* Content */}
          <Route path="media" element={<MediaPage />} />
          <Route path="moderation" element={<ModerationPage />} />
          <Route path="gallery" element={<GalleryPage />} />
          <Route path="wall" element={<WallPage />} />
          <Route path="play" element={<PlayPage />} />
          <Route path="hub" element={<HubPage />} />

          {/* Profile */}
          <Route path="profile" element={<ProfilePage />} />

          {/* Business */}
          <Route path="partners" element={<PartnersPage />} />
          <Route path="clients" element={<ClientsPage />} />
          <Route path="plans" element={<PlansPage />} />
          <Route path="analytics" element={<AnalyticsPage />} />
          <Route path="audit" element={<AuditPage />} />
          <Route path="settings" element={<SettingsPage />} />

          {/* 404 */}
          <Route path="*" element={<NotFound />} />
        </Route>
      </Routes>
    </Suspense>
  );
}

function PublicRoutes() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) return <FullScreenLoader />;

  return (
    <Suspense fallback={<FullScreenLoader />}>
      <Routes>
        <Route
          path="/login"
          element={isAuthenticated ? <Navigate to="/" replace /> : <LoginPage />}
        />

        {/* Public wall player — no auth, accessed by TV/projector screens */}
        <Route path="/wall/player/:code" element={<WallPlayerPage />} />

        <Route path="/*" element={<ProtectedRoutes />} />
      </Routes>
    </Suspense>
  );
}

// ─── App ───────────────────────────────────────────────────

const App = () => (
  <QueryClientProvider client={queryClient}>
    <ThemeProvider>
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <AuthProvider>
          <BrandingProvider>
            <BrowserRouter>
              <PublicRoutes />
            </BrowserRouter>
          </BrandingProvider>
        </AuthProvider>
      </TooltipProvider>
    </ThemeProvider>
  </QueryClientProvider>
);

export default App;
