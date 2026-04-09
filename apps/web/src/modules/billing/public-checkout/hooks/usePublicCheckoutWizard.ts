import { useMemo } from 'react';

export type PublicCheckoutStep = 'package' | 'details' | 'payment' | 'status';

export type PublicCheckoutWizardSummary = Partial<Record<Exclude<PublicCheckoutStep, 'status'>, string>>;

const STEP_ORDER: PublicCheckoutStep[] = ['package', 'details', 'payment', 'status'];

function normalizeStep(step: string | null, hasCheckout = false): PublicCheckoutStep {
  if (step === 'status' && hasCheckout) {
    return 'status';
  }

  if (step === 'payment' || step === 'details' || step === 'package') {
    return step;
  }

  return 'package';
}

type UsePublicCheckoutWizardOptions = {
  searchParams: URLSearchParams;
  setSearchParams: (nextInit: URLSearchParams, navigateOptions?: { replace?: boolean }) => void;
  checkoutUuid?: string | null;
  summaries?: PublicCheckoutWizardSummary;
};

export function usePublicCheckoutWizard({
  searchParams,
  setSearchParams,
  checkoutUuid,
  summaries = {},
}: UsePublicCheckoutWizardOptions) {
  const currentStep = normalizeStep(searchParams.get('step'), Boolean(checkoutUuid));
  const currentIndex = STEP_ORDER.indexOf(currentStep);
  const completedSteps = STEP_ORDER.slice(0, currentIndex);
  const progressValue = STEP_ORDER.length <= 1
    ? 0
    : (currentIndex / (STEP_ORDER.length - 1)) * 100;

  const stepMeta = useMemo(() => ([
    {
      key: 'package',
      label: 'Pacote',
      summary: summaries.package ?? 'Escolha o pacote ideal para o seu evento.',
    },
    {
      key: 'details',
      label: 'Seus dados',
      summary: summaries.details ?? 'Conte rapidinho sobre voce e seu evento.',
    },
    {
      key: 'payment',
      label: 'Pagamento',
      summary: summaries.payment ?? 'Escolha Pix ou cartao e finalize com seguranca.',
    },
  ] as const), [summaries.details, summaries.package, summaries.payment]);

  function updateStep(nextStep: PublicCheckoutStep) {
    const nextParams = new URLSearchParams(searchParams);
    nextParams.set('v2', '1');
    nextParams.set('step', nextStep);

    if (checkoutUuid) {
      nextParams.set('checkout', checkoutUuid);
    }

    setSearchParams(nextParams, { replace: true });
  }

  function goBack() {
    const backStep = STEP_ORDER[Math.max(0, currentIndex - 1)] ?? 'package';
    updateStep(backStep);
  }

  return {
    currentStep,
    currentIndex,
    completedSteps,
    progressValue,
    stepMeta,
    goToStep: updateStep,
    goBack,
  };
}
