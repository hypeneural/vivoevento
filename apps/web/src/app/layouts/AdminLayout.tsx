import { useState, useEffect } from 'react';
import { Outlet } from 'react-router-dom';
import { AppSidebar } from './AppSidebar';
import { AppHeader } from './AppHeader';
import { Breadcrumbs } from '@/shared/components/Breadcrumbs';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

export function AdminLayout() {
  const isMobile = useIsMobile();
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  // Auto-close mobile sidebar on route change
  useEffect(() => {
    if (isMobile) setMobileOpen(false);
  }, [isMobile]);

  // Close mobile menu on escape
  useEffect(() => {
    if (!mobileOpen) return;
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setMobileOpen(false);
    };
    document.addEventListener('keydown', handleEsc);
    return () => document.removeEventListener('keydown', handleEsc);
  }, [mobileOpen]);

  // Prevent body scroll when mobile menu is open
  useEffect(() => {
    if (mobileOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => { document.body.style.overflow = ''; };
  }, [mobileOpen]);

  return (
    <div className="min-h-[100dvh] bg-background">
      {/* Mobile overlay */}
      {mobileOpen && (
        <div
          className="fixed inset-0 z-30 bg-background/80 backdrop-blur-sm lg:hidden"
          onClick={() => setMobileOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Sidebar */}
      <div className={cn(
        'fixed left-0 top-0 z-40 h-full transition-transform duration-300 lg:translate-x-0',
        mobileOpen ? 'translate-x-0' : '-translate-x-full'
      )}>
        <AppSidebar
          collapsed={isMobile ? false : sidebarCollapsed}
          onToggle={() => {
            if (isMobile) {
              setMobileOpen(false);
            } else {
              setSidebarCollapsed(c => !c);
            }
          }}
          onNavClick={() => {
            if (isMobile) setMobileOpen(false);
          }}
        />
      </div>

      {/* Main */}
      <div className={cn(
        'transition-all duration-300 min-h-[100dvh] flex flex-col',
        !isMobile && (sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-60'),
      )}>
        <AppHeader onMenuToggle={() => setMobileOpen(o => !o)} />
        <main className="flex-1 p-3 sm:p-4 lg:p-6">
          <Breadcrumbs />
          <Outlet />
        </main>
      </div>
    </div>
  );
}
