import { ChevronRight } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';

const routeLabels: Record<string, string> = {
  '': 'Dashboard',
  'events': 'Eventos',
  'events/create': 'Criar Evento',
  'media': 'Midias',
  'moderation': 'Moderacao',
  'gallery': 'Galeria',
  'wall': 'Telao',
  'play': 'Jogos',
  'hub': 'Links',
  'whatsapp': 'WhatsApp',
  'partners': 'Parceiros',
  'clients': 'Clientes',
  'plans': 'Planos e Cobranca',
  'analytics': 'Relatorios',
  'audit': 'Auditoria',
  'settings': 'Configuracoes',
  'settings/whatsapp': 'WhatsApp',
  'ia': 'IA',
  'ia/respostas-de-midia': 'Respostas automaticas de midia',
};

export function Breadcrumbs() {
  const location = useLocation();
  const segments = location.pathname.split('/').filter(Boolean);

  if (segments.length === 0) {
    return null;
  }

  return (
    <nav className="mb-4 flex items-center gap-1 text-sm text-muted-foreground">
      <Link to="/" className="transition-colors hover:text-foreground">Dashboard</Link>
      {segments.map((segment, index) => {
        const path = `/${segments.slice(0, index + 1).join('/')}`;
        const label = routeLabels[segments.slice(0, index + 1).join('/')] || segment;
        const isLast = index === segments.length - 1;

        return (
          <span key={path} className="flex items-center gap-1">
            <ChevronRight className="h-3.5 w-3.5" />
            {isLast ? (
              <span className="font-medium text-foreground">{label}</span>
            ) : (
              <Link to={path} className="transition-colors hover:text-foreground">{label}</Link>
            )}
          </span>
        );
      })}
    </nav>
  );
}
