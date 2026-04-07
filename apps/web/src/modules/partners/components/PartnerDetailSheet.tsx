import { useQuery } from '@tanstack/react-query';
import { CalendarDays, FileText, Gift, Loader2, Users } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { EmptyState } from '@/shared/components/EmptyState';

import { partnersService } from '../api';
import type { PartnerDetailItem, PartnerListItem } from '../types';

function formatCurrencyFromCents(cents: number) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(cents / 100);
}

function formatDate(value?: string | null) {
  if (!value) return 'Sem data';

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function formatDateTime(value?: string | null) {
  if (!value) return 'Sem data';

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function summaryValue(value: number | string) {
  return <span className="text-lg font-semibold tabular-nums">{value}</span>;
}

interface SummaryBoxProps {
  label: string;
  value: number | string;
  hint?: string;
}

function SummaryBox({ label, value, hint }: SummaryBoxProps) {
  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-1">{summaryValue(value)}</p>
      {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  );
}

interface PartnerDetailSheetProps {
  open: boolean;
  partnerId: number | null;
  canManage: boolean;
  onOpenChange: (open: boolean) => void;
  onEdit: (partner: PartnerListItem) => void;
  onSuspend: (partner: PartnerListItem) => void;
  onDelete: (partner: PartnerListItem) => void;
  onAddStaff: (partner: PartnerListItem) => void;
  onCreateGrant: (partner: PartnerListItem) => void;
}

export function PartnerDetailSheet({
  open,
  partnerId,
  canManage,
  onOpenChange,
  onEdit,
  onSuspend,
  onDelete,
  onAddStaff,
  onCreateGrant,
}: PartnerDetailSheetProps) {
  const detailQuery = useQuery({
    queryKey: ['partners', 'detail', partnerId],
    queryFn: () => partnersService.show(partnerId!),
    enabled: open && !!partnerId,
  });

  const eventsQuery = useQuery({
    queryKey: ['partners', partnerId, 'events', { per_page: 8 }],
    queryFn: () => partnersService.listEvents(partnerId!, { per_page: 8 }),
    enabled: open && !!partnerId,
  });

  const clientsQuery = useQuery({
    queryKey: ['partners', partnerId, 'clients', { per_page: 8 }],
    queryFn: () => partnersService.listClients(partnerId!, { per_page: 8 }),
    enabled: open && !!partnerId,
  });

  const staffQuery = useQuery({
    queryKey: ['partners', partnerId, 'staff', { per_page: 25 }],
    queryFn: () => partnersService.listStaff(partnerId!, { per_page: 25 }),
    enabled: open && !!partnerId,
  });

  const grantsQuery = useQuery({
    queryKey: ['partners', partnerId, 'grants', { per_page: 10 }],
    queryFn: () => partnersService.listGrants(partnerId!, { per_page: 10 }),
    enabled: open && !!partnerId,
  });

  const activityQuery = useQuery({
    queryKey: ['partners', partnerId, 'activity', { per_page: 10 }],
    queryFn: () => partnersService.listActivity(partnerId!, { per_page: 10 }),
    enabled: open && !!partnerId,
  });

  const partner = detailQuery.data as PartnerDetailItem | undefined;
  const partnerForActions = partner as PartnerListItem | undefined;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-5xl">
        <SheetHeader>
          <SheetTitle>{partner?.name ?? 'Detalhe do parceiro'}</SheetTitle>
          <SheetDescription>
            Visao administrativa global com eventos, clientes, equipe, grants e logs.
          </SheetDescription>
        </SheetHeader>

        {detailQuery.isLoading ? (
          <div className="mt-10 flex items-center justify-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando parceiro...
          </div>
        ) : detailQuery.isError || !partner ? (
          <div className="mt-10 rounded-2xl border border-destructive/30 p-8 text-center text-sm text-destructive">
            Nao foi possivel carregar o parceiro.
          </div>
        ) : (
          <div className="mt-6 space-y-6">
            <div className="flex flex-col gap-3 rounded-3xl border border-border/60 bg-muted/20 p-4 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="text-xl font-semibold">{partner.name}</h2>
                  <Badge variant="outline">{partner.status}</Badge>
                  {partner.segment ? <Badge variant="secondary">{partner.segment}</Badge> : null}
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                  {partner.email || 'Sem e-mail'} {partner.phone ? `· ${partner.phone}` : ''}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                  Plano: {partner.current_subscription.plan_name || 'Sem plano'} · {partner.current_subscription.status || 'sem assinatura'}
                </p>
              </div>

              {canManage && partnerForActions ? (
                <div className="flex flex-wrap gap-2">
                  <Button variant="outline" onClick={() => onEdit(partnerForActions)}>
                    Editar
                  </Button>
                  <Button variant="outline" onClick={() => onAddStaff(partnerForActions)}>
                    Adicionar staff
                  </Button>
                  <Button variant="outline" onClick={() => onCreateGrant(partnerForActions)}>
                    Criar grant
                  </Button>
                  <Button variant="outline" onClick={() => onSuspend(partnerForActions)}>
                    Suspender
                  </Button>
                  <Button variant="destructive" onClick={() => onDelete(partnerForActions)}>
                    Remover vazio
                  </Button>
                </div>
              ) : null}
            </div>

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <SummaryBox label="Eventos" value={partner.events_summary.total} hint={`${partner.events_summary.active} ativos`} />
              <SummaryBox label="Clientes" value={partner.clients_summary.total} />
              <SummaryBox label="Equipe" value={partner.staff_summary.total} hint={`${partner.staff_summary.owners} owners`} />
              <SummaryBox
                label="Receita"
                value={formatCurrencyFromCents(partner.revenue.total_cents)}
                hint={`Atualizado ${formatDate(partner.stats_refreshed_at)}`}
              />
            </div>

            <Tabs defaultValue="overview" className="space-y-4">
              <TabsList className="flex h-auto flex-wrap justify-start">
                <TabsTrigger value="overview">Resumo</TabsTrigger>
                <TabsTrigger value="events">Eventos</TabsTrigger>
                <TabsTrigger value="clients">Clientes</TabsTrigger>
                <TabsTrigger value="staff">Staff</TabsTrigger>
                <TabsTrigger value="grants">Grants</TabsTrigger>
                <TabsTrigger value="activity">Logs</TabsTrigger>
              </TabsList>

              <TabsContent value="overview" className="space-y-4">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                  <SummaryBox label="Bonus" value={partner.events_summary.bonus} />
                  <SummaryBox label="Override manual" value={partner.events_summary.manual_override} />
                  <SummaryBox label="Compra avulsa" value={partner.events_summary.single_purchase} />
                  <SummaryBox label="Assinatura" value={partner.events_summary.subscription_covered} />
                </div>
                <div className="rounded-2xl border border-border/60 p-4 text-sm text-muted-foreground">
                  <p className="font-medium text-foreground">Notas internas</p>
                  <p className="mt-2 whitespace-pre-wrap">{partner.notes || 'Sem notas cadastradas.'}</p>
                </div>
              </TabsContent>

              <TabsContent value="events">
                <div className="rounded-2xl border border-border/60">
                  {(eventsQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={CalendarDays} title="Nenhum evento encontrado" description="Este parceiro ainda nao tem eventos no recorte atual." />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Evento</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Comercial</TableHead>
                          <TableHead>Data</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(eventsQuery.data?.data ?? []).map((event) => (
                          <TableRow key={event.id}>
                            <TableCell className="font-medium">{event.title}</TableCell>
                            <TableCell><Badge variant="outline">{event.status}</Badge></TableCell>
                            <TableCell><Badge variant="secondary">{event.commercial_mode || 'none'}</Badge></TableCell>
                            <TableCell>{formatDate(event.starts_at)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="clients">
                <div className="rounded-2xl border border-border/60">
                  {(clientsQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={Users} title="Nenhum cliente encontrado" description="Este parceiro ainda nao possui clientes vinculados." />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Cliente</TableHead>
                          <TableHead>Contato</TableHead>
                          <TableHead>Eventos</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(clientsQuery.data?.data ?? []).map((client) => (
                          <TableRow key={client.id}>
                            <TableCell className="font-medium">{client.name}</TableCell>
                            <TableCell>{client.email || client.phone || 'Sem contato'}</TableCell>
                            <TableCell>{client.events_count ?? 0}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="staff">
                <div className="rounded-2xl border border-border/60">
                  {(staffQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={Users} title="Nenhum staff encontrado" description="Adicione membros para operar os eventos do parceiro." />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Usuario</TableHead>
                          <TableHead>Role</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Entrada</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(staffQuery.data?.data ?? []).map((member) => (
                          <TableRow key={member.id}>
                            <TableCell>
                              <div>
                                <p className="font-medium">{member.user?.name ?? 'Usuario sem nome'}</p>
                                <p className="text-xs text-muted-foreground">{member.user?.email ?? 'Sem e-mail'}</p>
                              </div>
                            </TableCell>
                            <TableCell>
                              <div className="flex flex-wrap gap-1">
                                <Badge variant="outline">{member.role_key}</Badge>
                                {member.is_owner ? <Badge variant="secondary">owner</Badge> : null}
                              </div>
                            </TableCell>
                            <TableCell>{member.status}</TableCell>
                            <TableCell>{formatDate(member.joined_at)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="grants">
                <div className="rounded-2xl border border-border/60">
                  {(grantsQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={Gift} title="Nenhum grant encontrado" description="Crie bonus ou override manual quando houver necessidade operacional." />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Evento</TableHead>
                          <TableHead>Tipo</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>Vigencia</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(grantsQuery.data?.data ?? []).map((grant) => (
                          <TableRow key={grant.id}>
                            <TableCell className="font-medium">{grant.event?.title ?? `Evento #${grant.event_id}`}</TableCell>
                            <TableCell><Badge variant="outline">{grant.source_type}</Badge></TableCell>
                            <TableCell>{grant.status}</TableCell>
                            <TableCell>{formatDate(grant.starts_at)} ate {formatDate(grant.ends_at)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="activity">
                <div className="rounded-2xl border border-border/60">
                  {(activityQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={FileText} title="Nenhum log encontrado" description="As acoes administrativas aparecerao aqui." />
                  ) : (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Evento</TableHead>
                          <TableHead>Ator</TableHead>
                          <TableHead>Descricao</TableHead>
                          <TableHead>Data</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(activityQuery.data?.data ?? []).map((activity) => (
                          <TableRow key={activity.id}>
                            <TableCell><Badge variant="outline">{activity.event || 'activity'}</Badge></TableCell>
                            <TableCell>{activity.actor?.name || 'Sistema'}</TableCell>
                            <TableCell>{activity.description || 'Sem descricao'}</TableCell>
                            <TableCell>{formatDateTime(activity.created_at)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  )}
                </div>
              </TabsContent>
            </Tabs>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
