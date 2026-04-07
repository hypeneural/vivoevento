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

import type { PartnerFormPayload, PartnerListItem, PartnerStatus } from '../types';

const partnerFormSchema = z.object({
  name: z.string().trim().min(2, 'Informe o nome do parceiro.').max(160, 'Maximo de 160 caracteres.'),
  legal_name: z.string().trim().max(200, 'Maximo de 200 caracteres.'),
  document_number: z.string().trim().max(30, 'Maximo de 30 caracteres.'),
  email: z.string().trim().email('E-mail invalido.').max(160, 'Maximo de 160 caracteres.').or(z.literal('')),
  billing_email: z.string().trim().email('E-mail invalido.').max(160, 'Maximo de 160 caracteres.').or(z.literal('')),
  phone: z.string().trim().max(40, 'Maximo de 40 caracteres.'),
  timezone: z.string().trim().max(64, 'Maximo de 64 caracteres.'),
  status: z.enum(['active', 'inactive', 'suspended']),
  segment: z.string().trim().max(80, 'Maximo de 80 caracteres.'),
  notes: z.string().trim().max(4000, 'Maximo de 4000 caracteres.'),
  owner_name: z.string().trim().max(160, 'Maximo de 160 caracteres.'),
  owner_email: z.string().trim().email('E-mail invalido.').max(160, 'Maximo de 160 caracteres.').or(z.literal('')),
  owner_phone: z.string().trim().max(40, 'Maximo de 40 caracteres.'),
});

type PartnerFormValues = z.infer<typeof partnerFormSchema>;

function defaultValues(partner?: PartnerListItem | null): PartnerFormValues {
  return {
    name: partner?.name ?? '',
    legal_name: partner?.legal_name ?? '',
    document_number: partner?.document_number ?? '',
    email: partner?.email ?? '',
    billing_email: partner?.billing_email ?? '',
    phone: partner?.phone ?? '',
    timezone: partner?.timezone ?? 'America/Sao_Paulo',
    status: (partner?.status as PartnerStatus) ?? 'active',
    segment: partner?.segment ?? '',
    notes: partner?.notes ?? '',
    owner_name: partner?.owner?.name ?? '',
    owner_email: partner?.owner?.email ?? '',
    owner_phone: partner?.owner?.phone ?? '',
  };
}

interface PartnerFormDialogProps {
  open: boolean;
  mode: 'create' | 'edit';
  partner?: PartnerListItem | null;
  isSubmitting?: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: PartnerFormPayload) => void;
}

export function PartnerFormDialog({
  open,
  mode,
  partner,
  isSubmitting = false,
  onOpenChange,
  onSubmit,
}: PartnerFormDialogProps) {
  const form = useForm<PartnerFormValues>({
    resolver: zodResolver(partnerFormSchema),
    defaultValues: defaultValues(partner),
  });

  useEffect(() => {
    if (open) {
      form.reset(defaultValues(partner));
    }
  }, [form, open, partner]);

  const handleSubmit = form.handleSubmit((values) => {
    if (mode === 'create' && !values.email.trim()) {
      form.setError('email', { type: 'manual', message: 'Informe o e-mail principal do parceiro.' });
      return;
    }

    if (mode === 'create' && !values.owner_name.trim()) {
      form.setError('owner_name', { type: 'manual', message: 'Informe o nome do responsavel principal.' });
      return;
    }

    if (mode === 'create' && !values.owner_email.trim()) {
      form.setError('owner_email', { type: 'manual', message: 'Informe o e-mail do responsavel principal.' });
      return;
    }

    onSubmit({
      name: values.name.trim(),
      legal_name: values.legal_name.trim() || null,
      document_number: values.document_number.trim() || null,
      email: values.email.trim() || null,
      billing_email: values.billing_email.trim() || null,
      phone: values.phone.trim() || null,
      timezone: values.timezone.trim() || 'America/Sao_Paulo',
      status: values.status,
      segment: values.segment.trim() || null,
      notes: values.notes.trim() || null,
      owner: mode === 'create' ? {
        name: values.owner_name.trim(),
        email: values.owner_email.trim(),
        phone: values.owner_phone.trim() || null,
        send_invite: true,
      } : undefined,
    });
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{mode === 'create' ? 'Novo parceiro' : 'Editar parceiro'}</DialogTitle>
          <DialogDescription>
            {mode === 'create'
              ? 'Cadastre a organizacao parceira e o responsavel principal.'
              : 'Atualize dados cadastrais e perfil comercial do parceiro.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nome comercial</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="Ex: Cerimonial Horizonte" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="segment"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Segmento</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="cerimonialista, fotografo, agencia..." />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="legal_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Razao social</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="Opcional" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="document_number"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Documento</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="CPF/CNPJ" />
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
                    <FormLabel>E-mail principal</FormLabel>
                    <FormControl>
                      <Input {...field} type="email" placeholder="contato@parceiro.com" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="billing_email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>E-mail financeiro</FormLabel>
                    <FormControl>
                      <Input {...field} type="email" placeholder="financeiro@parceiro.com" />
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
                      <Input {...field} placeholder="WhatsApp ou telefone" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="timezone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Fuso horario</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="America/Sao_Paulo" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="status"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Status</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="Status" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="active">Ativo</SelectItem>
                        <SelectItem value="inactive">Inativo</SelectItem>
                        <SelectItem value="suspended">Suspenso</SelectItem>
                      </SelectContent>
                    </Select>
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
                  <FormLabel>Notas internas</FormLabel>
                  <FormControl>
                    <Textarea {...field} rows={4} placeholder="Notas comerciais, contexto de atendimento e observacoes." />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {mode === 'create' ? (
              <div className="rounded-2xl border border-border/60 p-4">
                <p className="text-sm font-semibold">Responsavel principal</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Este usuario sera vinculado como proprietario da organizacao parceira.
                </p>

                <div className="mt-4 grid gap-4 sm:grid-cols-3">
                  <FormField
                    control={form.control}
                    name="owner_name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Nome</FormLabel>
                        <FormControl>
                          <Input {...field} placeholder="Nome do responsavel principal" />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name="owner_email"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>E-mail</FormLabel>
                        <FormControl>
                          <Input {...field} type="email" placeholder="responsavel@parceiro.com" />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name="owner_phone"
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
              </div>
            ) : null}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Cancelar
              </Button>
              <Button type="submit" className="gradient-primary border-0" disabled={isSubmitting}>
                {mode === 'create' ? 'Cadastrar parceiro' : 'Salvar alteracoes'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
