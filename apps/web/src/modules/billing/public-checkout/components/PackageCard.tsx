import { CheckCircle2, Sparkles } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

import type { CommercialPackageCopy } from '../mappers/packageCommercialCopy';

type PackageCardProps = {
  pkg: CommercialPackageCopy;
  selected?: boolean;
  onSelect: (pkg: CommercialPackageCopy) => void;
};

export function PackageCard({
  pkg,
  selected = false,
  onSelect,
}: PackageCardProps) {
  return (
    <Card className={cn(
      'h-full border-slate-200 bg-white shadow-sm transition-all',
      selected ? 'border-primary shadow-lg shadow-primary/10' : 'hover:border-slate-300',
    )}
    >
      <CardHeader className="space-y-4">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-2">
            <CardTitle className="text-xl text-slate-950">{pkg.name}</CardTitle>
            <CardDescription className="text-sm leading-6 text-slate-600">{pkg.subtitle}</CardDescription>
          </div>
          {pkg.recommended ? (
            <Badge className="rounded-full bg-amber-500 text-white hover:bg-amber-500">
              <Sparkles className="mr-1 h-3.5 w-3.5" />
              Mais escolhido
            </Badge>
          ) : null}
        </div>
        <div className="space-y-1">
          <p className="text-3xl font-semibold text-slate-950">{pkg.priceLabel}</p>
          <p className="text-sm text-slate-600">{pkg.idealFor}</p>
        </div>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="space-y-3">
          {pkg.benefits.map((benefit) => (
            <div key={benefit} className="flex items-start gap-2 text-sm text-slate-700">
              <CheckCircle2 className="mt-0.5 h-4 w-4 text-emerald-600" />
              <span>{benefit}</span>
            </div>
          ))}
        </div>
        <Button className="w-full" onClick={() => onSelect(pkg)}>
          {selected ? 'Pacote selecionado' : 'Escolher este pacote'}
        </Button>
      </CardContent>
    </Card>
  );
}
