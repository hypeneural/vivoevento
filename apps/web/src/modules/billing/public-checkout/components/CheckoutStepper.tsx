import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Progress } from '@/components/ui/progress';

import type { PublicCheckoutStep } from '../hooks/usePublicCheckoutWizard';
import { StepSummaryRow } from './StepSummaryRow';

type StepItem = {
  key: 'package' | 'details' | 'payment';
  label: string;
  summary: string;
};

type CheckoutStepperProps = {
  currentStep: PublicCheckoutStep;
  progressValue: number;
  completedSteps: string[];
  steps: readonly StepItem[];
  childrenByStep: Record<string, React.ReactNode>;
};

export function CheckoutStepper({
  currentStep,
  progressValue,
  completedSteps,
  steps,
  childrenByStep,
}: CheckoutStepperProps) {
  if (currentStep === 'status') {
    return (
      <div className="space-y-6">
        <div className="space-y-2">
          <div className="flex items-center justify-between text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground">
            <span>Etapas</span>
            <span>{Math.round(progressValue)}%</span>
          </div>
          <Progress value={progressValue} className="h-2" />
        </div>

        <div className="space-y-3">
          {steps.map((step) => (
            <div key={step.key} className="rounded-2xl border border-slate-200 bg-white px-4 py-3">
              <StepSummaryRow
                label={step.label}
                summary={step.summary}
                completed
              />
            </div>
          ))}
        </div>

        {childrenByStep.payment}
      </div>
    );
  }

  const openValue = currentStep;

  return (
    <div className="space-y-6">
      <div className="space-y-2">
        <div className="flex items-center justify-between text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground">
          <span>Etapas</span>
          <span>{Math.round(progressValue)}%</span>
        </div>
        <Progress value={progressValue} className="h-2" />
      </div>

      <Accordion type="single" collapsible value={openValue}>
        {steps.map((step) => (
          <AccordionItem key={step.key} value={step.key}>
            <AccordionTrigger className="hover:no-underline">
              <StepSummaryRow
                label={step.label}
                summary={step.summary}
                active={openValue === step.key}
                completed={completedSteps.includes(step.key)}
              />
            </AccordionTrigger>
            <AccordionContent>
              {childrenByStep[step.key]}
            </AccordionContent>
          </AccordionItem>
        ))}
      </Accordion>
    </div>
  );
}
