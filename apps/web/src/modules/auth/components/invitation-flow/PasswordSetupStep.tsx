import type { KeyboardEventHandler } from 'react';
import { ArrowLeft, Eye, EyeOff, Loader2, Lock } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type PasswordStrength = {
  level: number;
  label: string;
  color?: string;
};

type PasswordSetupStepProps = {
  title: string;
  description: string;
  backLabel?: string;
  onBack: () => void;
  passwordLabel?: string;
  passwordInputId?: string;
  passwordAriaLabel?: string;
  passwordPlaceholder?: string;
  password: string;
  onPasswordChange: (value: string) => void;
  confirmationLabel?: string;
  confirmationInputId?: string;
  confirmationAriaLabel?: string;
  confirmationPlaceholder?: string;
  confirmation: string;
  onConfirmationChange: (value: string) => void;
  onConfirmationKeyDown?: KeyboardEventHandler<HTMLInputElement>;
  showPassword: boolean;
  onToggleShowPassword: () => void;
  strength?: PasswordStrength;
  mismatchText?: string;
  submitLabel: string;
  submitLoadingLabel?: string;
  onSubmit: () => void;
  submitDisabled?: boolean;
  isSubmitting?: boolean;
  buttonClassName?: string;
};

export function PasswordSetupStep({
  title,
  description,
  backLabel = 'Voltar',
  onBack,
  passwordLabel = 'Nova senha',
  passwordInputId,
  passwordAriaLabel = 'Nova senha',
  passwordPlaceholder = 'Minimo 8 caracteres',
  password,
  onPasswordChange,
  confirmationLabel = 'Confirmar nova senha',
  confirmationInputId,
  confirmationAriaLabel = 'Confirmar nova senha',
  confirmationPlaceholder = 'Repita a nova senha',
  confirmation,
  onConfirmationChange,
  onConfirmationKeyDown,
  showPassword,
  onToggleShowPassword,
  strength,
  mismatchText = 'As senhas nao conferem',
  submitLabel,
  submitLoadingLabel = submitLabel,
  onSubmit,
  submitDisabled = false,
  isSubmitting = false,
  buttonClassName = 'w-full h-10 sm:h-11 gradient-primary border-0 text-sm sm:text-base font-medium',
}: PasswordSetupStepProps) {
  const hasMismatch = confirmation.length > 0 && confirmation !== password;

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
      </div>

      <div className="space-y-3 sm:space-y-4">
        <div>
          <label className="mb-1 block text-xs font-medium text-muted-foreground sm:mb-1.5 sm:text-sm">
            {passwordLabel}
          </label>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id={passwordInputId}
              aria-label={passwordAriaLabel}
              type={showPassword ? 'text' : 'password'}
              value={password}
              onChange={(event) => onPasswordChange(event.target.value)}
              placeholder={passwordPlaceholder}
              className="h-10 pl-9 pr-10 text-sm sm:h-11 sm:text-base"
              autoComplete="new-password"
              autoFocus
            />
            <button
              type="button"
              onClick={onToggleShowPassword}
              className="absolute right-3 top-1/2 p-0.5 text-muted-foreground transition-colors hover:text-foreground"
              tabIndex={-1}
            >
              {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>

        {password && strength ? (
          <div className="space-y-1.5">
            <div className="flex gap-1">
              {[1, 2, 3, 4].map((level) => (
                <div
                  key={level}
                  className="h-1 flex-1 rounded-full transition-colors duration-300"
                  style={{
                    background:
                      strength.level >= level ? strength.color : 'hsl(var(--muted))',
                  }}
                />
              ))}
            </div>
            <p className="text-[10px] text-muted-foreground">{strength.label}</p>
          </div>
        ) : null}

        <div>
          <label className="mb-1 block text-xs font-medium text-muted-foreground sm:mb-1.5 sm:text-sm">
            {confirmationLabel}
          </label>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id={confirmationInputId}
              aria-label={confirmationAriaLabel}
              type={showPassword ? 'text' : 'password'}
              value={confirmation}
              onChange={(event) => onConfirmationChange(event.target.value)}
              onKeyDown={onConfirmationKeyDown}
              placeholder={confirmationPlaceholder}
              className="h-10 pl-9 text-sm sm:h-11 sm:text-base"
              autoComplete="new-password"
            />
          </div>
          {hasMismatch ? (
            <p className="mt-1 text-[10px] text-destructive">{mismatchText}</p>
          ) : null}
        </div>

        <Button
          className={buttonClassName}
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
