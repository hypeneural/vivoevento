import { Lock, ArrowUpCircle } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { SYSTEM_MODULES } from '@/shared/auth/modules';

interface FeatureLockProps {
  moduleKey: string;
  title?: string;
  description?: string;
}

/**
 * Full-page overlay shown when a module is not available
 * for the current organization (upgrade required).
 */
export function FeatureLock({ moduleKey, title, description }: FeatureLockProps) {
  const moduleDef = SYSTEM_MODULES[moduleKey];
  const moduleLabel = moduleDef?.label ?? moduleKey;
  const moduleDesc = moduleDef?.description ?? '';

  return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <div className="text-center space-y-4 px-4 max-w-md">
        <div className="mx-auto h-16 w-16 rounded-2xl bg-muted flex items-center justify-center">
          <Lock className="h-7 w-7 text-muted-foreground" />
        </div>
        <div className="space-y-1.5">
          <h2 className="text-lg font-semibold">
            {title ?? `Módulo ${moduleLabel} não disponível`}
          </h2>
          <p className="text-sm text-muted-foreground max-w-sm mx-auto">
            {description ?? `O módulo "${moduleLabel}" não está habilitado no plano da sua organização. ${moduleDesc ? moduleDesc + '.' : ''}`}
          </p>
        </div>
        <div className="flex items-center justify-center gap-3 pt-2">
          <Button variant="outline" size="sm" asChild>
            <Link to="/">Voltar ao Dashboard</Link>
          </Button>
          <Button size="sm" asChild className="gradient-primary border-0">
            <Link to="/plans">
              <ArrowUpCircle className="h-4 w-4 mr-1.5" />
              Ver Planos
            </Link>
          </Button>
        </div>
      </div>
    </div>
  );
}
