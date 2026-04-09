import { useFormContext, useWatch } from 'react-hook-form';

import { Button } from '@/components/ui/button';

import type { CheckoutV2FormValues } from '../support/checkoutFormSchema';
import type { CommercialPackageCopy } from '../mappers/packageCommercialCopy';
import { CheckoutErrorBanner } from './CheckoutErrorBanner';
import { CreditCardPaymentPanel } from './CreditCardPaymentPanel';
import { PaymentMethodTabs } from './PaymentMethodTabs';
import { PixPaymentPanel } from './PixPaymentPanel';
import { ResumeNoticeBanner } from './ResumeNoticeBanner';

type PaymentStepProps = {
  selectedPackage?: CommercialPackageCopy | null;
  isSubmitting?: boolean;
  resumeNotice?: {
    title: string;
    description: string;
    mode: 'auto' | 'manual';
  } | null;
  submitError?: string | null;
  identityConflict?: {
    message: string;
    loginPath: string;
  } | null;
  onBack: () => void;
  onSubmit: () => void;
};

export function PaymentStep({
  selectedPackage,
  isSubmitting = false,
  resumeNotice,
  submitError,
  identityConflict,
  onBack,
  onSubmit,
}: PaymentStepProps) {
  const form = useFormContext<CheckoutV2FormValues>();
  const paymentMethod = useWatch({
    control: form.control,
    name: 'payment_method',
  });

  return (
    <div className="space-y-6">
      {resumeNotice ? (
        <ResumeNoticeBanner
          title={resumeNotice.title}
          description={resumeNotice.description}
          mode={resumeNotice.mode}
          loading={resumeNotice.mode === 'auto' && isSubmitting}
        />
      ) : null}

      {identityConflict ? (
        <CheckoutErrorBanner
          title="Ja encontramos um cadastro compativel"
          description={identityConflict.message}
          actionLabel="Entrar para continuar"
          actionHref={identityConflict.loginPath}
        />
      ) : null}

      {submitError ? (
        <CheckoutErrorBanner
          title="Nao foi possivel continuar agora"
          description={submitError}
        />
      ) : null}

      <div className="space-y-4">
        <div className="space-y-2">
          <h2 className="text-xl font-semibold text-slate-950">Pagamento seguro</h2>
          <p className="text-sm leading-6 text-slate-600">
            Escolha como prefere pagar. O Pix continua sendo o caminho mais rapido.
          </p>
        </div>

        <PaymentMethodTabs
          value={paymentMethod}
          onValueChange={(nextValue) => form.setValue('payment_method', nextValue, { shouldDirty: true, shouldValidate: true })}
        />

        {paymentMethod === 'credit_card' ? (
          <CreditCardPaymentPanel />
        ) : (
          <PixPaymentPanel amountLabel={selectedPackage?.priceLabel ?? null} />
        )}
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button variant="outline" onClick={onBack}>
          Voltar para seus dados
        </Button>
        <Button onClick={onSubmit} disabled={isSubmitting}>
          {isSubmitting
            ? 'Processando...'
            : paymentMethod === 'credit_card'
              ? 'Finalizar com cartao'
              : 'Gerar meu Pix'}
        </Button>
      </div>
    </div>
  );
}
