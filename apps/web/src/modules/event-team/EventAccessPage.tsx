import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Mail, MessageCircle, ShieldCheck, Trash2, UserPlus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useParams } from 'react-router-dom';
import { z } from 'zod';

import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { queryKeys } from '@/lib/query-client';
import { PageHeader } from '@/shared/components/PageHeader';
import { useToast } from '@/hooks/use-toast';
import { getEventDetail } from '@/modules/events/api';
import { eventAccessApi } from './api';
import type { EventAccessInvitation, EventAccessMember } from './types';

const inviteSchema = z.object({
  name: z.string().trim().min(1, 'Informe o nome.').max(160, 'Nome muito longo.'),
  email: z.string().trim().email('Informe um e-mail válido.').or(z.literal('')),
  phone: z.string().trim().min(8, 'Informe o WhatsApp.'),
  preset_key: z.string().trim().min(1, 'Selecione o perfil de acesso.'),
  send_via_whatsapp: z.boolean().default(false),
});

type InviteFormValues = z.infer<typeof inviteSchema>;

function formatDate(value: string | null | undefined) {
  if (!value) {
    return 'Sem data';
  }

  return new Date(value).toLocaleString('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  });
}

function deliveryLabel(status: string | null) {
  switch (status) {
    case 'queued':
      return 'Enfileirado';
    case 'manual_link':
      return 'Link manual';
    case 'unavailable':
      return 'Sem instância';
    case 'failed':
      return 'Falhou';
    case 'revoked':
      return 'Revogado';
    default:
      return status || 'Pendente';
  }
}

export default function EventAccessPage() {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [isInviteDialogOpen, setInviteDialogOpen] = useState(false);
  const [invitationPendingRevoke, setInvitationPendingRevoke] = useState<EventAccessInvitation | null>(null);
  const [memberPendingRemoval, setMemberPendingRemoval] = useState<EventAccessMember | null>(null);

  const form = useForm<InviteFormValues>({
    resolver: zodResolver(inviteSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      preset_key: '',
      send_via_whatsapp: false,
    },
  });

  const eventId = id ?? '';

  const eventQuery = useQuery({
    queryKey: queryKeys.events.detail(eventId),
    queryFn: () => getEventDetail(eventId),
    enabled: eventId !== '',
  });

  const membersQuery = useQuery({
    queryKey: queryKeys.events.team(eventId),
    queryFn: () => eventAccessApi.listMembers(eventId),
    enabled: eventId !== '',
  });

  const invitationsQuery = useQuery({
    queryKey: queryKeys.events.accessInvitations(eventId),
    queryFn: () => eventAccessApi.listInvitations(eventId),
    enabled: eventId !== '',
  });

  const presetsQuery = useQuery({
    queryKey: queryKeys.auth.accessPresets(),
    queryFn: () => eventAccessApi.getPresets(),
  });

  const invalidateAccessQueries = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: queryKeys.events.team(eventId) }),
      queryClient.invalidateQueries({ queryKey: queryKeys.events.accessInvitations(eventId) }),
    ]);
  };

  const createInvitationMutation = useMutation({
    mutationFn: (values: InviteFormValues) => eventAccessApi.createInvitation(eventId, {
      invitee: {
        name: values.name,
        email: values.email,
        phone: values.phone,
      },
      preset_key: values.preset_key,
      send_via_whatsapp: values.send_via_whatsapp,
    }),
    onSuccess: async () => {
      await invalidateAccessQueries();
      form.reset();
      setInviteDialogOpen(false);
      toast({
        title: 'Convite criado',
        description: 'O acesso pendente foi registrado para este evento.',
      });
    },
  });

  const resendInvitationMutation = useMutation({
    mutationFn: ({ invitationId, sendViaWhatsApp }: { invitationId: number; sendViaWhatsApp: boolean }) =>
      eventAccessApi.resendInvitation(eventId, invitationId, { send_via_whatsapp: sendViaWhatsApp }),
    onSuccess: async () => {
      await invalidateAccessQueries();
      toast({
        title: 'Convite reenviado',
        description: 'O convite pendente foi atualizado.',
      });
    },
  });

  const revokeInvitationMutation = useMutation({
    mutationFn: (invitationId: number) => eventAccessApi.revokeInvitation(eventId, invitationId),
    onSuccess: async () => {
      await invalidateAccessQueries();
      setInvitationPendingRevoke(null);
      toast({
        title: 'Convite revogado',
        description: 'O acesso pendente foi revogado com sucesso.',
      });
    },
  });

  const removeMemberMutation = useMutation({
    mutationFn: (memberId: number) => eventAccessApi.removeMember(eventId, memberId),
    onSuccess: async () => {
      await invalidateAccessQueries();
      setMemberPendingRemoval(null);
      toast({
        title: 'Acesso removido',
        description: 'O membro foi removido da equipe do evento.',
      });
    },
  });

  const eventPresets = useMemo(
    () => presetsQuery.data?.event ?? [],
    [presetsQuery.data],
  );

  if (!eventId) {
    return (
      <Card className="border-destructive/30">
        <CardHeader>
          <CardTitle>Evento não encontrado</CardTitle>
          <CardDescription>Abra a página a partir do detalhe do evento para gerenciar os acessos.</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  if (eventQuery.isLoading || membersQuery.isLoading || invitationsQuery.isLoading || presetsQuery.isLoading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center">
        <Loader2 className="h-6 w-6 animate-spin text-primary" />
      </div>
    );
  }

  const event = eventQuery.data;
  const members = membersQuery.data ?? [];
  const invitations = invitationsQuery.data ?? [];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Acessos do evento"
        description="Convide DJs, noivos e equipe operacional com escopo limitado a este evento."
        actions={(
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" asChild>
              <Link to={`/events/${eventId}`}>Voltar ao evento</Link>
            </Button>
            <Dialog open={isInviteDialogOpen} onOpenChange={setInviteDialogOpen}>
              <DialogTrigger asChild>
                <Button>
                  <UserPlus className="mr-2 h-4 w-4" />
                  Convidar acesso
                </Button>
              </DialogTrigger>
              <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                  <DialogTitle>Novo acesso para o evento</DialogTitle>
                  <DialogDescription>
                    Escolha um perfil simples. O usuário receberá acesso somente a este evento.
                  </DialogDescription>
                </DialogHeader>

                <form
                  className="space-y-4"
                  onSubmit={form.handleSubmit((values) => createInvitationMutation.mutate(values))}
                >
                  <div className="space-y-2">
                    <Label htmlFor="event-access-name">Nome</Label>
                    <Input id="event-access-name" aria-label="Nome" {...form.register('name')} />
                    {form.formState.errors.name ? (
                      <p className="text-sm text-destructive">{form.formState.errors.name.message}</p>
                    ) : null}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="event-access-phone">WhatsApp</Label>
                    <Input id="event-access-phone" aria-label="WhatsApp" {...form.register('phone')} />
                    {form.formState.errors.phone ? (
                      <p className="text-sm text-destructive">{form.formState.errors.phone.message}</p>
                    ) : null}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="event-access-email">E-mail</Label>
                    <Input id="event-access-email" aria-label="E-mail" {...form.register('email')} />
                    {form.formState.errors.email ? (
                      <p className="text-sm text-destructive">{form.formState.errors.email.message}</p>
                    ) : null}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="event-access-preset">Perfil de acesso</Label>
                    <select
                      id="event-access-preset"
                      aria-label="Perfil de acesso"
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      {...form.register('preset_key')}
                    >
                      <option value="">Selecione</option>
                      {eventPresets.map((preset) => (
                        <option key={preset.key} value={preset.key}>
                          {preset.label}
                        </option>
                      ))}
                    </select>
                    {form.formState.errors.preset_key ? (
                      <p className="text-sm text-destructive">{form.formState.errors.preset_key.message}</p>
                    ) : null}
                  </div>

                  <div className="flex items-center gap-3 rounded-lg border border-border/60 px-3 py-3">
                    <Checkbox
                      id="event-access-send-via-whatsapp"
                      checked={form.watch('send_via_whatsapp')}
                      onCheckedChange={(checked) => form.setValue('send_via_whatsapp', checked === true)}
                      aria-label="Enviar convite pelo WhatsApp"
                    />
                    <div className="space-y-0.5">
                      <Label htmlFor="event-access-send-via-whatsapp">Enviar convite pelo WhatsApp</Label>
                      <p className="text-sm text-muted-foreground">
                        Usa a instância padrão do evento ou da organização, se estiver conectada.
                      </p>
                    </div>
                  </div>

                  <DialogFooter>
                    <Button
                      type="submit"
                      disabled={createInvitationMutation.isPending}
                    >
                      {createInvitationMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                      Enviar convite
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          </div>
        )}
      />

      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">{event?.title ?? 'Evento'}</CardTitle>
            <CardDescription>{event?.organization_name ?? 'Organização'}</CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            <p>Status: {event?.status ?? 'draft'}</p>
            <p>Data: {formatDate((event as { starts_at?: string | null } | undefined)?.starts_at)}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base">
              <Users className="h-4 w-4" />
              Acessos ativos
            </CardTitle>
            <CardDescription>Usuários que já aceitaram o convite.</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">{members.length}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="flex items-center gap-2 text-base">
              <ShieldCheck className="h-4 w-4" />
              Convites pendentes
            </CardTitle>
            <CardDescription>Aguardando aceite do usuário.</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold">{invitations.filter((item) => item.status === 'pending').length}</p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Equipe ativa</CardTitle>
          <CardDescription>Quem já acessa este evento hoje.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {members.length === 0 ? (
            <p className="text-sm text-muted-foreground">Nenhum acesso ativo ainda.</p>
          ) : members.map((member) => (
            <div key={member.id} className="flex flex-col gap-3 rounded-xl border border-border/60 p-4 md:flex-row md:items-center md:justify-between">
              <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-medium text-foreground">{member.user?.name ?? 'Usuário sem nome'}</p>
                  <Badge variant="outline">{member.role_label}</Badge>
                </div>
                <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
                  <span>{member.user?.phone ?? 'Sem WhatsApp'}</span>
                  <span>{member.user?.email ?? 'Sem e-mail'}</span>
                </div>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setMemberPendingRemoval(member)}
                aria-label={`Remover ${member.user?.name ?? 'membro'}`}
              >
                <Trash2 className="mr-2 h-4 w-4" />
                Remover acesso
              </Button>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Convites pendentes</CardTitle>
          <CardDescription>Reenvie, copie o link ou revogue o acesso antes do aceite.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {invitations.length === 0 ? (
            <p className="text-sm text-muted-foreground">Nenhum convite pendente para este evento.</p>
          ) : invitations.map((invitation) => (
            <div key={invitation.id} className="flex flex-col gap-4 rounded-xl border border-border/60 p-4">
              <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-medium text-foreground">{invitation.invitee.name}</p>
                    <Badge variant="outline">{invitation.role_label}</Badge>
                    <Badge variant="secondary">{deliveryLabel(invitation.delivery_status)}</Badge>
                  </div>
                  <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
                    <span className="inline-flex items-center gap-1">
                      <MessageCircle className="h-3.5 w-3.5" />
                      {invitation.invitee.phone || 'Sem WhatsApp'}
                    </span>
                    <span className="inline-flex items-center gap-1">
                      <Mail className="h-3.5 w-3.5" />
                      {invitation.invitee.email || 'Sem e-mail'}
                    </span>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Último envio: {formatDate(invitation.last_sent_at)}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      void navigator.clipboard?.writeText(invitation.invitation_url);
                      toast({
                        title: 'Link copiado',
                        description: 'O link do convite foi copiado para a área de transferência.',
                      });
                    }}
                  >
                    Copiar link
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => resendInvitationMutation.mutate({ invitationId: invitation.id, sendViaWhatsApp: true })}
                    disabled={resendInvitationMutation.isPending}
                  >
                    Reenviar WhatsApp
                  </Button>
                  <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => setInvitationPendingRevoke(invitation)}
                  >
                    Revogar convite
                  </Button>
                </div>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      <AlertDialog open={invitationPendingRevoke !== null} onOpenChange={(open) => !open && setInvitationPendingRevoke(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Revogar convite pendente</AlertDialogTitle>
            <AlertDialogDescription>
              O link atual deixará de funcionar para {invitationPendingRevoke?.invitee.name}.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (invitationPendingRevoke) {
                  revokeInvitationMutation.mutate(invitationPendingRevoke.id);
                }
              }}
            >
              Confirmar revogação
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={memberPendingRemoval !== null} onOpenChange={(open) => !open && setMemberPendingRemoval(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover acesso ativo</AlertDialogTitle>
            <AlertDialogDescription>
              {memberPendingRemoval?.user?.name} deixará de acessar este evento imediatamente.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (memberPendingRemoval) {
                  removeMemberMutation.mutate(memberPendingRemoval.id);
                }
              }}
            >
              Confirmar remoção
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
