import { useLocation, Link } from 'react-router-dom';
import { Home, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function NotFound() {
  const location = useLocation();

  return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <div className="text-center space-y-4 px-4">
        <p className="text-6xl sm:text-7xl font-bold gradient-text">404</p>
        <div className="space-y-1.5">
          <h1 className="text-lg sm:text-xl font-semibold">Página não encontrada</h1>
          <p className="text-sm text-muted-foreground max-w-sm mx-auto">
            A página <code className="text-xs bg-muted px-1.5 py-0.5 rounded">{location.pathname}</code> não existe ou foi removida.
          </p>
        </div>
        <div className="flex items-center justify-center gap-3 pt-2">
          <Button variant="outline" size="sm" onClick={() => window.history.back()}>
            <ArrowLeft className="h-4 w-4 mr-1.5" />
            Voltar
          </Button>
          <Button size="sm" asChild className="gradient-primary border-0">
            <Link to="/">
              <Home className="h-4 w-4 mr-1.5" />
              Dashboard
            </Link>
          </Button>
        </div>
      </div>
    </div>
  );
}
