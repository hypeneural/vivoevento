import { Loader2, LogIn, ShieldCheck } from 'lucide-react';

import { Button } from '@/components/ui/button';

type AuthenticatedInvitationActionsProps = {
  userName: string;
  acceptLabel?: string;
  onAccept: () => void;
  onSwitchAccount: () => void;
  isAccepting?: boolean;
  isSwitchingAccount?: boolean;
};

export function AuthenticatedInvitationActions({
  userName,
  acceptLabel = 'Aceitar convite e entrar',
  onAccept,
  onSwitchAccount,
  isAccepting = false,
  isSwitchingAccount = false,
}: AuthenticatedInvitationActionsProps) {
  return (
    <div className="space-y-4">
      <div className="rounded-2xl border bg-muted/20 p-4 text-sm">
        <p className="font-medium">Voce esta logado como</p>
        <p className="mt-1 text-muted-foreground">{userName}</p>
        <p className="mt-2 text-muted-foreground">
          Se este nao for o contato convidado, entre com outra conta antes de aceitar.
        </p>
      </div>
      <Button className="w-full" onClick={onAccept} disabled={isAccepting}>
        {isAccepting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <ShieldCheck className="mr-2 h-4 w-4" />}
        {acceptLabel}
      </Button>
      <Button variant="ghost" className="w-full" onClick={onSwitchAccount} disabled={isAccepting || isSwitchingAccount}>
        {isSwitchingAccount ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <LogIn className="mr-2 h-4 w-4" />}
        Usar outra conta
      </Button>
    </div>
  );
}
