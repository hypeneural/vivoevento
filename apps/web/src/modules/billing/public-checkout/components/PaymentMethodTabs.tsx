import { CreditCard, QrCode } from 'lucide-react';

import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

type PaymentMethodTabsProps = {
  value: 'pix' | 'credit_card';
  onValueChange: (value: 'pix' | 'credit_card') => void;
};

export function PaymentMethodTabs({
  value,
  onValueChange,
}: PaymentMethodTabsProps) {
  return (
    <Tabs value={value} onValueChange={(nextValue) => onValueChange(nextValue as 'pix' | 'credit_card')}>
      <TabsList className="grid h-auto w-full grid-cols-2 rounded-2xl bg-slate-100 p-1">
        <TabsTrigger value="pix" className="min-h-[72px] rounded-xl px-4 py-3 data-[state=active]:bg-white">
          <span className="flex items-center gap-3 text-left">
            <QrCode className="h-5 w-5 text-sky-600" />
            <span className="space-y-1">
              <span className="block text-sm font-semibold text-slate-950">Pix</span>
              <span className="block text-xs text-slate-500">Mais rapido para finalizar</span>
            </span>
          </span>
        </TabsTrigger>
        <TabsTrigger value="credit_card" className="min-h-[72px] rounded-xl px-4 py-3 data-[state=active]:bg-white">
          <span className="flex items-center gap-3 text-left">
            <CreditCard className="h-5 w-5 text-emerald-600" />
            <span className="space-y-1">
              <span className="block text-sm font-semibold text-slate-950">Cartao</span>
              <span className="block text-xs text-slate-500">Pagamento seguro por credito</span>
            </span>
          </span>
        </TabsTrigger>
      </TabsList>
      <TabsContent value="pix" className="hidden" />
      <TabsContent value="credit_card" className="hidden" />
    </Tabs>
  );
}
