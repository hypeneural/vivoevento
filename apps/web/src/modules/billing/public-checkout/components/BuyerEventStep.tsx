import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { useFormContext } from 'react-hook-form';

import type { PublicCheckoutIdentityCheckResponse } from '@/lib/api-types';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

import { BuyerIdentityFields } from './BuyerIdentityFields';
import { EventBasicsFields } from './EventBasicsFields';
import { IdentityAssistInline } from './IdentityAssistInline';

type CheckoutFormValues = {
  organization_name: string;
  event_date: string;
  event_city: string;
  event_description: string;
};

type BuyerEventStepProps = {
  identityState?: PublicCheckoutIdentityCheckResponse | null;
  isCheckingIdentity?: boolean;
  onContinue: () => void;
  loginHref: string;
};

export function BuyerEventStep({
  identityState,
  isCheckingIdentity = false,
  onContinue,
  loginHref,
}: BuyerEventStepProps) {
  const form = useFormContext<CheckoutFormValues>();
  const [showMore, setShowMore] = useState(false);

  return (
    <div className="space-y-6">
      <BuyerIdentityFields />
      <EventBasicsFields />

      <IdentityAssistInline state={identityState} isChecking={isCheckingIdentity} />

      <Collapsible open={showMore} onOpenChange={setShowMore}>
        <CollapsibleTrigger asChild>
          <Button variant="ghost" className="px-0 text-sm">
            Adicionar mais detalhes
            <ChevronDown className="h-4 w-4" />
          </Button>
        </CollapsibleTrigger>
        <CollapsibleContent className="space-y-4 pt-4">
          <div className="grid gap-4 md:grid-cols-2">
            <FormField
              control={form.control}
              name="event_date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Data do evento</FormLabel>
                  <FormControl>
                    <Input type="date" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="event_city"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Cidade</FormLabel>
                  <FormControl>
                    <Input placeholder="Cidade do evento" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
          <FormField
            control={form.control}
            name="organization_name"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Nome do casal, empresa ou responsavel</FormLabel>
                <FormControl>
                  <Input placeholder="Como voce quer identificar essa compra" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="event_description"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Descricao</FormLabel>
                <FormControl>
                  <Textarea placeholder="Se quiser, conte mais um pouco sobre o evento." {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </CollapsibleContent>
      </Collapsible>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button variant="link" className="h-auto px-0" asChild>
          <a href={loginHref}>Ja tenho conta</a>
        </Button>
        <Button onClick={onContinue}>
          Continuar para pagamento
        </Button>
      </div>
    </div>
  );
}
