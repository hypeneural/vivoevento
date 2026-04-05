import { Download, Smartphone } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useInstallPwaPrompt } from '@/modules/play/hooks/useInstallPwaPrompt';

export function PwaInstallBanner() {
  const { canInstall, isInstalled, promptInstall } = useInstallPwaPrompt();

  if (isInstalled || !canInstall) {
    return null;
  }

  return (
    <Card className="border-emerald-500/20 bg-emerald-500/10 shadow-none">
      <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-400/15 text-emerald-200">
            <Smartphone className="h-5 w-5" />
          </div>
          <div className="space-y-1">
            <p className="text-sm font-semibold text-white">Instale o Play no celular</p>
            <p className="text-sm text-white/75">
              Abra mais rapido, mantenha o shell em cache e volte para os jogos sem depender de buscar tudo de novo.
            </p>
          </div>
        </div>

        <Button onClick={() => void promptInstall()} className="sm:min-w-[170px]">
          <Download className="mr-1.5 h-4 w-4" />
          Instalar app
        </Button>
      </CardContent>
    </Card>
  );
}
