import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useQuery } from '@tanstack/react-query';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

import { partnersService } from '../api';
import type { PartnerGrantPayload, PartnerListItem } from '../types';

const grantSchema = z.object({
  event_id: z.string().min(1, 'Selecione o evento.'),
  source_type: z.enum(['bonus', 'manual_override', 'trial']),
  reason: z.string().trim().max(255, 'Maximo de 255 caracteres.'),
  starts_at: z.string().trim(),
  ends_at: z.string().trim(),
});

type GrantValues = z.infer<typeof grantSchema>;

interface PartnerGrantDialogProps {
  open: boolean;
  partner?: PartnerListItem | null;
  isSubmitting?: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: PartnerGrantPayload) => void;
}

export function PartnerGrantDialog({
  open,
  partner,
  isSubmitting = false,
  onOpenChange,
  onSubmit,
}: PartnerGrantDialogProps) {
  const form = useForm<GrantValues>({
    resolver: zodResolver(grantSchema),
    defaultValues: {
      event_id: '',
      source_type: 'bonus',
      reason: '',
      starts_at: '',
      ends_at: '',
    },
  });

  const eventsQuery = useQuery({
    queryKey: ['partners', partner?.id, 'grant-event-options'],
    queryFn: () => partnersService.listEvents(partner!.id, {
      per_page: 100,
      sort_by: 'starts_at',
      sort_direction: 'desc',
    }),
    enabled: open && !!partner,
  });

  useEffect(() => {
    if (open) {
      form.reset({
        event_id: '',
        source_type: 'bonus',
        reason: '',
        starts_at: '',
        ends_at: '',
      });
    }
  }, [form, open]);

  const handleSubmit = form.handleSubmit((values) => {
    onSubmit({
      event_id: Number(values.event_id),
      source_type: values.source_type,
      reason: values.reason.trim() || null,
      starts_at: values.starts_at || null,
      ends_at: values.ends_at || null,
      features: {},
      limits: {},
    });
  });

  const events = eventsQuery.data?.data ?? [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Criar grant</DialogTitle>
          <DialogDescription>
            {partner ? `Criar bonus/manual override para evento de "${partner.name}".` : 'Criar grant de evento.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="event_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Evento</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder={eventsQuery.isLoading ? 'Carregando eventos...' : 'Selecione o evento'} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {events.map((event) => (
                        <SelectItem key={event.id} value={String(event.id)}>
                          {event.title}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="source_type"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Tipo</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Tipo" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="bonus">Bonus</SelectItem>
                      <SelectItem value="manual_override">Manual override</SelectItem>
                      <SelectItem value="trial">Trial</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Motivo</FormLabel>
                  <FormControl>
                    <Textarea {...field} rows={3} placeholder="Contexto comercial ou operacional." />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="starts_at"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Inicio</FormLabel>
                    <FormControl>
                      <Input {...field} type="datetime-local" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="ends_at"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Fim</FormLabel>
                    <FormControl>
                      <Input {...field} type="datetime-local" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancelar
              </Button>
              <Button type="submit" className="gradient-primary border-0" disabled={isSubmitting || events.length === 0}>
                Criar grant
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
