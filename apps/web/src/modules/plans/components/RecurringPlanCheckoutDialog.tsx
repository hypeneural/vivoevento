import { useEffect, useMemo, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { Building2, CreditCard, Loader2, ShieldCheck } from 'lucide-react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { createPagarmeCardToken, PagarmeTokenizationError } from '@/lib/pagarme-tokenization';
import type { ApiBillingSubscription, ApiPlan, ApiPlanPrice } from '@/lib/api-types';
import {
  digitsOnly,
  formatCardExpiryPart,
  formatCardNumber,
  formatPhone,
  formatZipCode,
  hasTwoWords,
  isValidCardExpiry,
  isValidCardNumber,
  normalizeCardHolderName,
  normalizeStateCode,
} from '@/modules/billing/public-checkout/support/checkoutFormUtils';

import type { BillingCheckoutPayload } from '../api';

const planCheckoutSchema = z.object({
  payer_name: z.string().trim().min(3, 'Informe o nome do pagador.'),
  payer_email: z.string().trim().email('Informe um e-mail valido.'),
  payer_document: z.string().trim().min(11, 'Informe CPF ou CNPJ do pagador.'),
  payer_phone: z.string().trim().min(10, 'Informe um telefone com DDD.'),
  address_street: z.string().trim().min(3, 'Informe a rua de cobranca.'),
  address_number: z.string().trim().min(1, 'Informe o numero do endereco.'),
  address_district: z.string().trim().min(2, 'Informe o bairro.'),
  address_complement: z.string().trim().optional().default(''),
  address_zip_code: z.string().trim().min(8, 'Informe o CEP.'),
  address_city: z.string().trim().min(2, 'Informe a cidade de cobranca.'),
  address_state: z.string().trim().min(2, 'Informe a UF do endereco.'),
  card_number: z.string().trim().min(13, 'Informe o numero do cartao.'),
  card_holder_name: z.string().trim().min(3, 'Informe o nome do titular.'),
  card_exp_month: z.string().trim().min(2, 'Informe o mes da validade.'),
  card_exp_year: z.string().trim().min(2, 'Informe o ano da validade.'),
  card_cvv: z.string().trim().min(3, 'Informe o CVV.'),
}).superRefine((values, ctx) => {
  const documentDigits = digitsOnly(values.payer_document);

  if (![11, 14].includes(documentDigits.length)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Use um CPF ou CNPJ valido.',
      path: ['payer_document'],
    });
  }

  if (digitsOnly(values.payer_phone).length < 10) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe um telefone com DDD.',
      path: ['payer_phone'],
    });
  }

  if (digitsOnly(values.address_zip_code).length !== 8) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'O CEP deve ter 8 digitos.',
      path: ['address_zip_code'],
    });
  }

  if (normalizeStateCode(values.address_state).length !== 2) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Use a UF com 2 letras.',
      path: ['address_state'],
    });
  }

  if (!hasTwoWords(values.card_holder_name)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe nome e sobrenome do titular.',
      path: ['card_holder_name'],
    });
  }

  if (!isValidCardNumber(values.card_number)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe um numero de cartao valido.',
      path: ['card_number'],
    });
  }

  if (!isValidCardExpiry(values.card_exp_month, values.card_exp_year)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Informe uma validade vigente para o cartao.',
      path: ['card_exp_year'],
    });
  }

  if (![3, 4].includes(digitsOnly(values.card_cvv).length)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'O CVV deve ter 3 ou 4 digitos.',
      path: ['card_cvv'],
    });
  }
});

type PlanCheckoutFormValues = z.infer<typeof planCheckoutSchema>;

function formatMoney(amountCents: number, currency = 'BRL') {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency,
  }).format(amountCents / 100);
}

function formatCycle(cycle?: string | null) {
  return cycle === 'yearly' ? 'anual' : 'mensal';
}

function formatDocument(value: string) {
  const digits = digitsOnly(value).slice(0, 14);

  if (digits.length <= 11) {
    return digits
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
  }

  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

function buildDefaultValues(
  organizationName?: string | null,
  userName?: string | null,
  userEmail?: string | null,
): PlanCheckoutFormValues {
  return {
    payer_name: organizationName?.trim() || userName?.trim() || '',
    payer_email: userEmail?.trim() || '',
    payer_document: '',
    payer_phone: '',
    address_street: '',
    address_number: '',
    address_district: '',
    address_complement: '',
    address_zip_code: '',
    address_city: '',
    address_state: '',
    card_number: '',
    card_holder_name: userName?.trim().toUpperCase() || '',
    card_exp_month: '',
    card_exp_year: '',
    card_cvv: '',
  };
}

export interface RecurringPlanCheckoutDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  plan: ApiPlan | null;
  price: ApiPlanPrice | null;
  organizationName?: string | null;
  userName?: string | null;
  userEmail?: string | null;
  currentSubscription?: ApiBillingSubscription | null;
  isSubmitting?: boolean;
  onSubmit: (payload: BillingCheckoutPayload) => Promise<unknown>;
}

export function RecurringPlanCheckoutDialog({
  open,
  onOpenChange,
  plan,
  price,
  organizationName,
  userName,
  userEmail,
  currentSubscription,
  isSubmitting = false,
  onSubmit,
}: RecurringPlanCheckoutDialogProps) {
  const [tokenizationError, setTokenizationError] = useState<string | null>(null);
  const [isTokenizing, setIsTokenizing] = useState(false);

  const defaultValues = useMemo(
    () => buildDefaultValues(organizationName, userName, userEmail),
    [organizationName, userEmail, userName],
  );

  const form = useForm<PlanCheckoutFormValues>({
    resolver: zodResolver(planCheckoutSchema),
    defaultValues,
  });

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset(defaultValues);
    setTokenizationError(null);
  }, [defaultValues, form, open, plan?.id, price?.id]);

  const isBusy = isSubmitting || isTokenizing;
  const targetBillingCycle = price?.billing_cycle === 'yearly' ? 'yearly' : 'monthly';
  const isPlanSwitch = Boolean(
    plan
      && currentSubscription
      && (
        currentSubscription.plan_key !== plan.code
        || currentSubscription.billing_cycle !== targetBillingCycle
      ),
  );

  const handleSubmit = form.handleSubmit(async (values) => {
    if (!plan || !price) {
      return;
    }

    setTokenizationError(null);
    setIsTokenizing(true);

    try {
      const token = await createPagarmeCardToken({
        number: digitsOnly(values.card_number),
        holderName: values.card_holder_name.trim(),
        expMonth: digitsOnly(values.card_exp_month),
        expYear: digitsOnly(values.card_exp_year),
        cvv: digitsOnly(values.card_cvv),
      });

      await onSubmit({
        plan_id: plan.id,
        billing_cycle: price.billing_cycle === 'yearly' ? 'yearly' : 'monthly',
        payment_method: 'credit_card',
        payer: {
          name: values.payer_name.trim(),
          email: values.payer_email.trim(),
          document: digitsOnly(values.payer_document),
          phone: digitsOnly(values.payer_phone),
          address: {
            street: values.address_street.trim(),
            number: values.address_number.trim(),
            district: values.address_district.trim(),
            complement: values.address_complement.trim() || undefined,
            zip_code: digitsOnly(values.address_zip_code),
            city: values.address_city.trim(),
            state: normalizeStateCode(values.address_state),
            country: 'BR',
          },
        },
        credit_card: {
          card_token: token.id,
        },
      });

      onOpenChange(false);
    } catch (error) {
      if (error instanceof PagarmeTokenizationError) {
        setTokenizationError(error.message);
      } else if (error instanceof Error) {
        setTokenizationError(error.message);
      } else {
        setTokenizationError('Nao foi possivel tokenizar o cartao agora.');
      }
    } finally {
      setIsTokenizing(false);
    }
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] max-w-4xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Contratar plano recorrente</DialogTitle>
          <DialogDescription>
            Revise os dados da cobranca da conta e confirme. O cartao so e validado no momento final da contratacao.
          </DialogDescription>
        </DialogHeader>

        {plan && price ? (
          <div className="grid gap-4 rounded-3xl border border-border/60 bg-muted/20 p-5 md:grid-cols-[1.2fr_0.8fr]">
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                  {formatCycle(price.billing_cycle)}
                </Badge>
                <Badge variant="outline" className="border-border/60 bg-background/50">
                  <Building2 className="mr-1 h-3.5 w-3.5" />
                  {organizationName || 'Conta atual'}
                </Badge>
              </div>
              <h3 className="text-lg font-semibold text-foreground">{plan.name}</h3>
              <p className="text-sm text-muted-foreground">
                {plan.description || 'Plano recorrente para a operacao mensal da conta.'}
              </p>
            </div>

            <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
              <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Valor contratado</p>
              <p className="mt-2 text-3xl font-semibold text-foreground">
                {formatMoney(price.amount_cents, price.currency)}
              </p>
              <p className="mt-1 text-sm text-muted-foreground">
                cobranca {formatCycle(price.billing_cycle)}
              </p>
            </div>
          </div>
        ) : null}

        {isPlanSwitch && plan ? (
          <div className="rounded-3xl border border-amber-500/20 bg-amber-500/5 p-4 text-sm text-muted-foreground">
            <p className="font-medium text-foreground">Troca de plano da conta</p>
            <p className="mt-1">
              A conta esta em <strong>{currentSubscription?.plan_name || 'um plano ativo'}</strong>. Ao confirmar,
              encerramos a renovacao do plano atual e passamos a usar <strong>{plan.name}</strong> na conta.
            </p>
          </div>
        ) : null}

        <Form {...form}>
          <form className="space-y-6" onSubmit={(event) => void handleSubmit(event)}>
            <div className="grid gap-4 md:grid-cols-2">
              <FormField
                control={form.control}
                name="payer_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nome do pagador</FormLabel>
                    <FormControl>
                      <Input placeholder="Razao social ou nome completo" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="payer_email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>E-mail de cobranca</FormLabel>
                    <FormControl>
                      <Input type="email" placeholder="financeiro@parceiro.com" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="payer_document"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>CPF ou CNPJ</FormLabel>
                    <FormControl>
                      <Input
                        placeholder="000.000.000-00"
                        {...field}
                        onChange={(event) => field.onChange(formatDocument(event.target.value))}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="payer_phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Telefone</FormLabel>
                    <FormControl>
                      <Input
                        placeholder="(11) 99999-9999"
                        {...field}
                        onChange={(event) => field.onChange(formatPhone(event.target.value))}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="space-y-4">
              <div>
                <h4 className="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                  Endereco de cobranca
                </h4>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <FormField
                  control={form.control}
                  name="address_street"
                  render={({ field }) => (
                    <FormItem className="md:col-span-2">
                      <FormLabel>Rua</FormLabel>
                      <FormControl>
                        <Input placeholder="Rua de cobranca" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="address_number"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Numero</FormLabel>
                      <FormControl>
                        <Input placeholder="123" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="address_district"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Bairro</FormLabel>
                      <FormControl>
                        <Input placeholder="Centro" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="address_complement"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Complemento</FormLabel>
                      <FormControl>
                        <Input placeholder="Opcional" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="address_zip_code"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>CEP</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="00000-000"
                          {...field}
                          onChange={(event) => field.onChange(formatZipCode(event.target.value))}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="address_city"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Cidade</FormLabel>
                      <FormControl>
                        <Input placeholder="Sao Paulo" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="address_state"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>UF</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="SP"
                          {...field}
                          onChange={(event) => field.onChange(normalizeStateCode(event.target.value))}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
            </div>

            <Separator />

            <div className="space-y-4">
              <div className="flex items-center gap-2">
                <CreditCard className="h-4 w-4 text-primary" />
                <h4 className="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                  Cartao
                </h4>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <FormField
                  control={form.control}
                  name="card_number"
                  render={({ field }) => (
                    <FormItem className="md:col-span-2">
                      <FormLabel>Numero do cartao</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="0000 0000 0000 0000"
                          autoComplete="cc-number"
                          inputMode="numeric"
                          {...field}
                          onChange={(event) => field.onChange(formatCardNumber(event.target.value))}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="card_holder_name"
                  render={({ field }) => (
                    <FormItem className="md:col-span-2">
                      <FormLabel>Nome impresso no cartao</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="Nome e sobrenome"
                          autoComplete="cc-name"
                          {...field}
                          onChange={(event) => field.onChange(normalizeCardHolderName(event.target.value))}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="card_exp_month"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Mes</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="MM"
                          autoComplete="cc-exp-month"
                          inputMode="numeric"
                          {...field}
                          onChange={(event) => field.onChange(formatCardExpiryPart(event.target.value, 2))}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="card_exp_year"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Ano</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="AA"
                          autoComplete="cc-exp-year"
                          inputMode="numeric"
                          {...field}
                          onChange={(event) => field.onChange(formatCardExpiryPart(event.target.value, 2))}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="card_cvv"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>CVV</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="123"
                          autoComplete="cc-csc"
                          inputMode="numeric"
                          {...field}
                          onChange={(event) => field.onChange(formatCardExpiryPart(event.target.value, 4))}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
            </div>

            <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm text-muted-foreground">
              <div className="flex items-start gap-3">
                <ShieldCheck className="mt-0.5 h-4 w-4 text-emerald-300" />
                <div className="space-y-1">
                  <p className="font-medium text-foreground">Pagamento protegido</p>
                  <p>
                    Os dados do cartao ficam protegidos e a validacao acontece somente quando voce confirma a contratacao.
                  </p>
                </div>
              </div>
            </div>

            {tokenizationError ? (
              <div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                {tokenizationError}
              </div>
            ) : null}

            <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isBusy}>
                Cancelar
              </Button>
              <Button type="submit" disabled={isBusy || !plan || !price}>
                {isBusy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Confirmar contratacao
              </Button>
            </div>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
