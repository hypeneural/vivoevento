import { Link, useLocation, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  LayoutDashboard, CalendarDays, Image, ShieldCheck, GalleryHorizontalEnd,
  Monitor, Gamepad2, Globe, Users, UserCheck, CreditCard, BarChart3,
  ClipboardList, Settings, ChevronLeft, Sparkles, LogOut,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useAuth } from '@/app/providers/AuthProvider';
import { UserAvatar } from '@/shared/components/UserAvatar';

// ─── Navigation Config (single source of truth) ────────────

interface NavItem {
  key: string;
  label: string;
  icon: LucideIcon;
  path: string;
  permission?: string;
  module?: string;
}

interface NavGroup {
  label: string;
  section: string;
  items: NavItem[];
}

const NAV_CONFIG: NavGroup[] = [
  {
    label: 'Principal',
    section: 'main',
    items: [
      { key: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, path: '/', permission: 'dashboard.view', module: 'dashboard' },
      { key: 'events', label: 'Eventos', icon: CalendarDays, path: '/events', permission: 'events.view', module: 'events' },
    ],
  },
  {
    label: 'Conteúdo',
    section: 'content',
    items: [
      { key: 'media', label: 'Mídias', icon: Image, path: '/media', permission: 'media.view', module: 'media' },
      { key: 'moderation', label: 'Moderação', icon: ShieldCheck, path: '/moderation', permission: 'media.moderate', module: 'moderation' },
      { key: 'gallery', label: 'Galeria', icon: GalleryHorizontalEnd, path: '/gallery', permission: 'gallery.manage', module: 'gallery' },
      { key: 'wall', label: 'Wall', icon: Monitor, path: '/wall', permission: 'wall.manage', module: 'wall' },
      { key: 'play', label: 'Play', icon: Gamepad2, path: '/play', permission: 'play.manage', module: 'play' },
      { key: 'hub', label: 'Hub', icon: Globe, path: '/hub', permission: 'hub.manage', module: 'hub' },
    ],
  },
  {
    label: 'Gestão',
    section: 'management',
    items: [
      { key: 'partners', label: 'Parceiros', icon: Users, path: '/partners', permission: 'partners.view', module: 'partners' },
      { key: 'clients', label: 'Clientes', icon: UserCheck, path: '/clients', permission: 'clients.view', module: 'clients' },
      { key: 'plans', label: 'Planos', icon: CreditCard, path: '/plans', permission: 'plans.view', module: 'plans' },
      { key: 'analytics', label: 'Analytics', icon: BarChart3, path: '/analytics', permission: 'analytics.view', module: 'analytics' },
      { key: 'audit', label: 'Auditoria', icon: ClipboardList, path: '/audit', permission: 'audit.view', module: 'audit' },
      { key: 'settings', label: 'Config', icon: Settings, path: '/settings', permission: 'settings.manage', module: 'settings' },
    ],
  },
];

// ─── Component ─────────────────────────────────────────────

interface AppSidebarProps {
  collapsed: boolean;
  onToggle: () => void;
  onNavClick?: () => void;
}

export function AppSidebar({ collapsed, onToggle, onNavClick }: AppSidebarProps) {
  const location = useLocation();
  const { meUser: user, meOrganization: organization, can, canAccessModule, logout } = useAuth();

  // Filter nav items by permission + module access
  const filteredGroups = NAV_CONFIG.map(group => ({
    ...group,
    items: group.items.filter(item => {
      if (item.permission && !can(item.permission)) return false;
      if (item.module && !canAccessModule(item.module)) return false;
      return true;
    }),
  })).filter(group => group.items.length > 0);

  const orgDisplay = '🏢';

  return (
    <aside className={cn(
      'flex h-full flex-col border-r border-sidebar-border bg-sidebar transition-all duration-300',
      collapsed ? 'w-16' : 'w-60'
    )}>
      {/* Logo */}
      <div className="flex h-14 sm:h-16 items-center justify-between px-3 sm:px-4 border-b border-sidebar-border shrink-0">
        {!collapsed && (
          <Link to="/" className="flex items-center gap-2" onClick={onNavClick}>
            <Sparkles className="h-5 w-5 sm:h-6 sm:w-6 text-primary shrink-0" />
            <span className="text-base sm:text-lg font-bold gradient-text truncate">Evento Vivo</span>
          </Link>
        )}
        {collapsed && (
          <Link to="/" className="mx-auto" onClick={onNavClick}>
            <Sparkles className="h-5 w-5 sm:h-6 sm:w-6 text-primary" />
          </Link>
        )}
        <button
          onClick={onToggle}
          className={cn(
            'hidden lg:flex rounded-md p-1 text-sidebar-foreground hover:bg-sidebar-accent transition-colors',
            collapsed && 'mx-auto mt-0'
          )}
          aria-label={collapsed ? 'Expandir sidebar' : 'Recolher sidebar'}
        >
          <ChevronLeft className={cn('h-4 w-4 transition-transform', collapsed && 'rotate-180')} />
        </button>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto scrollbar-thin py-2 sm:py-3 px-2 space-y-0.5">
        {filteredGroups.map((group) => (
          <div key={group.section}>
            {!collapsed && (
              <p className="text-[10px] font-semibold text-sidebar-foreground/50 uppercase tracking-wider px-3 pt-4 pb-1.5">
                {group.label}
              </p>
            )}
            {collapsed && <div className="h-3" />}
            {group.items.map((item) => {
              const isActive = item.path === '/'
                ? location.pathname === '/'
                : location.pathname.startsWith(item.path);
              return (
                <Link
                  key={item.path}
                  to={item.path}
                  onClick={onNavClick}
                  className={cn(
                    'flex items-center gap-3 rounded-lg px-3 py-2 sm:py-2.5 text-sm font-medium transition-all duration-150 relative',
                    collapsed && 'justify-center px-2',
                    isActive
                      ? 'text-primary bg-primary/10'
                      : 'text-sidebar-foreground hover:text-foreground hover:bg-sidebar-accent'
                  )}
                  title={collapsed ? item.label : undefined}
                >
                  {isActive && (
                    <motion.div
                      layoutId="sidebar-indicator"
                      className="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-4 sm:h-5 rounded-r-full bg-primary"
                      transition={{ type: 'spring', bounce: 0.2, duration: 0.4 }}
                    />
                  )}
                  <item.icon className="h-4 w-4 sm:h-[18px] sm:w-[18px] shrink-0" />
                  {!collapsed && <span className="truncate">{item.label}</span>}
                </Link>
              );
            })}
          </div>
        ))}
      </nav>

      {/* Footer */}
      {user && (
        <div className="border-t border-sidebar-border p-2 sm:p-3 shrink-0">
          {!collapsed ? (
            <div className="flex items-center gap-2.5">
              <Link to="/profile" onClick={onNavClick} className="shrink-0">
                <UserAvatar name={user.name} avatarUrl={user.avatar_url} size="sm" />
              </Link>
              <Link to="/profile" onClick={onNavClick} className="flex-1 min-w-0 hover:opacity-80 transition-opacity">
                <p className="text-xs sm:text-sm font-medium truncate leading-tight text-foreground">{user.name}</p>
                <p className="text-[10px] text-sidebar-foreground/60 truncate">{user.role.name}</p>
              </Link>
              <button
                onClick={() => logout()}
                className="p-1.5 rounded-md text-sidebar-foreground/50 hover:text-destructive hover:bg-destructive/10 transition-colors shrink-0"
                title="Sair"
              >
                <LogOut className="h-3.5 w-3.5" />
              </button>
            </div>
          ) : (
            <div className="flex flex-col items-center gap-1.5">
              <Link to="/profile" onClick={onNavClick} title={user.name}>
                <UserAvatar name={user.name} avatarUrl={user.avatar_url} size="sm" />
              </Link>
              <button
                onClick={() => logout()}
                className="w-full flex items-center justify-center p-1.5 rounded-md text-sidebar-foreground/50 hover:text-destructive hover:bg-destructive/10 transition-colors"
                title="Sair"
              >
                <LogOut className="h-3.5 w-3.5" />
              </button>
            </div>
          )}
        </div>
      )}
    </aside>
  );
}
