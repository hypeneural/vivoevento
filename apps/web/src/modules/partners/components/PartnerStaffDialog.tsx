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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatRoleLabel } from '@/shared/auth/labels';

import type { PartnerListItem, PartnerStaffPayload } from '../types';

const staffSchema = z.object({
  name: z.string().trim().min(2, 'Informe o nome do membro.').max(160, 'Maximo de 160 caracteres.'),
  email: z.string().trim().email('E-mail invalido.').max(160, 'Maximo de 160 caracteres.'),
  phone: z.string().trim().max(40, 'Maximo de 40 caracteres.'),
  role_key: z.enum(['partner-owner', 'partner-manager', 'event-operator', 'viewer']),
  is_owner: z.enum(['yes', 'no']),
});

type StaffValues = z.infer<typeof staffSchema>;

interface PartnerStaffDialogProps {
  open: boolean;
  partner?: PartnerListItem | null;
  isSubmitting?: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: PartnerStaffPayload) => void;
}

export function PartnerStaffDialog({
  open,
  partner,
  isSubmitting = false,
  onOpenChange,
  onSubmit,
}: PartnerStaffDialogProps) {
  const form = useForm<StaffValues>({
    resolver: zodResolver(staffSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      role_key: 'partner-manager',
      is_owner: 'no',
    },
  });

  useEffect(() => {
    if (open) {
      form.reset({
        name: '',
        email: '',
        phone: '',
        role_key: 'partner-manager',
        is_owner: 'no',
      });
    }
  }, [form, open]);

  const handleSubmit = form.handleSubmit((values) => {
    onSubmit({
      user: {
        name: values.name.trim(),
        email: values.email.trim(),
        phone: values.phone.trim() || null,
      },
      role_key: values.role_key,
      is_owner: values.is_owner === 'yes',
      send_invite: true,
    });
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Adicionar membro</DialogTitle>
          <DialogDescription>
            {partner ? `Adicionar membro em "${partner.name}".` : 'Adicionar membro na organizacao parceira.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nome</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="Nome do membro" />
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
                      <Input {...field} type="email" placeholder="staff@parceiro.com" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Telefone</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="Opcional" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="role_key"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Perfil</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Selecione o perfil" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="partner-owner">{formatRoleLabel('partner-owner', 'partner-owner')}</SelectItem>
                        <SelectItem value="partner-manager">{formatRoleLabel('partner-manager', 'partner-manager')}</SelectItem>
                        <SelectItem value="event-operator">{formatRoleLabel('event-operator', 'event-operator')}</SelectItem>
                        <SelectItem value="viewer">{formatRoleLabel('viewer', 'viewer')}</SelectItem>
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="is_owner"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Eh proprietario?</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Defina a titularidade" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="no">Nao</SelectItem>
                        <SelectItem value="yes">Sim</SelectItem>
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancelar
              </Button>
              <Button type="submit" className="gradient-primary border-0" disabled={isSubmitting}>
                Adicionar membro
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
