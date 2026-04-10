import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  BarChart3,
  CalendarDays,
  ChevronDown,
  ChevronLeft,
  ClipboardList,
  CreditCard,
  GalleryHorizontalEnd,
  Gamepad2,
  Globe,
  Image,
  LayoutDashboard,
  LogOut,
  MessageCircle,
  Monitor,
  Settings,
  ShieldCheck,
  Sparkles,
  UserCheck,
  Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { useAuth } from '@/app/providers/AuthProvider';
import { preloadRouteForPath } from '@/app/routing/route-preload';
import { formatRoleLabel } from '@/shared/auth/labels';
import { UserAvatar } from '@/shared/components/UserAvatar';
import { WHATSAPP_SETTINGS_PATH } from '@/modules/whatsapp/paths';

interface NavItem {
  key: string;
  label: string;
  icon: LucideIcon;
  path?: string;
  exact?: boolean;
  permission?: string;
  permissions?: string[];
  module?: string;
  children?: NavItem[];
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
    label: 'Conteudo',
    section: 'content',
    items: [
      { key: 'media', label: 'Midias', icon: Image, path: '/media', permission: 'media.view', module: 'media' },
      { key: 'moderation', label: 'Moderacao', icon: ShieldCheck, path: '/moderation', permission: 'media.moderate', module: 'moderation' },
      { key: 'gallery', label: 'Galeria', icon: GalleryHorizontalEnd, path: '/gallery', permission: 'gallery.view', module: 'gallery' },
      { key: 'wall', label: 'Telao', icon: Monitor, path: '/wall', permission: 'wall.view', module: 'wall' },
      { key: 'play', label: 'Jogos', icon: Gamepad2, path: '/play', permission: 'play.view', module: 'play' },
      { key: 'hub', label: 'Links', icon: Globe, path: '/hub', permission: 'hub.view', module: 'hub' },
    ],
  },
  {
    label: 'Gestao',
    section: 'management',
    items: [
      { key: 'partners', label: 'Parceiros', icon: Users, path: '/partners', permissions: ['partners.view.any', 'partners.manage.any'], module: 'partners' },
      { key: 'clients', label: 'Clientes', icon: UserCheck, path: '/clients', permission: 'clients.view', module: 'clients' },
      { key: 'plans', label: 'Planos', icon: CreditCard, path: '/plans', permission: 'billing.view', module: 'plans' },
      { key: 'analytics', label: 'Relatorios', icon: BarChart3, path: '/analytics', permission: 'analytics.view', module: 'analytics' },
      { key: 'audit', label: 'Auditoria', icon: ClipboardList, path: '/audit', permission: 'audit.view', module: 'audit' },
      { key: 'ai-media-replies', label: 'Moderação IA', icon: Sparkles, path: '/ia/moderacao-de-midia', permission: 'settings.manage', module: 'settings' },
      {
        key: 'settings',
        label: 'Configuracoes',
        icon: Settings,
        children: [
          { key: 'settings-general', label: 'Geral', icon: Settings, path: '/settings', exact: true, permission: 'settings.manage', module: 'settings' },
          {
            key: 'settings-whatsapp',
            label: 'WhatsApp',
            icon: MessageCircle,
            path: WHATSAPP_SETTINGS_PATH,
            permissions: ['channels.view', 'channels.manage'],
          },
        ],
      },
    ],
  },
];

function isPathActive(path: string, pathname: string, exact = false) {
  if (path === '/') {
    return pathname === '/';
  }

  if (exact) {
    return pathname === path;
  }

  return pathname === path || pathname.startsWith(`${path}/`);
}

interface AppSidebarProps {
  collapsed: boolean;
  onToggle: () => void;
  onNavClick?: () => void;
}

export function AppSidebar({ collapsed, onToggle, onNavClick }: AppSidebarProps) {
  const location = useLocation();
  const { meUser: user, workspaces, can, canAccessModule, logout } = useAuth();
  const [expandedItems, setExpandedItems] = useState<Record<string, boolean>>({
    settings: true,
  });

  const roleLabel = user ? formatRoleLabel(user.role.key, user.role.name) : '';

  const hasOwnAccess = (item: NavItem) => {
    if (!item.path && !item.permission && !item.permissions?.length && !item.module) {
      return false;
    }

    const permissionAllowed = item.permissions?.length
      ? item.permissions.some((permission) => can(permission))
      : item.permission
        ? can(item.permission)
        : true;

    if (!permissionAllowed) {
      return false;
    }

    if (item.module && !canAccessModule(item.module)) {
      return false;
    }

    return true;
  };

  const filterItems = (items: NavItem[]): NavItem[] => {
    return items.reduce<NavItem[]>((accumulator, item) => {
      const visibleChildren = item.children ? filterItems(item.children) : undefined;

      if (!hasOwnAccess(item) && (!visibleChildren || visibleChildren.length === 0)) {
        return accumulator;
      }

      accumulator.push({
        ...item,
        children: visibleChildren,
      });

      return accumulator;
    }, []);
  };

  const filteredGroups = NAV_CONFIG
    .map((group) => ({
      ...group,
      items: filterItems(group.items),
    }))
    .filter((group) => group.items.length > 0)
    .map((group) => {
      if (group.section !== 'main' || workspaces.event_accesses.length === 0) {
        return group;
      }

      const myEventsItem: NavItem = {
        key: 'my-events',
        label: 'Meus eventos',
        icon: CalendarDays,
        path: '/my-events',
      };

      const alreadyPresent = group.items.some((item) => item.key === myEventsItem.key);

      return alreadyPresent
        ? group
        : {
            ...group,
            items: [...group.items, myEventsItem],
          };
    });

  const isItemActive = (item: NavItem): boolean => {
    if (item.path && isPathActive(item.path, location.pathname, item.exact)) {
      return true;
    }

    return item.children?.some((child) => isItemActive(child)) ?? false;
  };

  const navItemClasses = (active: boolean, nested = false) => cn(
    'relative flex items-center gap-3 rounded-lg text-sm font-medium transition-all duration-150',
    nested ? 'ml-4 px-3 py-2 pl-6' : 'px-3 py-2.5',
    collapsed && !nested && 'justify-center px-2',
    active
      ? 'bg-primary/10 text-primary'
      : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-foreground',
  );

  const renderActiveIndicator = (nested = false) => (
    <motion.div
      layoutId={nested ? 'sidebar-sub-indicator' : 'sidebar-indicator'}
      className={cn(
        'absolute top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-r-full bg-primary',
        nested ? 'left-0' : 'left-0',
      )}
      transition={{ type: 'spring', bounce: 0.2, duration: 0.4 }}
    />
  );

  const renderLeafItem = (item: NavItem, nested = false) => {
    if (!item.path) {
      return null;
    }

    const active = isItemActive(item);

    return (
      <Link
        key={item.key}
        to={item.path}
        onClick={onNavClick}
        onMouseEnter={() => preloadRouteForPath(item.path as string)}
        onFocus={() => preloadRouteForPath(item.path as string)}
        className={navItemClasses(active, nested)}
        title={collapsed && !nested ? item.label : undefined}
      >
        {active ? renderActiveIndicator(nested) : null}
        <item.icon className={cn('shrink-0', nested ? 'h-4 w-4' : 'h-[18px] w-[18px]')} />
        {!collapsed || nested ? <span className="truncate">{item.label}</span> : null}
      </Link>
    );
  };

  const renderParentItem = (item: NavItem) => {
    const active = isItemActive(item);
    const firstChildPath = item.children?.find((child) => child.path)?.path;

    if (collapsed) {
      if (!firstChildPath) {
        return null;
      }

      return (
        <Link
          key={item.key}
          to={firstChildPath}
          onClick={onNavClick}
          onMouseEnter={() => preloadRouteForPath(firstChildPath)}
          onFocus={() => preloadRouteForPath(firstChildPath)}
          className={navItemClasses(active)}
          title={item.label}
        >
          {active ? renderActiveIndicator() : null}
          <item.icon className="h-[18px] w-[18px] shrink-0" />
        </Link>
      );
    }

    const isOpen = expandedItems[item.key] ?? active;

    return (
      <Collapsible
        key={item.key}
        open={isOpen}
        onOpenChange={(open) => {
          setExpandedItems((current) => ({
            ...current,
            [item.key]: open,
          }));
        }}
      >
        <CollapsibleTrigger asChild>
          <button type="button" className={navItemClasses(active)}>
            {active ? renderActiveIndicator() : null}
            <item.icon className="h-[18px] w-[18px] shrink-0" />
            <span className="truncate">{item.label}</span>
            <ChevronDown className={cn('ml-auto h-4 w-4 shrink-0 transition-transform', isOpen && 'rotate-180')} />
          </button>
        </CollapsibleTrigger>

        <CollapsibleContent className="mt-1 space-y-1">
          {item.children?.map((child) => renderLeafItem(child, true))}
        </CollapsibleContent>
      </Collapsible>
    );
  };

  return (
    <aside
      className={cn(
        'flex h-full flex-col border-r border-sidebar-border bg-sidebar transition-all duration-300',
        collapsed ? 'w-16' : 'w-60',
      )}
    >
      <div className="flex h-14 items-center justify-between border-b border-sidebar-border px-3 sm:h-16 sm:px-4 shrink-0">
        {!collapsed ? (
          <Link to="/" className="flex items-center gap-2" onClick={onNavClick}>
            <Sparkles className="h-5 w-5 shrink-0 text-primary sm:h-6 sm:w-6" />
            <span className="truncate text-base font-bold gradient-text sm:text-lg">Evento Vivo</span>
          </Link>
        ) : (
          <Link to="/" className="mx-auto" onClick={onNavClick}>
            <Sparkles className="h-5 w-5 text-primary sm:h-6 sm:w-6" />
          </Link>
        )}

        <button
          onClick={onToggle}
          className={cn(
            'hidden rounded-md p-1 text-sidebar-foreground transition-colors hover:bg-sidebar-accent lg:flex',
            collapsed && 'mx-auto mt-0',
          )}
          aria-label={collapsed ? 'Expandir sidebar' : 'Recolher sidebar'}
        >
          <ChevronLeft className={cn('h-4 w-4 transition-transform', collapsed && 'rotate-180')} />
        </button>
      </div>

      <nav className="flex-1 overflow-y-auto px-2 py-2 space-y-0.5 scrollbar-thin sm:py-3">
        {filteredGroups.map((group) => (
          <div key={group.section}>
            {!collapsed ? (
              <p className="px-3 pb-1.5 pt-4 text-[10px] font-semibold uppercase tracking-wider text-sidebar-foreground/50">
                {group.label}
              </p>
            ) : (
              <div className="h-3" />
            )}

            {group.items.map((item) => {
              if (item.children?.length) {
                return renderParentItem(item);
              }

              return renderLeafItem(item);
            })}
          </div>
        ))}
      </nav>

      {user ? (
        <div className="border-t border-sidebar-border p-2 sm:p-3 shrink-0">
          {!collapsed ? (
            <div className="flex items-center gap-2.5">
              <Link to="/profile" onClick={onNavClick} className="shrink-0">
                <UserAvatar name={user.name} avatarUrl={user.avatar_url} size="sm" />
              </Link>

              <Link to="/profile" onClick={onNavClick} className="min-w-0 flex-1 transition-opacity hover:opacity-80">
                <p className="truncate text-xs font-medium leading-tight text-foreground sm:text-sm">{user.name}</p>
                <p className="truncate text-[10px] text-sidebar-foreground/60">{roleLabel}</p>
              </Link>

              <button
                onClick={() => logout()}
                className="shrink-0 rounded-md p-1.5 text-sidebar-foreground/50 transition-colors hover:bg-destructive/10 hover:text-destructive"
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
                className="flex w-full items-center justify-center rounded-md p-1.5 text-sidebar-foreground/50 transition-colors hover:bg-destructive/10 hover:text-destructive"
                title="Sair"
              >
                <LogOut className="h-3.5 w-3.5" />
              </button>
            </div>
          )}
        </div>
      ) : null}
    </aside>
  );
}
