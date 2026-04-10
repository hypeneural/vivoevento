import { ChevronUp, ShieldCheck } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Drawer,
  DrawerContent,
  DrawerDescription,
  DrawerHeader,
  DrawerTitle,
  DrawerTrigger,
} from '@/components/ui/drawer';

import type { PublicCheckoutStep } from '../hooks/usePublicCheckoutWizard';
import type { MobileCheckoutFooterSummary } from '../mappers/checkoutResponseAdapters';
import { CheckoutSidebar } from './CheckoutSidebar';

type MobileCheckoutFooterProps = {
  currentStep: PublicCheckoutStep;
  selectedPackage?: {
    name: string;
    priceLabel: string;
    subtitle: string;
  } | null;
  summary: MobileCheckoutFooterSummary;
  primaryActionLabel?: string | null;
  primaryActionDisabled?: boolean;
  onPrimaryAction?: (() => void) | null;
};

export function MobileCheckoutFooter({
  currentStep,
  selectedPackage,
  summary,
  primaryActionLabel,
  primaryActionDisabled = false,
  onPrimaryAction,
}: MobileCheckoutFooterProps) {
  if (currentStep === 'status') {
    return null;
  }

  return (
    <Drawer shouldScaleBackground={false}>
      <div
        data-testid="public-checkout-mobile-footer"
        className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-white/92 backdrop-blur-xl lg:hidden"
      >
        <div className="mx-auto flex w-full max-w-3xl items-center gap-3 px-4 py-4">
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold text-slate-950">{summary.title}</p>
            <p className="truncate text-xs text-slate-600">{summary.description}</p>
          </div>
          {primaryActionLabel && onPrimaryAction ? (
            <Button
              type="button"
              data-testid="public-checkout-mobile-primary-cta"
              className="shrink-0 rounded-2xl"
              disabled={primaryActionDisabled}
              onClick={onPrimaryAction}
            >
              {primaryActionLabel}
            </Button>
          ) : null}
          <DrawerTrigger asChild>
            <Button type="button" variant="outline" className="shrink-0 rounded-2xl">
              <ShieldCheck className="h-4 w-4 text-emerald-600" />
              Ver resumo
              <ChevronUp className="h-4 w-4" />
            </Button>
          </DrawerTrigger>
        </div>
      </div>

      <DrawerContent
        data-testid="public-checkout-mobile-drawer"
        className="max-h-[90vh] overflow-y-auto rounded-t-[28px]"
      >
        <DrawerHeader className="border-b border-slate-200 px-5 pb-4 text-left">
          <DrawerTitle>Resumo da compra</DrawerTitle>
          <DrawerDescription>
            Confira seu pacote, o proximo passo e os sinais de seguranca sem sair da jornada.
          </DrawerDescription>
        </DrawerHeader>
        <div className="space-y-4 px-4 py-4">
          <CheckoutSidebar currentStep={currentStep} selectedPackage={selectedPackage} />
        </div>
      </DrawerContent>
    </Drawer>
  );
}
