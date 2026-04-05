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
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

import type { ClientFormPayload, ClientItem, ClientOrganizationOption, ClientType } from '../types';
import { CLIENT_TYPE_OPTIONS } from '../types';

const clientFormSchema = z.object({
  organization_id: z.string().optional(),
  type: z.enum(['pessoa_fisica', 'empresa']),
  name: z.string().trim().min(3, 'Informe ao menos 3 caracteres.').max(180, 'Máximo de 180 caracteres.'),
  email: z.string().trim().email('E-mail inválido.').max(160, 'Máximo de 160 caracteres.').or(z.literal('')),
  phone: z.string().trim().max(40, 'Máximo de 40 caracteres.'),
  document_number: z.string().trim().max(30, 'Máximo de 30 caracteres.'),
  notes: z.string().trim().max(2000, 'Máximo de 2000 caracteres.'),
});

type ClientFormValues = z.infer<typeof clientFormSchema>;

function buildDefaultValues(client?: ClientItem | null, defaultOrganizationId?: number | null): ClientFormValues {
  return {
    organization_id: client?.organization_id ? String(client.organization_id) : defaultOrganizationId ? String(defaultOrganizationId) : '',
    type: (client?.type ?? 'pessoa_fisica') as ClientType,
    name: client?.name ?? '',
    email: client?.email ?? '',
    phone: client?.phone ?? '',
    document_number: client?.document_number ?? '',
    notes: client?.notes ?? '',
  };
}

interface ClientFormDialogProps {
  open: boolean;
  mode: 'create' | 'edit';
  client?: ClientItem | null;
  canSelectOrganization: boolean;
  organizationLabel?: string | null;
  defaultOrganizationId?: number | null;
  organizationOptions: ClientOrganizationOption[];
  isSubmitting?: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: ClientFormPayload) => void;
}

export function ClientFormDialog({
  open,
  mode,
  client,
  canSelectOrganization,
  organizationLabel,
  defaultOrganizationId,
  organizationOptions,
  isSubmitting = false,
  onOpenChange,
  onSubmit,
}: ClientFormDialogProps) {
  const form = useForm<ClientFormValues>({
    resolver: zodResolver(clientFormSchema),
    defaultValues: buildDefaultValues(client, defaultOrganizationId),
  });

  useEffect(() => {
    if (!open) {
      return;
    }

    form.reset(buildDefaultValues(client, defaultOrganizationId));
  }, [client, defaultOrganizationId, form, open]);

  const handleSubmit = form.handleSubmit((values) => {
    if (canSelectOrganization && !values.organization_id) {
      form.setError('organization_id', {
        type: 'manual',
        message: 'Selecione uma organização para continuar.',
      });
      return;
    }

    onSubmit({
      organization_id: canSelectOrganization && values.organization_id ? Number(values.organization_id) : undefined,
      type: values.type,
      name: values.name.trim(),
      email: values.email.trim() || null,
      phone: values.phone.trim() || null,
      document_number: values.document_number.trim() || null,
      notes: values.notes.trim() || null,
    });
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{mode === 'create' ? 'Novo cliente' : 'Editar cliente'}</DialogTitle>
          <DialogDescription>
            {mode === 'create'
              ? 'Cadastre um cliente final com contato, documento e vínculo de organização.'
              : 'Atualize os dados do cliente e mantenha o histórico de eventos vinculado.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={handleSubmit} className="space-y-4">
            {canSelectOrganization ? (
              <FormField
                control={form.control}
                name="organization_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Parceiro</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Selecione uma organização" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {organizationOptions.map((organization) => (
                          <SelectItem key={organization.id} value={String(organization.id)}>
                            {organization.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            ) : (
              <div className="space-y-2">
                <FormLabel>Parceiro</FormLabel>
                <Input value={organizationLabel ?? 'Organização da sessão'} disabled />
              </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Tipo</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Selecione o tipo" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {CLIENT_TYPE_OPTIONS.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {option.label}
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
                name="document_number"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>CPF/CNPJ</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="Opcional" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Nome</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder="Ex: Ana Paula ou Studio Horizonte" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>E-mail</FormLabel>
                    <FormControl>
                      <Input {...field} type="email" placeholder="Opcional" />
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
            </div>

            <FormField
              control={form.control}
              name="notes"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Observações</FormLabel>
                  <FormControl>
                    <Textarea {...field} rows={4} placeholder="Briefing, preferências ou informações internas." />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancelar
              </Button>
              <Button type="submit" className="gradient-primary border-0" disabled={isSubmitting}>
                {mode === 'create' ? 'Cadastrar cliente' : 'Salvar alterações'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
