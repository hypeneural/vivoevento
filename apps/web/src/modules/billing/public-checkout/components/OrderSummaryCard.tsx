import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type OrderSummaryCardProps = {
  pkg?: {
    name: string;
    priceLabel: string;
    subtitle: string;
  } | null;
};

export function OrderSummaryCard({ pkg }: OrderSummaryCardProps) {
  return (
    <Card className="border-slate-200 bg-white">
      <CardHeader>
        <CardTitle className="text-lg">Seu pacote</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-slate-600">
        {pkg ? (
          <>
            <p className="font-semibold text-slate-950">{pkg.name}</p>
            <p>{pkg.priceLabel}</p>
            <p>{pkg.subtitle}</p>
          </>
        ) : (
          <p>Escolha um pacote para ver o resumo da compra.</p>
        )}
      </CardContent>
    </Card>
  );
}
