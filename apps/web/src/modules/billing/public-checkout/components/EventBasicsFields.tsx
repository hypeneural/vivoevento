import { useFormContext } from 'react-hook-form';

import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type CheckoutFormValues = {
  event_title: string;
  event_type: 'wedding' | 'birthday' | 'fifteen' | 'corporate' | 'fair' | 'graduation' | 'other';
};

export function EventBasicsFields() {
  const form = useFormContext<CheckoutFormValues>();

  return (
    <div className="grid gap-4 md:grid-cols-2">
      <FormField
        control={form.control}
        name="event_title"
        render={({ field }) => (
          <FormItem>
            <FormLabel>Nome do evento</FormLabel>
            <FormControl>
              <Input placeholder="Ex.: Casamento Camila e Bruno" {...field} />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
      <FormField
        control={form.control}
        name="event_type"
        render={({ field }) => (
          <FormItem>
            <FormLabel>Tipo do evento</FormLabel>
            <Select onValueChange={field.onChange} value={field.value}>
              <FormControl>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o tipo do evento" />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value="wedding">Casamento</SelectItem>
                <SelectItem value="birthday">Aniversario</SelectItem>
                <SelectItem value="fifteen">15 anos</SelectItem>
                <SelectItem value="corporate">Corporativo</SelectItem>
                <SelectItem value="fair">Feira</SelectItem>
                <SelectItem value="graduation">Formatura</SelectItem>
                <SelectItem value="other">Outro</SelectItem>
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        )}
      />
    </div>
  );
}
