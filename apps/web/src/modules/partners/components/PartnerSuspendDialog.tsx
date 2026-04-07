import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
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
import { Textarea } from '@/components/ui/textarea';

import type { PartnerListItem, PartnerSuspendPayload } from '../types';

const suspendSchema = z.object({
  reason: z.string().trim().min(3, 'Informe o motivo da suspensao.').max(160, 'Maximo de 160 caracteres.'),
  notes: z.string().trim().max(2000, 'Maximo de 2000 caracteres.'),
});

type SuspendValues = z.infer<typeof suspendSchema>;

interface PartnerSuspendDialogProps {
  open: boolean;
  partner?: PartnerListItem | null;
  isSubmitting?: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: PartnerSuspendPayload) => void;
}

export function PartnerSuspendDialog({
  open,
  partner,
  isSubmitting = false,
  onOpenChange,
  onSubmit,
}: PartnerSuspendDialogProps) {
  const form = useForm<SuspendValues>({
    resolver: zodResolver(suspendSchema),
    defaultValues: {
      reason: '',
      notes: '',
    },
  });

  useEffect(() => {
    if (open) {
      form.reset({ reason: '', notes: '' });
    }
  }, [form, open]);

  const handleSubmit = form.handleSubmit((values) => {
    onSubmit({
      reason: values.reason.trim(),
      notes: values.notes.trim() || null,
    });
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Suspender parceiro</DialogTitle>
          <DialogDescription>
            {partner ? `Suspender "${partner.name}" sem remover historico operacional.` : 'Suspender parceiro.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Motivo</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder="Ex: revisao administrativa" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="notes"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Notas</FormLabel>
                  <FormControl>
                    <Textarea {...field} rows={4} placeholder="Contexto interno opcional." />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancelar
              </Button>
              <Button type="submit" variant="destructive" disabled={isSubmitting}>
                Suspender parceiro
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
