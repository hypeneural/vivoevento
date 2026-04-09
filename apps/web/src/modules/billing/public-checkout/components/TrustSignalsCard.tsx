import { ShieldCheck } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function TrustSignalsCard() {
  return (
    <Card className="border-slate-200 bg-white">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-lg">
          <ShieldCheck className="h-4 w-4 text-emerald-600" />
          Pagamento seguro
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-2 text-sm text-slate-600">
        <p>Pix ou cartao com confirmacao automatica.</p>
        <p>Seus dados sensiveis de cartao continuam fora do nosso servidor.</p>
      </CardContent>
    </Card>
  );
}
