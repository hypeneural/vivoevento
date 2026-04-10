import { useIsMobile } from '@/hooks/use-mobile';

import type { PublicCheckoutStep } from '../hooks/usePublicCheckoutWizard';
import type { CheckoutSelectedPackageSummary } from '../mappers/checkoutResponseAdapters';

type MobileSelectedPackageSummaryProps = {
  currentStep: PublicCheckoutStep;
  selectedPackage?: CheckoutSelectedPackageSummary | null;
};

export function MobileSelectedPackageSummary({
  currentStep,
  selectedPackage,
}: MobileSelectedPackageSummaryProps) {
  const isMobile = useIsMobile();

  if (!isMobile || !selectedPackage || currentStep === 'package' || currentStep === 'status') {
    return null;
  }

  return (
    <section
      data-testid="public-checkout-mobile-package-summary"
      className="mb-4 rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm"
      aria-label="Resumo compacto do pacote escolhido"
    >
      <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700">
        Pacote escolhido
      </p>
      <div className="mt-1 flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="truncate text-sm font-semibold text-slate-950">
            {selectedPackage.name}
          </h2>
          <p className="mt-0.5 line-clamp-2 text-xs leading-5 text-slate-500">
            {selectedPackage.subtitle}
          </p>
        </div>
        <p className="shrink-0 text-sm font-semibold text-slate-950">
          {selectedPackage.priceLabel}
        </p>
      </div>
    </section>
  );
}
