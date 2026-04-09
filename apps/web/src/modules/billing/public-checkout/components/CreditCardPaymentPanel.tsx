import { useFormContext } from 'react-hook-form';

import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

import type { CheckoutV2FormValues } from '../support/checkoutFormSchema';
import {
  formatCardExpiryPart,
  formatCardNumber,
  formatCpf,
  formatPhone,
  formatZipCode,
  normalizeCardHolderName,
  normalizeStateCode,
} from '../support/checkoutFormUtils';

export function CreditCardPaymentPanel() {
  const form = useFormContext<CheckoutV2FormValues>();

  return (
    <div className="space-y-6 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5">
      <div className="space-y-2">
        <h3 className="text-base font-semibold text-slate-950">Pagamento seguro por cartao</h3>
        <p className="text-sm leading-6 text-slate-600">
          Seus dados sao protegidos. Para concluir, precisamos dos dados do pagador e do endereco de cobranca.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <FormField
          control={form.control}
          name="payer_document"
          render={({ field }) => (
            <FormItem>
              <FormLabel>CPF do pagador</FormLabel>
              <FormControl>
                <Input
                  placeholder="000.000.000-00"
                  {...field}
                  onChange={(event) => field.onChange(formatCpf(event.target.value))}
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
              <FormLabel>Telefone do pagador</FormLabel>
              <FormControl>
                <Input
                  placeholder="(48) 99999-9999"
                  {...field}
                  onChange={(event) => field.onChange(formatPhone(event.target.value))}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
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
                <Input placeholder="Cidade de cobranca" {...field} />
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
              <FormLabel>Estado</FormLabel>
              <FormControl>
                <Input
                  placeholder="SC"
                  {...field}
                  onChange={(event) => field.onChange(normalizeStateCode(event.target.value))}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
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
                  inputMode="numeric"
                  autoComplete="cc-number"
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
                  inputMode="numeric"
                  autoComplete="cc-exp-month"
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
                  inputMode="numeric"
                  autoComplete="cc-exp-year"
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
                  inputMode="numeric"
                  autoComplete="cc-csc"
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
  );
}
