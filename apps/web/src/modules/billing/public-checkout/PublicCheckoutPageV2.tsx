import { startTransition, useEffect, useMemo, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm, useWatch } from 'react-hook-form';
import { useNavigate, useSearchParams } from 'react-router-dom';

import { useAuth } from '@/app/providers/AuthProvider';
import { Form } from '@/components/ui/form';
import { ApiError, setToken } from '@/lib/api';
import { createPagarmeCardToken, PagarmeTokenizationError } from '@/lib/pagarme-tokenization';

import { publicEventCheckoutService } from '../services/public-event-checkout.service';
import { publicEventPackagesService } from '../services/public-event-packages.service';
import { BuyerEventStep } from './components/BuyerEventStep';
import { CheckoutHeroSimple } from './components/CheckoutHeroSimple';
import { CheckoutSidebar } from './components/CheckoutSidebar';
import { CheckoutStepper } from './components/CheckoutStepper';
import { MobileCheckoutFooter } from './components/MobileCheckoutFooter';
import { MobileSelectedPackageSummary } from './components/MobileSelectedPackageSummary';
import { PackageSelectionStep } from './components/PackageSelectionStep';
import { PaymentStatusCard } from './components/PaymentStatusCard';
import { PaymentStep } from './components/PaymentStep';
import { PublicCheckoutShell } from './components/PublicCheckoutShell';
import { useCheckoutIdentityPrecheck } from './hooks/useCheckoutIdentityPrecheck';
import { useCheckoutResumeDraft } from './hooks/useCheckoutResumeDraft';
import { useCheckoutStatusPolling } from './hooks/useCheckoutStatusPolling';
import { usePublicCheckoutWizard } from './hooks/usePublicCheckoutWizard';
import {
  buildCheckoutWizardSummaries,
  buildMobileCheckoutFooterSummary,
  resolveCheckoutSelectedPackage,
} from './mappers/checkoutResponseAdapters';
import { buildCheckoutStatusViewModel } from './mappers/checkoutStatusViewModel';
import { findCommercialPackageBySelectionKey, mapPackageToCommercialCard } from './mappers/packageCommercialCopy';
import { checkoutV2Schema, initialCheckoutV2Values, type CheckoutV2FormValues } from './support/checkoutFormSchema';
import {
  buildCheckoutPayload,
  buildV2CheckoutResumePath,
  buildV2LoginResumePath,
  digitsOnly,
  formatPhone,
  normalizeCardHolderName,
  PUBLIC_CHECKOUT_V2_AUTH_RESUME_VALUE,
  PUBLIC_CHECKOUT_V2_POLLING_INTERVAL_MS,
} from './support/checkoutFormUtils';

type ResumeNotice = {
  title: string;
  description: string;
  mode: 'auto' | 'manual';
};

type IdentityConflictState = {
  message: string;
  loginPath: string;
};

function findIdentityConflictMessage(error: ApiError): string | null {
  const candidates = [
    error.fieldError('whatsapp'),
    error.fieldError('email'),
    typeof error.body?.message === 'string' ? error.body.message : null,
  ].filter((value): value is string => Boolean(value));

  return candidates.find((message) => message.toLowerCase().includes('faca login para continuar')) ?? null;
}

export function PublicCheckoutPageV2() {
  const { isAuthenticated, refreshSession } = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [identityConflict, setIdentityConflict] = useState<IdentityConflictState | null>(null);
  const [resumeNotice, setResumeNotice] = useState<ResumeNotice | null>(null);
  const [resumeInitialized, setResumeInitialized] = useState(false);
  const [resumeAutoSubmitted, setResumeAutoSubmitted] = useState(false);
  const [nowMs, setNowMs] = useState(() => Date.now());
  const checkoutUuid = searchParams.get('checkout');
  const packageQuery = searchParams.get('package');
  const resumeMode = searchParams.get('resume');

  const form = useForm<CheckoutV2FormValues>({
    resolver: zodResolver(checkoutV2Schema),
    defaultValues: initialCheckoutV2Values,
    mode: 'onBlur',
    reValidateMode: 'onChange',
  });
  const { resumeDraft, writeDraft, clearDraft, restoreValues } = useCheckoutResumeDraft(initialCheckoutV2Values);

  const packagesQuery = useQuery({
    queryKey: ['public-event-packages', 'v2'],
    queryFn: () => publicEventPackagesService.list(),
    retry: false,
  });

  const packageId = useWatch({ control: form.control, name: 'package_id' });
  const responsibleName = useWatch({ control: form.control, name: 'responsible_name' });
  const whatsapp = useWatch({ control: form.control, name: 'whatsapp' });
  const email = useWatch({ control: form.control, name: 'email' });
  const eventTitle = useWatch({ control: form.control, name: 'event_title' });
  const paymentMethod = useWatch({ control: form.control, name: 'payment_method' });
  const payerPhone = useWatch({ control: form.control, name: 'payer_phone' });

  const commercialPackages = useMemo(
    () => (packagesQuery.data ?? []).map((pkg, index) => mapPackageToCommercialCard(pkg, index)),
    [packagesQuery.data],
  );
  const formSelectedPackage = commercialPackages.find((pkg) => String(pkg.id) === packageId) ?? null;
  const identityPrecheck = useCheckoutIdentityPrecheck({
    whatsapp: whatsapp ?? '',
    email: email ?? '',
  });

  const checkoutQuery = useCheckoutStatusPolling({
    checkoutUuid,
    pollingIntervalMs: PUBLIC_CHECKOUT_V2_POLLING_INTERVAL_MS,
  });
  const checkoutResponse = checkoutQuery.data ?? undefined;
  const statusViewModel = buildCheckoutStatusViewModel(checkoutResponse, nowMs);

  const selectedPackage = useMemo(() => resolveCheckoutSelectedPackage({
    formSelectedPackage,
    checkoutResponse,
  }), [checkoutResponse, formSelectedPackage]);

  const wizardSummaries = useMemo(() => buildCheckoutWizardSummaries({
    selectedPackage,
    responsibleName,
    eventTitle,
    checkoutResponse,
    statusViewModel,
  }), [checkoutResponse, eventTitle, responsibleName, selectedPackage, statusViewModel]);

  const wizard = usePublicCheckoutWizard({
    searchParams,
    setSearchParams: (next, options) => {
      startTransition(() => setSearchParams(next, options));
    },
    checkoutUuid,
    summaries: wizardSummaries,
  });

  const mobileFooterSummary = useMemo(() => buildMobileCheckoutFooterSummary({
    currentStep: wizard.currentStep,
    selectedPackage,
    statusViewModel,
  }), [selectedPackage, statusViewModel, wizard.currentStep]);
  const mobilePrimaryActionLabel = useMemo(() => {
    if (wizard.currentStep === 'details') {
      return 'Continuar para pagamento';
    }

    if (wizard.currentStep === 'payment') {
      return paymentMethod === 'credit_card' ? 'Finalizar com cartao' : 'Gerar meu Pix';
    }

    return null;
  }, [paymentMethod, wizard.currentStep]);

  function handleSelectPackage(pkg: (typeof commercialPackages)[number]) {
    form.setValue('package_id', String(pkg.id), { shouldDirty: true, shouldValidate: true });
    const nextParams = new URLSearchParams(searchParams);
    nextParams.set('package', pkg.deepLinkKey);
    nextParams.set('v2', '1');
    nextParams.set('step', 'details');

    startTransition(() => setSearchParams(nextParams, { replace: true }));
  }

  useEffect(() => {
    if (commercialPackages.length === 0) {
      return;
    }

    const deepLinkedPackage = findCommercialPackageBySelectionKey(commercialPackages, packageQuery);
    if (!deepLinkedPackage) {
      return;
    }

    if (String(deepLinkedPackage.id) !== packageId) {
      form.setValue('package_id', String(deepLinkedPackage.id), {
        shouldDirty: false,
        shouldTouch: false,
        shouldValidate: false,
      });
    }

    if (
      deepLinkedPackage
      && !searchParams.get('step')
      && !checkoutUuid
      && resumeMode !== PUBLIC_CHECKOUT_V2_AUTH_RESUME_VALUE
    ) {
      wizard.goToStep('details');
    }
  }, [checkoutUuid, commercialPackages, form, packageId, packageQuery, resumeMode, searchParams, wizard]);

  async function handleContinueToPayment() {
    const values = form.getValues();
    let hasError = false;

    if (!values.package_id.trim()) {
      form.setError('package_id', {
        type: 'manual',
        message: 'Escolha um pacote.',
      });
      hasError = true;
    }

    if (values.responsible_name.trim().length < 3) {
      form.setError('responsible_name', {
        type: 'manual',
        message: 'Informe seu nome.',
      });
      hasError = true;
    }

    if (digitsOnly(values.whatsapp).length < 10) {
      form.setError('whatsapp', {
        type: 'manual',
        message: 'Informe um WhatsApp com DDD.',
      });
      hasError = true;
    }

    if (values.event_title.trim().length < 3) {
      form.setError('event_title', {
        type: 'manual',
        message: 'Informe o nome do evento.',
      });
      hasError = true;
    }

    if (hasError) {
      return;
    }

    wizard.goToStep('payment');
  }

  function handleUseExistingAccount() {
    const values = form.getValues();
    const packageKey = formSelectedPackage?.deepLinkKey ?? packageQuery;

    writeDraft(values, 'manual_login');
    setIdentityConflict(null);
    setSubmitError(null);
    setResumeNotice(null);
    setResumeInitialized(false);
    setResumeAutoSubmitted(false);

    if (isAuthenticated) {
      navigate(buildV2CheckoutResumePath(packageKey), { replace: true });
      return;
    }

    navigate(buildV2LoginResumePath(packageKey));
  }

  useEffect(() => {
    if (paymentMethod !== 'credit_card') {
      return;
    }

    if (digitsOnly(payerPhone).length > 0) {
      return;
    }

    if (digitsOnly(whatsapp).length < 10) {
      return;
    }

    form.setValue('payer_phone', formatPhone(whatsapp), {
      shouldDirty: false,
      shouldTouch: false,
      shouldValidate: false,
    });
  }, [form, payerPhone, paymentMethod, whatsapp]);

  const createCheckoutMutation = useMutation({
    mutationFn: async (values: CheckoutV2FormValues) => {
      let cardToken: string | undefined;

      if (values.payment_method === 'credit_card') {
        const token = await createPagarmeCardToken({
          number: digitsOnly(values.card_number),
          holderName: normalizeCardHolderName(values.card_holder_name),
          expMonth: values.card_exp_month,
          expYear: values.card_exp_year,
          cvv: digitsOnly(values.card_cvv),
        });

        cardToken = token.id;
      }

      const response = await publicEventCheckoutService.create(buildCheckoutPayload(values, cardToken));

      if (response.token) {
        setToken(response.token);

        try {
          await refreshSession();
        } catch {
          // Keep checkout response visible even if session bootstrap fails transiently.
        }
      }

      return response;
    },
    onSuccess: (response) => {
      setSubmitError(null);
      setIdentityConflict(null);
      setResumeNotice(null);
      clearDraft();
      setResumeInitialized(false);
      setResumeAutoSubmitted(false);
      queryClient.setQueryData(['public-event-checkout-v2', response.checkout.uuid], response);

      const nextParams = new URLSearchParams(searchParams);
      nextParams.set('v2', '1');
      nextParams.set('checkout', response.checkout.uuid);
      nextParams.set('step', 'status');
      nextParams.delete('resume');

      startTransition(() => setSearchParams(nextParams, { replace: true }));
    },
    onError: (error, values) => {
      if (error instanceof ApiError) {
        const validationEntries = Object.entries(error.validationErrors ?? {});
        const conflictMessage = findIdentityConflictMessage(error);

        if (conflictMessage) {
          writeDraft(values);
          setIdentityConflict({
            message: conflictMessage,
            loginPath: buildV2LoginResumePath(formSelectedPackage?.deepLinkKey ?? packageQuery),
          });
          setSubmitError(null);
          return;
        }

        for (const [field, messages] of validationEntries) {
          if (messages[0]) {
            form.setError(field as never, {
              type: 'server',
              message: messages[0],
            });
          }
        }

        setIdentityConflict(null);
        setSubmitError(validationEntries[0]?.[1]?.[0] ?? error.message);
        return;
      }

      setIdentityConflict(null);
      setSubmitError(
        error instanceof PagarmeTokenizationError
          ? error.message
          : 'Nao foi possivel iniciar o checkout agora.',
      );
    },
  });

  useEffect(() => {
    if (resumeInitialized || resumeMode !== PUBLIC_CHECKOUT_V2_AUTH_RESUME_VALUE || !resumeDraft || checkoutUuid) {
      return;
    }

    const restoredValues = restoreValues(resumeDraft);

    form.reset(restoredValues);
    setIdentityConflict(null);
    setSubmitError(null);
    setResumeNotice(
      restoredValues.payment_method === 'credit_card'
        ? {
            title: 'Sessao retomada com a sua conta',
            description: 'Retomamos os dados seguros da sua jornada. Para sua seguranca, os campos do cartao precisam ser preenchidos novamente.',
            mode: 'manual',
          }
        : resumeDraft.source === 'identity_conflict'
          ? {
            title: 'Sessao retomada com a sua conta',
            description: 'Seu rascunho foi restaurado e o checkout Pix sera retomado automaticamente.',
            mode: 'auto',
          }
          : {
            title: 'Sessao retomada com a sua conta',
            description: 'Seus dados foram restaurados. Agora e so seguir para o pagamento.',
            mode: 'manual',
          },
    );
    setResumeInitialized(true);
    wizard.goToStep('payment');
  }, [checkoutUuid, form, resumeDraft, resumeInitialized, resumeMode, restoreValues, wizard]);

  useEffect(() => {
    if (
      !resumeInitialized
      || !isAuthenticated
      || !resumeDraft
      || resumeMode !== PUBLIC_CHECKOUT_V2_AUTH_RESUME_VALUE
      || checkoutUuid
      || resumeAutoSubmitted
      || createCheckoutMutation.isPending
      || resumeDraft.payment_method !== 'pix'
      || resumeDraft.source !== 'identity_conflict'
    ) {
      return;
    }

    setResumeAutoSubmitted(true);
    void createCheckoutMutation.mutateAsync(restoreValues(resumeDraft)).catch(() => undefined);
  }, [
    checkoutUuid,
    createCheckoutMutation,
    isAuthenticated,
    resumeAutoSubmitted,
    resumeDraft,
    resumeInitialized,
    resumeMode,
    restoreValues,
  ]);

  useEffect(() => {
    if (!statusViewModel.pixExpiresAt || statusViewModel.isTerminal || statusViewModel.paymentMethod !== 'pix') {
      return undefined;
    }

    const timer = window.setInterval(() => setNowMs(Date.now()), 1000);

    return () => window.clearInterval(timer);
  }, [statusViewModel.isTerminal, statusViewModel.paymentMethod, statusViewModel.pixExpiresAt]);

  async function handleSubmitCheckout() {
    setSubmitError(null);
    setIdentityConflict(null);
    form.clearErrors();

    const isValid = await form.trigger();

    if (!isValid) {
      return;
    }

    try {
      await createCheckoutMutation.mutateAsync(form.getValues());
    } catch {
      // handled by mutation callbacks
    }
  }

  const stepChildren: Record<string, React.ReactNode> = {
    package: packagesQuery.isLoading ? (
      <div className="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-sm text-slate-500">
        Carregando os pacotes do evento...
      </div>
    ) : (
      <PackageSelectionStep
        packages={commercialPackages}
        selectedPackageId={packageId}
        onSelect={(pkg) => handleSelectPackage(pkg)}
      />
    ),
    details: (
      <BuyerEventStep
        identityState={identityPrecheck.identityAssist}
        isCheckingIdentity={identityPrecheck.isChecking}
        onContinue={() => void handleContinueToPayment()}
        onUseExistingAccount={handleUseExistingAccount}
      />
    ),
    payment: wizard.currentStep === 'status'
      ? (
        <PaymentStatusCard
          status={statusViewModel}
          isFetching={checkoutQuery.isFetching}
          isAuthenticated={isAuthenticated}
          onRefresh={() => {
            void checkoutQuery.refetch();
          }}
        />
      )
      : (
        <PaymentStep
          selectedPackage={formSelectedPackage}
          isSubmitting={createCheckoutMutation.isPending}
          resumeNotice={resumeNotice}
          submitError={submitError}
          identityConflict={identityConflict}
          onBack={() => wizard.goBack()}
          onSubmit={() => void handleSubmitCheckout()}
        />
      ),
  };

  return (
    <Form {...form}>
      <PublicCheckoutShell
        hero={<CheckoutHeroSimple />}
        main={(
          <>
            <MobileSelectedPackageSummary
              currentStep={wizard.currentStep}
              selectedPackage={selectedPackage}
            />
            <CheckoutStepper
              currentStep={wizard.currentStep}
              progressValue={wizard.progressValue}
              completedSteps={wizard.completedSteps}
              steps={wizard.stepMeta}
              childrenByStep={stepChildren}
            />
          </>
        )}
        sidebar={(
          <CheckoutSidebar
            currentStep={wizard.currentStep}
            selectedPackage={selectedPackage}
          />
        )}
        mobileFooter={(
          <MobileCheckoutFooter
            currentStep={wizard.currentStep}
            selectedPackage={selectedPackage}
            summary={mobileFooterSummary}
            primaryActionLabel={mobilePrimaryActionLabel}
            primaryActionDisabled={createCheckoutMutation.isPending}
            onPrimaryAction={wizard.currentStep === 'details'
              ? () => void handleContinueToPayment()
              : wizard.currentStep === 'payment'
                ? () => void handleSubmitCheckout()
                : null}
          />
        )}
      />
    </Form>
  );
}
