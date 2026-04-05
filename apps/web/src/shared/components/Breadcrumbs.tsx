import { ChevronRight } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';

const routeLabels: Record<string, string> = {
  '': 'Dashboard',
  'events': 'Eventos',
  'events/create': 'Criar Evento',
  'media': 'Mídias',
  'moderation': 'Moderação',
  'gallery': 'Galeria',
  'wall': 'Wall',
  'play': 'Play',
  'hub': 'Hub',
  'whatsapp': 'WhatsApp',
  'partners': 'Parceiros',
  'clients': 'Clientes',
  'plans': 'Planos & Billing',
  'analytics': 'Analytics',
  'audit': 'Auditoria',
  'settings': 'Configurações',
  'settings/whatsapp': 'WhatsApp',
};

export function Breadcrumbs() {
  const location = useLocation();
  const segments = location.pathname.split('/').filter(Boolean);

  if (segments.length === 0) return null;

  return (
    <nav className="flex items-center gap-1 text-sm text-muted-foreground mb-4">
      <Link to="/" className="hover:text-foreground transition-colors">Dashboard</Link>
      {segments.map((segment, i) => {
        const path = '/' + segments.slice(0, i + 1).join('/');
        const label = routeLabels[segments.slice(0, i + 1).join('/')] || segment;
        const isLast = i === segments.length - 1;
        return (
          <span key={path} className="flex items-center gap-1">
            <ChevronRight className="h-3.5 w-3.5" />
            {isLast ? (
              <span className="text-foreground font-medium">{label}</span>
            ) : (
              <Link to={path} className="hover:text-foreground transition-colors">{label}</Link>
            )}
          </span>
        );
      })}
    </nav>
  );
}
