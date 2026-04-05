import { History, RotateCcw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export function SessionResumeBanner({
  playerName,
  expiresAt,
  onResume,
  onDiscard,
  isPending = false,
}: {
  playerName?: string | null;
  expiresAt?: string | null;
  onResume: () => void;
  onDiscard: () => void;
  isPending?: boolean;
}) {
  return (
    <Card className="border-amber-500/20 bg-amber-500/10 shadow-none">
      <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-300/15 text-amber-100">
            <History className="h-5 w-5" />
          </div>
          <div className="space-y-1">
            <p className="text-sm font-semibold text-white">
              Existe uma partida em andamento{playerName ? ` para ${playerName}` : ''}.
            </p>
            <p className="text-sm text-white/75">
              {expiresAt
                ? `Voce pode tentar retomar essa sessao ate ${new Date(expiresAt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}.`
                : 'Voce pode retomar essa sessao agora.'}
            </p>
          </div>
        </div>

        <div className="flex gap-2">
          <Button variant="outline" className="border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white" onClick={onDiscard} disabled={isPending}>
            Descartar
          </Button>
          <Button onClick={onResume} disabled={isPending}>
            <RotateCcw className="mr-1.5 h-4 w-4" />
            Retomar
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
