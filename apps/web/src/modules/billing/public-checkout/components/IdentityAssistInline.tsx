import { AlertCircle, CheckCircle2, Loader2, LogIn } from 'lucide-react';

import type { PublicCheckoutIdentityCheckResponse } from '@/lib/api-types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type IdentityAssistInlineProps = {
  state?: PublicCheckoutIdentityCheckResponse | null;
  isChecking?: boolean;
};

export function IdentityAssistInline({
  state,
  isChecking = false,
}: IdentityAssistInlineProps) {
  if (isChecking) {
    return (
      <Alert>
        <Loader2 className="h-4 w-4 animate-spin" />
        <AlertTitle>Verificando seus dados...</AlertTitle>
        <AlertDescription>
          Estamos conferindo se existe uma conta compativel para acelerar sua compra.
        </AlertDescription>
      </Alert>
    );
  }

  if (!state) {
    return null;
  }

  const isMismatch = state.identity_status === 'authenticated_mismatch';
  const Icon = state.identity_status === 'login_suggested'
    ? LogIn
    : isMismatch
      ? AlertCircle
      : CheckCircle2;

  return (
    <Alert variant={isMismatch ? 'destructive' : 'default'}>
      <Icon className="h-4 w-4" />
      <AlertTitle>{state.title}</AlertTitle>
      <AlertDescription>
        <p>{state.description}</p>
        {state.login_url && state.action_label ? (
          <a
            href={state.login_url}
            className="mt-3 inline-flex text-sm font-medium underline underline-offset-4"
          >
            {state.action_label}
          </a>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}
