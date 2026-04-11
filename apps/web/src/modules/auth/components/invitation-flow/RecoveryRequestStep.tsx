import type { KeyboardEventHandler, ReactNode } from 'react';
import { ArrowLeft, KeyRound, Loader2, Phone } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type RecoveryRequestStepProps = {
  title?: string;
  description: string;
  backLabel?: string;
  onBack: () => void;
  identityLabel?: string;
  identityInputId?: string;
  identityAriaLabel?: string;
  identityValue: string;
  onIdentityChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
  onIdentityKeyDown?: KeyboardEventHandler<HTMLInputElement>;
  identityPlaceholder?: string;
  helper?: ReactNode;
  submitLabel: string;
  submitLoadingLabel?: string;
  onSubmit: () => void;
  submitDisabled?: boolean;
  isSubmitting?: boolean;
};

export function RecoveryRequestStep({
  title = 'Esqueci a senha',
  description,
  backLabel = 'Voltar ao login',
  onBack,
  identityLabel = 'WhatsApp ou E-mail',
  identityInputId,
  identityAriaLabel = 'WhatsApp ou E-mail',
  identityValue,
  onIdentityChange,
  onIdentityKeyDown,
  identityPlaceholder = '(51) 99999-9999 ou seu@email.com',
  helper,
  submitLabel,
  submitLoadingLabel = submitLabel,
  onSubmit,
  submitDisabled = false,
  isSubmitting = false,
}: RecoveryRequestStepProps) {
  return (
    <div className="space-y-4 sm:space-y-5">
      <div className="space-y-1.5">
        <button
          type="button"
          onClick={onBack}
          className="mb-2 -ml-0.5 flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft className="h-3 w-3" />
          {backLabel}
        </button>
        <div className="flex items-center gap-2">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10">
            <KeyRound className="h-4.5 w-4.5 text-primary" />
          </div>
          <div>
            <h2 className="text-lg font-semibold sm:text-xl">{title}</h2>
          </div>
        </div>
        <p className="text-sm text-muted-foreground">{description}</p>
      </div>

      <div className="space-y-3 sm:space-y-4">
        <div>
          <label
            htmlFor={identityInputId}
            className="mb-1 block text-xs font-medium text-muted-foreground sm:mb-1.5 sm:text-sm"
          >
            {identityLabel}
          </label>
          <div className="relative">
            <Phone className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id={identityInputId}
              aria-label={identityAriaLabel}
              type="text"
              value={identityValue}
              onChange={onIdentityChange}
              onKeyDown={onIdentityKeyDown}
              placeholder={identityPlaceholder}
              className="h-10 pl-9 text-sm sm:h-11 sm:text-base"
              autoFocus
            />
          </div>
        </div>

        {helper}

        <Button
          className="h-10 w-full border-0 text-sm font-medium sm:h-11 sm:text-base gradient-primary"
          onClick={onSubmit}
          disabled={isSubmitting || submitDisabled}
        >
          {isSubmitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
          {isSubmitting ? submitLoadingLabel : submitLabel}
        </Button>
      </div>
    </div>
  );
}
