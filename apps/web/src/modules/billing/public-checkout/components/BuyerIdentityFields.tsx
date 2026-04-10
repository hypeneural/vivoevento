import { useFormContext } from 'react-hook-form';

import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

import { formatPhone } from '../support/checkoutFormUtils';

type CheckoutFormValues = {
  responsible_name: string;
  whatsapp: string;
  email: string;
};

export function BuyerIdentityFields() {
  const form = useFormContext<CheckoutFormValues>();

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <FormField
        control={form.control}
        name="responsible_name"
        render={({ field }) => (
          <FormItem className="md:col-span-2">
            <FormLabel>Seu nome completo</FormLabel>
            <FormControl>
              <Input placeholder="Ex.: Camila Rocha" autoComplete="name" {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={form.control}
        name="whatsapp"
        render={({ field }) => (
          <FormItem>
            <FormLabel>WhatsApp com DDD</FormLabel>
            <FormControl>
              <Input
                placeholder="(48) 99999-9999"
                inputMode="tel"
                autoComplete="tel"
                {...field}
                onChange={(event) => field.onChange(formatPhone(event.target.value))}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={form.control}
        name="email"
        render={({ field }) => (
          <FormItem>
            <FormLabel>E-mail</FormLabel>
            <FormControl>
              <Input placeholder="voce@exemplo.com" {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </div>
  );
}
