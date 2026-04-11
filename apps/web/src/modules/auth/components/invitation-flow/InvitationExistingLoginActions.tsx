import { LogIn } from 'lucide-react';
import { Link } from 'react-router-dom';

import { Button } from '@/components/ui/button';

type InvitationExistingLoginActionsProps = {
  intro: string;
  loginPath: string;
  forgotPasswordPath: string;
  loginLabel?: string;
};

export function InvitationExistingLoginActions({
  intro,
  loginPath,
  forgotPasswordPath,
  loginLabel = 'Entrar para aceitar convite',
}: InvitationExistingLoginActionsProps) {
  return (
    <div className="space-y-4">
      <div className="rounded-2xl border bg-muted/20 p-4 text-sm text-muted-foreground">
        {intro}
      </div>
      <Button asChild className="w-full">
        <Link to={loginPath}>
          <LogIn className="mr-2 h-4 w-4" />
          {loginLabel}
        </Link>
      </Button>
      <Button asChild variant="ghost" className="w-full">
        <Link to={forgotPasswordPath}>Esqueci a senha</Link>
      </Button>
      <div className="rounded-2xl border bg-background px-4 py-3 text-sm text-muted-foreground">
        <p>Você voltará para este convite após entrar.</p>
        <p className="mt-1">Essa mesma conta pode ser usada em vários eventos e convites.</p>
      </div>
    </div>
  );
}
