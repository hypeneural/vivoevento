import type { PublicEventCheckoutResponse } from '@/lib/api-types';

import type { PublicCheckoutStep, PublicCheckoutWizardSummary } from '../hooks/usePublicCheckoutWizard';
import { formatCurrency } from '../support/checkoutFormUtils';
import type { PublicCheckoutStatusViewModel } from './checkoutStatusViewModel';
import type { CommercialPackageCopy } from './packageCommercialCopy';

export type CheckoutSelectedPackageSummary = {
  name: string;
  priceLabel: string;
  subtitle: string;
};

export type MobileCheckoutFooterSummary = {
  title: string;
  description: string;
};

type ResolveCheckoutSelectedPackageOptions = {
  formSelectedPackage?: CommercialPackageCopy | null;
  checkoutResponse?: PublicEventCheckoutResponse;
};

type BuildCheckoutWizardSummariesOptions = {
  selectedPackage?: CheckoutSelectedPackageSummary | null;
  responsibleName?: string | null;
  eventTitle?: string | null;
  checkoutResponse?: PublicEventCheckoutResponse;
  statusViewModel: Pick<PublicCheckoutStatusViewModel, 'statusLabel'>;
};

type BuildMobileCheckoutFooterSummaryOptions = {
  currentStep: PublicCheckoutStep;
  selectedPackage?: CheckoutSelectedPackageSummary | null;
  statusViewModel: Pick<PublicCheckoutStatusViewModel, 'title' | 'statusLabel'>;
};

export function resolveCheckoutSelectedPackage({
  formSelectedPackage,
  checkoutResponse,
}: ResolveCheckoutSelectedPackageOptions): CheckoutSelectedPackageSummary | null {
  if (formSelectedPackage) {
    return {
      name: formSelectedPackage.name,
      priceLabel: formSelectedPackage.priceLabel,
      subtitle: formSelectedPackage.subtitle,
    };
  }

  if (!checkoutResponse?.checkout.package) {
    return null;
  }

  return {
    name: checkoutResponse.checkout.package.name,
    priceLabel: formatCurrency(checkoutResponse.checkout.total_cents, checkoutResponse.checkout.currency),
    subtitle: checkoutResponse.checkout.package.description || 'Pacote contratado para este evento.',
  };
}

export function buildCheckoutWizardSummaries({
  selectedPackage,
  responsibleName,
  eventTitle,
  checkoutResponse,
  statusViewModel,
}: BuildCheckoutWizardSummariesOptions): PublicCheckoutWizardSummary {
  return {
    package: selectedPackage ? `${selectedPackage.name} selecionado.` : undefined,
    details: responsibleName && eventTitle
      ? `${responsibleName} - ${eventTitle}`
      : undefined,
    payment: checkoutResponse
      ? statusViewModel.statusLabel
      : 'Escolha Pix ou cartao para concluir a compra.',
  };
}

export function buildMobileCheckoutFooterSummary({
  currentStep,
  selectedPackage,
  statusViewModel,
}: BuildMobileCheckoutFooterSummaryOptions): MobileCheckoutFooterSummary {
  if (currentStep === 'status') {
    return {
      title: statusViewModel.title,
      description: statusViewModel.statusLabel,
    };
  }

  if (!selectedPackage) {
    return {
      title: 'Sua compra em poucos passos',
      description: currentStep === 'package'
        ? 'Escolha um pacote para ver o resumo.'
        : 'Veja o resumo da jornada antes de continuar.',
    };
  }

  switch (currentStep) {
    case 'details':
      return {
        title: `${selectedPackage.name} • ${selectedPackage.priceLabel}`,
        description: 'Falta contar rapidinho sobre voce e seu evento.',
      };
    case 'payment':
      return {
        title: `${selectedPackage.name} • ${selectedPackage.priceLabel}`,
        description: 'Pix rapido ou cartao com confirmacao automatica.',
      };
    case 'package':
    default:
      return {
        title: `${selectedPackage.name} • ${selectedPackage.priceLabel}`,
        description: selectedPackage.subtitle,
      };
  }
}
