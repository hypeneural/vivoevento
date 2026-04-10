import { useEffect, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { CreditCard, Loader2, ShieldCheck } from 'lucide-react';
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
import type { ApiBillingCard } from '@/lib/api-types';
import { createPagarmeCardToken, PagarmeTokenizationError } from '@/lib/pagarme-tokenization';
import {
  digitsOnly,
  formatCardExpiryPart,
  formatCardNumber,
  hasTwoWords,
  isValidCardExpiry,
  isValidCardNumber,
  normalizeCardHolderName,
} from '@/modules/billing/public-checkout/support/checkoutFormUtils';

import type { UpdateSubscriptionCardPayload } from '../api';

const cardUpdateSchema = z.object({
  card_number: z.string().trim().min(13, 'Informe o numero do cartao.'),
  card_holder_name: z.string().trim().min(3, 'Informe o nome do titular.'),
  card_exp_month: z.string().trim().min(2, 'Informe o mes da validade.'),
  card_exp_year: z.string().trim().min(2, 'Informe o ano da validade.'),
  card_cvv: z.string().trim().min(3, 'Informe o CVV.'),
}).superRefine((values, ctx) => {
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

type CardUpdateFormValues = z.infer<typeof cardUpdateSchema>;

const EMPTY_CARD_VALUES: CardUpdateFormValues = {
  card_number: '',
  card_holder_name: '',
  card_exp_month: '',
  card_exp_year: '',
  card_cvv: '',
};

export interface RecurringCardUpdateDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  cards: ApiBillingCard[];
  isCardsLoading?: boolean;
  isSubmitting?: boolean;
  onSubmit: (payload: UpdateSubscriptionCardPayload) => Promise<unknown>;
}

export function RecurringCardUpdateDialog({
  open,
  onOpenChange,
  cards,
  isCardsLoading = false,
  isSubmitting = false,
  onSubmit,
}: RecurringCardUpdateDialogProps) {
  const [tokenizationError, setTokenizationError] = useState<string | null>(null);
  const [isTokenizing, setIsTokenizing] = useState(false);

  const form = useForm<CardUpdateFormValues>({
    resolver: zodResolver(cardUpdateSchema),
    defaultValues: EMPTY_CARD_VALUES,
  });

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset(EMPTY_CARD_VALUES);
    setTokenizationError(null);
  }, [form, open]);

  const isBusy = isSubmitting || isTokenizing;

  const handleUseSavedCard = async (cardId: string) => {
    setTokenizationError(null);
    await onSubmit({ card_id: cardId });
    onOpenChange(false);
  };

  const handleNewCardSubmit = form.handleSubmit(async (values) => {
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

      await onSubmit({ card_token: token.id });
      onOpenChange(false);
    } catch (error) {
      if (error instanceof PagarmeTokenizationError || error instanceof Error) {
        setTokenizationError(error.message);
      } else {
        setTokenizationError('Nao foi possivel tokenizar o novo cartao agora.');
      }
    } finally {
      setIsTokenizing(false);
    }
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] max-w-3xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Trocar cartao da assinatura</DialogTitle>
          <DialogDescription>
            Use um cartao salvo na wallet do cliente ou tokeniza um novo cartao apenas no submit final.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Wallet do cliente</p>
            <div className="mt-3 space-y-3">
              {isCardsLoading ? (
                <div className="rounded-2xl border border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                  Carregando cartoes salvos...
                </div>
              ) : null}

              {!isCardsLoading && cards.length === 0 ? (
                <div className="rounded-2xl border border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                  Nenhum cartao salvo encontrado. Cadastre um novo cartao abaixo.
                </div>
              ) : null}

              {cards.map((card) => (
                <div
                  key={card.id}
                  className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-background/40 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-2xl border border-border/60 bg-background/60">
                      <CreditCard className="h-4 w-4 text-primary" />
                    </div>
                    <div>
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium text-foreground">
                          {card.label || `${card.brand || 'Cartao'} final ${card.last_four || '----'}`}
                        </p>
                        {card.is_default ? (
                          <Badge variant="outline" className="border-emerald-500/30 bg-emerald-500/10 text-emerald-100">
                            Padrao atual
                          </Badge>
                        ) : null}
                      </div>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {card.holder_name || 'Titular nao informado'} - status {card.status || 'active'}
                      </p>
                    </div>
                  </div>

                  <Button
                    type="button"
                    variant={card.is_default ? 'secondary' : 'outline'}
                    disabled={isBusy || card.is_default}
                    onClick={() => void handleUseSavedCard(card.id)}
                  >
                    {isBusy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                    Usar este cartao
                  </Button>
                </div>
              ))}
            </div>
          </div>

          <Separator />

          <Form {...form}>
            <form className="space-y-4" onSubmit={(event) => void handleNewCardSubmit(event)}>
              <div className="flex items-center gap-2">
                <CreditCard className="h-4 w-4 text-primary" />
                <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Novo cartao</p>
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

              <div className="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm text-muted-foreground">
                <div className="flex items-start gap-3">
                  <ShieldCheck className="mt-0.5 h-4 w-4 text-emerald-300" />
                  <div className="space-y-1">
                    <p className="font-medium text-foreground">Tokenizacao no submit final</p>
                    <p>O backend recebe apenas <code>card_token</code>; numero e CVV nao saem do navegador.</p>
                  </div>
                </div>
              </div>

              {tokenizationError ? (
                <div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                  {tokenizationError}
                </div>
              ) : null}

              <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" disabled={isBusy} onClick={() => onOpenChange(false)}>
                  Fechar
                </Button>
                <Button type="submit" disabled={isBusy}>
                  {isBusy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                  Salvar novo cartao
                </Button>
              </div>
            </form>
          </Form>
        </div>
      </DialogContent>
    </Dialog>
  );
}
