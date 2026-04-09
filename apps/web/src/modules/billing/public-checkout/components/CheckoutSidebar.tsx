import type { PublicCheckoutStep } from '../hooks/usePublicCheckoutWizard';
import { NextStepCard } from './NextStepCard';
import { OrderSummaryCard } from './OrderSummaryCard';
import { TrustSignalsCard } from './TrustSignalsCard';

type CheckoutSidebarProps = {
  currentStep: PublicCheckoutStep;
  selectedPackage?: {
    name: string;
    priceLabel: string;
    subtitle: string;
  } | null;
};

export function CheckoutSidebar({
  currentStep,
  selectedPackage,
}: CheckoutSidebarProps) {
  return (
    <aside className="space-y-4">
      <OrderSummaryCard pkg={selectedPackage} />
      <NextStepCard currentStep={currentStep} />
      <TrustSignalsCard />
    </aside>
  );
}
