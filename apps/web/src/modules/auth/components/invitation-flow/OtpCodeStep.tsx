import type { CSSProperties, ReactNode } from 'react';
import { ArrowLeft, Loader2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';

type OtpCodeStepProps = {
  title: string;
  description: string;
  secondaryDescription?: string;
  backLabel?: string;
  onBack: () => void;
  helper?: ReactNode;
  codeLabel?: string;
  value: string;
  onChange: (value: string) => void;
  inputVariant?: 'slots' | 'field';
  inputId?: string;
  inputAriaLabel?: string;
  inputPlaceholder?: string;
  submitLabel: string;
  submitLoadingLabel?: string;
  onSubmit: () => void;
  submitDisabled?: boolean;
  isSubmitting?: boolean;
  buttonClassName?: string;
  buttonStyle?: CSSProperties;
  resendLayout?: 'inline' | 'stacked';
  resendPrompt?: string;
  resendCountdownPrefix?: string;
  resendCountdown?: number;
  formatCountdown?: (seconds: number) => string;
  onResend?: () => void;
  resendButtonLabel?: string;
  resendLoadingLabel?: string;
};

export function OtpCodeStep({
  title,
  description,
  secondaryDescription,
  backLabel = 'Voltar',
  onBack,
  helper,
  codeLabel = 'Codigo de verificacao',
  value,
  onChange,
  inputVariant = 'field',
  inputId,
  inputAriaLabel = 'Codigo de verificacao',
  inputPlaceholder = '000000',
  submitLabel,
  submitLoadingLabel = submitLabel,
  onSubmit,
  submitDisabled = false,
  isSubmitting = false,
  buttonClassName = 'w-full h-10 sm:h-11 gradient-primary border-0 text-sm sm:text-base font-medium',
  buttonStyle,
  resendLayout = 'stacked',
  resendPrompt = 'Nao recebeu?',
  resendCountdownPrefix = 'Novo envio em',
  resendCountdown = 0,
  formatCountdown,
  onResend,
  resendButtonLabel = 'Reenviar codigo',
  resendLoadingLabel = 'Reenviando...',
}: OtpCodeStepProps) {
  const countdownLabel =
    resendCountdown > 0 && formatCountdown ? formatCountdown(resendCountdown) : null;

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
        <h2 className="text-lg font-semibold sm:text-xl">{title}</h2>
        <p className="text-sm text-muted-foreground">{description}</p>
        {secondaryDescription ? (
          <p className="text-xs text-muted-foreground/70">{secondaryDescription}</p>
        ) : null}
      </div>

      <div className="space-y-3 sm:space-y-4">
        {helper}

        <div>
          <label className="mb-1.5 block text-xs font-medium text-muted-foreground sm:text-sm">
            {codeLabel}
          </label>
          {inputVariant === 'slots' ? (
            <InputOTP
              maxLength={6}
              value={value}
              onChange={(nextValue) => onChange(nextValue.replace(/\D/g, '').slice(0, 6))}
              containerClassName="justify-center"
            >
              <InputOTPGroup>
                {[0, 1, 2, 3, 4, 5].map((index) => (
                  <InputOTPSlot
                    key={index}
                    index={index}
                    className="h-11 w-11 sm:h-12 sm:w-12"
                  />
                ))}
              </InputOTPGroup>
            </InputOTP>
          ) : (
            <Input
              id={inputId}
              aria-label={inputAriaLabel}
              type="text"
              inputMode="numeric"
              maxLength={6}
              value={value}
              onChange={(event) => onChange(event.target.value.replace(/\D/g, '').slice(0, 6))}
              placeholder={inputPlaceholder}
              className="h-12 text-center text-xl font-bold tracking-[0.5em] sm:h-14 sm:text-2xl"
              autoFocus
            />
          )}
        </div>

        <Button
          className={buttonClassName}
          style={buttonStyle}
          onClick={onSubmit}
          disabled={isSubmitting || submitDisabled}
        >
          {isSubmitting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
          {isSubmitting ? submitLoadingLabel : submitLabel}
        </Button>
      </div>

      {onResend ? (
        resendLayout === 'inline' ? (
          <div className="flex items-center justify-between text-xs text-muted-foreground/70">
            <span>{resendPrompt}</span>
            {countdownLabel ? (
              <span className="font-mono">{countdownLabel}</span>
            ) : (
              <button
                type="button"
                onClick={onResend}
                disabled={isSubmitting}
                className="font-medium text-primary hover:underline disabled:cursor-not-allowed disabled:opacity-60"
              >
                {isSubmitting ? resendLoadingLabel : resendButtonLabel}
              </button>
            )}
          </div>
        ) : (
          <div className="text-center">
            {countdownLabel ? (
              <p className="mb-2 font-mono text-[11px] text-muted-foreground/70">
                {resendCountdownPrefix} {countdownLabel}
              </p>
            ) : null}
            <button
              type="button"
              onClick={onResend}
              className="text-xs text-muted-foreground transition-colors hover:text-primary disabled:cursor-not-allowed disabled:opacity-60"
              disabled={isSubmitting || resendCountdown > 0}
            >
              {isSubmitting ? resendLoadingLabel : resendButtonLabel}
            </button>
          </div>
        )
      ) : null}
    </div>
  );
}
