import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Search, Bell, Plus, LogOut, Menu, Sun, Moon, Monitor, User, Settings, Shield, X } from 'lucide-react';
import { useAuth } from '@/app/providers/AuthProvider';
import { useTheme } from '@/app/providers/ThemeProvider';
import type { ThemeMode } from '@/app/providers/ThemeProvider';
import { mockNotifications } from '@/shared/mock/data';
import { formatRoleLabel } from '@/shared/auth/labels';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
  DropdownMenuSeparator, DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { UserAvatar } from '@/shared/components/UserAvatar';
import { GlobalSearch } from '@/shared/components/GlobalSearch';

interface AppHeaderProps {
  onMenuToggle?: () => void;
}

const USE_MOCK = import.meta.env.VITE_USE_MOCK !== 'false';

const themeOptions: { value: ThemeMode; label: string; icon: typeof Sun }[] = [
  { value: 'light', label: 'Claro', icon: Sun },
  { value: 'dark', label: 'Escuro', icon: Moon },
  { value: 'system', label: 'Sistema', icon: Monitor },
];

export function AppHeader({ onMenuToggle }: AppHeaderProps) {
  const { meUser: user, meOrganization: organization, loginMock, logout, availableUsers, can } = useAuth();
  const { theme, setTheme, resolvedTheme } = useTheme();
  const navigate = useNavigate();
  const unreadCount = mockNotifications.filter(n => !n.read).length;
  const [mobileSearchOpen, setMobileSearchOpen] = useState(false);

  if (!user) return null;

  const CurrentThemeIcon = resolvedTheme === 'dark' ? Moon : Sun;
  const roleLabel = formatRoleLabel(user.role.key, user.role.name);
  const orgDisplay = organization?.name?.[0] || '🏢';

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <>
    <header className="sticky top-0 z-20 flex h-14 sm:h-16 items-center gap-2 sm:gap-3 border-b border-border bg-background/80 backdrop-blur-xl px-3 sm:px-4 lg:px-6 shrink-0">
      {/* Mobile menu toggle */}
      <button
        onClick={onMenuToggle}
        className="lg:hidden rounded-md p-2 -ml-1 text-muted-foreground hover:text-foreground active:bg-muted/50 transition-colors"
        aria-label="Abrir menu"
      >
        <Menu className="h-5 w-5" />
      </button>

      {/* Search — Desktop */}
      <GlobalSearch className="hidden sm:flex flex-1 max-w-md" />

      {/* Search — Mobile toggle */}
      <button
        onClick={() => setMobileSearchOpen(prev => !prev)}
        className="sm:hidden rounded-md p-2 text-muted-foreground hover:text-foreground transition-colors"
        aria-label="Buscar"
      >
        {mobileSearchOpen ? <X className="h-[18px] w-[18px]" /> : <Search className="h-[18px] w-[18px]" />}
      </button>

      <div className="flex-1" />

      <div className="flex items-center gap-1 sm:gap-1.5">
        {/* Org Display */}
        {organization && (
          <div className="hidden md:flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-muted/40 border border-border/30">
            <span className="text-sm">{orgDisplay}</span>
            <span className="max-w-[100px] lg:max-w-[140px] truncate text-xs text-muted-foreground">{organization.name}</span>
          </div>
        )}

        {/* New Event */}
        {can('events.create') && (
          <Button size="sm" asChild className="gradient-primary border-0 h-8 sm:h-9 text-xs sm:text-sm px-2.5 sm:px-3">
            <Link to="/events/create">
              <Plus className="h-3.5 w-3.5 sm:h-4 sm:w-4 sm:mr-1" />
              <span className="hidden sm:inline">Novo Evento</span>
            </Link>
          </Button>
        )}

        {/* Theme Toggle */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8 sm:h-9 sm:w-9" aria-label="Alterar tema">
              <CurrentThemeIcon className="h-4 w-4 sm:h-[18px] sm:w-[18px]" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-36">
            <DropdownMenuLabel className="text-xs">Tema</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {themeOptions.map(opt => (
              <DropdownMenuItem
                key={opt.value}
                onClick={() => setTheme(opt.value)}
                className={theme === opt.value ? 'bg-muted' : ''}
              >
                <opt.icon className="h-4 w-4 mr-2" />
                {opt.label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        {/* Notifications */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="relative h-8 w-8 sm:h-9 sm:w-9"
              aria-label="Abrir notificações"
            >
              <Bell className="h-4 w-4 sm:h-[18px] sm:w-[18px]" />
              {unreadCount > 0 && (
                <span className="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-destructive text-[9px] sm:text-[10px] font-bold text-destructive-foreground flex items-center justify-center">
                  {unreadCount}
                </span>
              )}
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-72 sm:w-80">
            <DropdownMenuLabel>Notificações</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {mockNotifications.slice(0, 4).map(n => (
              <DropdownMenuItem key={n.id} className="flex flex-col items-start gap-0.5 py-2 sm:py-2.5">
                <span className="text-xs sm:text-sm font-medium">{n.title}</span>
                <span className="text-[10px] sm:text-xs text-muted-foreground line-clamp-1">{n.message}</span>
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        {/* User Menu */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="gap-1.5 sm:gap-2 h-8 sm:h-9 px-1.5 sm:px-2">
              <UserAvatar name={user.name} avatarUrl={user.avatar_url} size="xs" />
              <div className="hidden md:flex flex-col items-start">
                <span className="text-xs sm:text-sm font-medium leading-none truncate max-w-[100px]">{user.name}</span>
                <span className="text-[9px] sm:text-[10px] text-muted-foreground">{roleLabel}</span>
              </div>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56 sm:w-60">
            {/* User info header */}
            <div className="px-2 py-2.5">
              <div className="flex items-center gap-2.5">
                <UserAvatar name={user.name} avatarUrl={user.avatar_url} size="md" />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium truncate">{user.name}</p>
                  <p className="text-[10px] text-muted-foreground truncate">{user.email}</p>
                  <Badge variant="outline" className="mt-1 text-[9px] px-1.5 py-0 h-4 gap-0.5">
                    <Shield className="h-2 w-2" />
                    {roleLabel}
                  </Badge>
                </div>
              </div>
            </div>

            <DropdownMenuSeparator />

            {/* Profile */}
            <DropdownMenuItem onClick={() => navigate('/profile')} className="text-xs sm:text-sm cursor-pointer">
              <User className="h-3.5 w-3.5 sm:h-4 sm:w-4 mr-2" />
              Meu Perfil
            </DropdownMenuItem>

            {/* Settings */}
            {can('settings.manage') && (
              <DropdownMenuItem onClick={() => navigate('/settings')} className="text-xs sm:text-sm cursor-pointer">
                <Settings className="h-3.5 w-3.5 sm:h-4 sm:w-4 mr-2" />
                Configurações
              </DropdownMenuItem>
            )}

            {/* Dev: Profile Switcher */}
            {USE_MOCK && (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuLabel className="text-[10px] text-muted-foreground font-normal">
                  Trocar Perfil (Dev)
                </DropdownMenuLabel>
                {availableUsers.map(u => (
                  <DropdownMenuItem key={u.id} onClick={() => loginMock(u.id)} className="text-xs cursor-pointer">
                    <div className="h-5 w-5 rounded-full gradient-primary flex items-center justify-center text-[8px] font-bold text-primary-foreground shrink-0 mr-2">
                      {u.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
                    </div>
                    <span className="truncate flex-1">{u.name}</span>
                    <Badge variant="secondary" className="ml-auto text-[8px] px-1 shrink-0">{u.role}</Badge>
                  </DropdownMenuItem>
                ))}
              </>
            )}

            <DropdownMenuSeparator />

            {/* Logout */}
            <DropdownMenuItem onClick={handleLogout} className="text-destructive focus:text-destructive cursor-pointer text-xs sm:text-sm">
              <LogOut className="h-3.5 w-3.5 sm:h-4 sm:w-4 mr-2" />
              Sair da conta
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>

    {/* Mobile search overlay */}
    {mobileSearchOpen && (
      <div className="sm:hidden sticky top-14 z-20 border-b border-border bg-background/95 backdrop-blur-xl px-3 py-2 animate-in slide-in-from-top-1 duration-150">
        <GlobalSearch className="w-full" />
      </div>
    )}
    </>
  );
}
