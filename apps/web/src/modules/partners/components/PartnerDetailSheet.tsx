import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { CalendarDays, FileText, Gift, Loader2, Users } from 'lucide-react';

import type { ApiPaginationMeta } from '@/lib/api-types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatRoleLabel, formatUserStatusLabel } from '@/shared/auth/labels';
import { EmptyState } from '@/shared/components/EmptyState';

import { partnersService } from '../api';
import type { EventCommercialMode } from '@/modules/events/types';
import type { PartnerDetailItem, PartnerListItem } from '../types';

function formatCurrencyFromCents(cents: number) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(cents / 100);
}

function formatDate(value?: string | null) {
  if (!value) return 'Sem data';
  return new Date(value).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTime(value?: string | null) {
  if (!value) return 'Sem data';
  return new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatCommercialModeLabel(mode?: string | null) {
  if (mode === 'subscription_covered') return 'Assinatura';
  if (mode === 'single_purchase') return 'Compra avulsa';
  if (mode === 'bonus') return 'Bonus';
  if (mode === 'manual_override') return 'Liberacao manual';
  if (mode === 'trial') return 'Periodo de teste';
  if (mode === 'none') return 'Sem cobertura';
  return mode || 'Sem cobertura';
}

function formatGrantSourceLabel(sourceType?: string | null) {
  if (sourceType === 'subscription') return 'Assinatura';
  if (sourceType === 'event_purchase') return 'Compra avulsa';
  if (sourceType === 'bonus') return 'Bonus';
  if (sourceType === 'manual_override') return 'Liberacao manual';
  if (sourceType === 'trial') return 'Periodo de teste';
  return sourceType || 'Nao informado';
}

function normalizeTextFilter(value: string) {
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : undefined;
}

function SummaryBox({ label, value, hint }: { label: string; value: number | string; hint?: string }) {
  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-1 text-lg font-semibold tabular-nums">{value}</p>
      {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  );
}

function TabPagination({ meta, isFetching, onPrevious, onNext }: {
  meta?: ApiPaginationMeta;
  isFetching: boolean;
  onPrevious: () => void;
  onNext: () => void;
}) {
  if (!meta) return null;

  return (
    <div className="flex items-center justify-between border-t border-border/60 px-4 py-3 text-xs text-muted-foreground">
      <span>Pagina {meta.page} de {meta.last_page} | {meta.total} registros</span>
      <div className="flex items-center gap-2">
        <Button variant="outline" size="sm" disabled={meta.page <= 1 || isFetching} onClick={onPrevious}>Anterior</Button>
        <Button variant="outline" size="sm" disabled={meta.page >= meta.last_page || isFetching} onClick={onNext}>Proxima</Button>
      </div>
    </div>
  );
}

type DetailTabKey = 'overview' | 'events' | 'clients' | 'staff' | 'grants' | 'activity';

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
  const [activeTab, setActiveTab] = useState<DetailTabKey>('overview');
  const [eventsFilters, setEventsFilters] = useState({ search: '', commercialMode: 'all' as 'all' | EventCommercialMode, page: 1, perPage: 8 });
  const [clientsFilters, setClientsFilters] = useState({ search: '', hasEvents: 'all' as 'all' | 'with_events' | 'without_events', page: 1, perPage: 8 });
  const [staffFilters, setStaffFilters] = useState({ search: '', page: 1, perPage: 25 });
  const [grantsFilters, setGrantsFilters] = useState({ sourceType: 'all', status: 'all', page: 1, perPage: 10 });
  const [activityFilters, setActivityFilters] = useState({ search: '', page: 1, perPage: 10 });

  useEffect(() => {
    if (!open) return;
    setActiveTab('overview');
    setEventsFilters({ search: '', commercialMode: 'all', page: 1, perPage: 8 });
    setClientsFilters({ search: '', hasEvents: 'all', page: 1, perPage: 8 });
    setStaffFilters({ search: '', page: 1, perPage: 25 });
    setGrantsFilters({ sourceType: 'all', status: 'all', page: 1, perPage: 10 });
    setActivityFilters({ search: '', page: 1, perPage: 10 });
  }, [open, partnerId]);

  const detailQuery = useQuery({ queryKey: ['partners', 'detail', partnerId], queryFn: () => partnersService.show(partnerId!), enabled: open && !!partnerId });
  const eventsQuery = useQuery({ queryKey: ['partners', partnerId, 'events', eventsFilters], queryFn: () => partnersService.listEvents(partnerId!, { page: eventsFilters.page, per_page: eventsFilters.perPage, search: normalizeTextFilter(eventsFilters.search), commercial_mode: eventsFilters.commercialMode !== 'all' ? eventsFilters.commercialMode : undefined }), enabled: open && !!partnerId });
  const clientsQuery = useQuery({ queryKey: ['partners', partnerId, 'clients', clientsFilters], queryFn: () => partnersService.listClients(partnerId!, { page: clientsFilters.page, per_page: clientsFilters.perPage, search: normalizeTextFilter(clientsFilters.search), has_events: clientsFilters.hasEvents === 'with_events' ? true : clientsFilters.hasEvents === 'without_events' ? false : undefined }), enabled: open && !!partnerId });
  const staffQuery = useQuery({ queryKey: ['partners', partnerId, 'staff', staffFilters], queryFn: () => partnersService.listStaff(partnerId!, { page: staffFilters.page, per_page: staffFilters.perPage, search: normalizeTextFilter(staffFilters.search) }), enabled: open && !!partnerId });
  const grantsQuery = useQuery({ queryKey: ['partners', partnerId, 'grants', grantsFilters], queryFn: () => partnersService.listGrants(partnerId!, { page: grantsFilters.page, per_page: grantsFilters.perPage, source_type: grantsFilters.sourceType !== 'all' ? grantsFilters.sourceType : undefined, status: grantsFilters.status !== 'all' ? grantsFilters.status : undefined }), enabled: open && !!partnerId });
  const activityQuery = useQuery({ queryKey: ['partners', partnerId, 'activity', activityFilters], queryFn: () => partnersService.listActivity(partnerId!, { page: activityFilters.page, per_page: activityFilters.perPage, search: normalizeTextFilter(activityFilters.search) }), enabled: open && !!partnerId });

  const partner = detailQuery.data as PartnerDetailItem | undefined;
  const partnerForActions = partner as PartnerListItem | undefined;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-5xl">
        <SheetHeader>
          <SheetTitle>{partner?.name ?? 'Detalhe do parceiro'}</SheetTitle>
          <SheetDescription>Visao administrativa global com eventos, clientes, equipe, concessoes e logs.</SheetDescription>
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
                  <Badge variant="outline">{formatUserStatusLabel(partner.status)}</Badge>
                  {partner.segment ? <Badge variant="secondary">{partner.segment}</Badge> : null}
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                  {partner.email || 'Sem e-mail'} {partner.phone ? ` | ${partner.phone}` : ''}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                  Plano: {partner.current_subscription.plan_name || 'Sem plano'} | {partner.current_subscription.status || 'sem assinatura'}
                </p>
              </div>

              {canManage && partnerForActions ? (
                <div className="flex flex-wrap gap-2">
                  <Button variant="outline" onClick={() => onEdit(partnerForActions)}>Editar</Button>
                  <Button variant="outline" onClick={() => onAddStaff(partnerForActions)}>Adicionar membro</Button>
                  <Button variant="outline" onClick={() => onCreateGrant(partnerForActions)}>Criar concessao</Button>
                  <Button variant="outline" onClick={() => onSuspend(partnerForActions)}>Suspender</Button>
                  <Button variant="destructive" onClick={() => onDelete(partnerForActions)}>Remover vazio</Button>
                </div>
              ) : null}
            </div>

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <SummaryBox label="Eventos" value={partner.events_summary.total} hint={`${partner.events_summary.active} ativos`} />
              <SummaryBox label="Clientes" value={partner.clients_summary.total} />
              <SummaryBox label="Equipe" value={partner.staff_summary.total} hint={`${partner.staff_summary.owners} proprietarios`} />
              <SummaryBox label="Receita" value={formatCurrencyFromCents(partner.revenue.total_cents)} hint={`Atualizado ${formatDate(partner.stats_refreshed_at)}`} />
            </div>

            <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as DetailTabKey)} className="space-y-4">
              <TabsList className="flex h-auto flex-wrap justify-start">
                <TabsTrigger value="overview">Resumo</TabsTrigger>
                <TabsTrigger value="events">Eventos</TabsTrigger>
                <TabsTrigger value="clients">Clientes</TabsTrigger>
                <TabsTrigger value="staff">Equipe</TabsTrigger>
                <TabsTrigger value="grants">Concessoes</TabsTrigger>
                <TabsTrigger value="activity">Logs</TabsTrigger>
              </TabsList>

              <TabsContent value="overview" className="space-y-4">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                  <SummaryBox label="Bonus" value={partner.events_summary.bonus} />
                  <SummaryBox label="Liberacao manual" value={partner.events_summary.manual_override} />
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
                  <div className="grid gap-3 border-b border-border/60 p-4 md:grid-cols-[minmax(0,1fr)_220px_140px]">
                    <Input aria-label="Buscar eventos" placeholder="Buscar eventos do parceiro" value={eventsFilters.search} onChange={(event) => setEventsFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} />
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Modo comercial</span>
                      <select aria-label="Modo comercial" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={eventsFilters.commercialMode} onChange={(event) => setEventsFilters((current) => ({ ...current, commercialMode: event.target.value as 'all' | EventCommercialMode, page: 1 }))}>
                        <option value="all">Todos</option>
                        <option value="subscription_covered">Assinatura</option>
                        <option value="single_purchase">Compra avulsa</option>
                        <option value="bonus">Bonus</option>
                        <option value="manual_override">Liberacao manual</option>
                        <option value="trial">Periodo de teste</option>
                        <option value="none">Sem cobertura</option>
                      </select>
                    </label>
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Eventos por pagina</span>
                      <select aria-label="Eventos por pagina" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={String(eventsFilters.perPage)} onChange={(event) => setEventsFilters((current) => ({ ...current, perPage: Number(event.target.value), page: 1 }))}>
                        {[8, 15, 25].map((option) => <option key={option} value={option}>{option}</option>)}
                      </select>
                    </label>
                  </div>
                  {(eventsQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={CalendarDays} title="Nenhum evento encontrado" description="Este parceiro ainda nao tem eventos no recorte atual." />
                  ) : (
                    <>
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
                              <TableCell><Badge variant="secondary">{formatCommercialModeLabel(event.commercial_mode)}</Badge></TableCell>
                              <TableCell>{formatDate(event.starts_at)}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                      <TabPagination meta={eventsQuery.data?.meta} isFetching={eventsQuery.isFetching} onPrevious={() => setEventsFilters((current) => ({ ...current, page: Math.max(1, current.page - 1) }))} onNext={() => setEventsFilters((current) => ({ ...current, page: current.page + 1 }))} />
                    </>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="clients">
                <div className="rounded-2xl border border-border/60">
                  <div className="grid gap-3 border-b border-border/60 p-4 md:grid-cols-[minmax(0,1fr)_220px_140px]">
                    <Input aria-label="Buscar clientes" placeholder="Buscar clientes do parceiro" value={clientsFilters.search} onChange={(event) => setClientsFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} />
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Recorte de clientes</span>
                      <select aria-label="Recorte de clientes" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={clientsFilters.hasEvents} onChange={(event) => setClientsFilters((current) => ({ ...current, hasEvents: event.target.value as 'all' | 'with_events' | 'without_events', page: 1 }))}>
                        <option value="all">Todos</option>
                        <option value="with_events">Com eventos</option>
                        <option value="without_events">Sem eventos</option>
                      </select>
                    </label>
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Clientes por pagina</span>
                      <select aria-label="Clientes por pagina" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={String(clientsFilters.perPage)} onChange={(event) => setClientsFilters((current) => ({ ...current, perPage: Number(event.target.value), page: 1 }))}>
                        {[8, 15, 25].map((option) => <option key={option} value={option}>{option}</option>)}
                      </select>
                    </label>
                  </div>
                  {(clientsQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={Users} title="Nenhum cliente encontrado" description="Este parceiro ainda nao possui clientes vinculados." />
                  ) : (
                    <>
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
                      <TabPagination meta={clientsQuery.data?.meta} isFetching={clientsQuery.isFetching} onPrevious={() => setClientsFilters((current) => ({ ...current, page: Math.max(1, current.page - 1) }))} onNext={() => setClientsFilters((current) => ({ ...current, page: current.page + 1 }))} />
                    </>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="staff">
                <div className="rounded-2xl border border-border/60">
                  <div className="grid gap-3 border-b border-border/60 p-4 md:grid-cols-[minmax(0,1fr)_160px]">
                    <Input aria-label="Buscar equipe" placeholder="Buscar membros do parceiro" value={staffFilters.search} onChange={(event) => setStaffFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} />
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Membros por pagina</span>
                      <select aria-label="Membros por pagina" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={String(staffFilters.perPage)} onChange={(event) => setStaffFilters((current) => ({ ...current, perPage: Number(event.target.value), page: 1 }))}>
                        {[10, 25, 50].map((option) => <option key={option} value={option}>{option}</option>)}
                      </select>
                    </label>
                  </div>
                  {(staffQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={Users} title="Nenhum membro encontrado" description="Adicione membros para operar os eventos do parceiro." />
                  ) : (
                    <>
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Usuario</TableHead>
                            <TableHead>Perfil</TableHead>
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
                                  <Badge variant="outline">{formatRoleLabel(member.role_key, member.role_key)}</Badge>
                                  {member.is_owner ? <Badge variant="secondary">Proprietario</Badge> : null}
                                </div>
                              </TableCell>
                              <TableCell>{formatUserStatusLabel(member.status)}</TableCell>
                              <TableCell>{formatDate(member.joined_at)}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                      <TabPagination meta={staffQuery.data?.meta} isFetching={staffQuery.isFetching} onPrevious={() => setStaffFilters((current) => ({ ...current, page: Math.max(1, current.page - 1) }))} onNext={() => setStaffFilters((current) => ({ ...current, page: current.page + 1 }))} />
                    </>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="grants">
                <div className="rounded-2xl border border-border/60">
                  <div className="grid gap-3 border-b border-border/60 p-4 md:grid-cols-[220px_220px_140px]">
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Tipo de concessao</span>
                      <select aria-label="Tipo de concessao" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={grantsFilters.sourceType} onChange={(event) => setGrantsFilters((current) => ({ ...current, sourceType: event.target.value, page: 1 }))}>
                        <option value="all">Todos</option>
                        <option value="bonus">Bonus</option>
                        <option value="manual_override">Liberacao manual</option>
                        <option value="trial">Periodo de teste</option>
                      </select>
                    </label>
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Status da concessao</span>
                      <select aria-label="Status da concessao" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={grantsFilters.status} onChange={(event) => setGrantsFilters((current) => ({ ...current, status: event.target.value, page: 1 }))}>
                        <option value="all">Todos</option>
                        <option value="active">Ativo</option>
                        <option value="expired">Expirado</option>
                        <option value="revoked">Revogado</option>
                      </select>
                    </label>
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Concessoes por pagina</span>
                      <select aria-label="Concessoes por pagina" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={String(grantsFilters.perPage)} onChange={(event) => setGrantsFilters((current) => ({ ...current, perPage: Number(event.target.value), page: 1 }))}>
                        {[10, 25, 50].map((option) => <option key={option} value={option}>{option}</option>)}
                      </select>
                    </label>
                  </div>
                  {(grantsQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={Gift} title="Nenhuma concessao encontrada" description="Crie bonus ou liberacao manual quando houver necessidade operacional." />
                  ) : (
                    <>
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
                              <TableCell><Badge variant="outline">{formatGrantSourceLabel(grant.source_type)}</Badge></TableCell>
                              <TableCell>{formatUserStatusLabel(grant.status)}</TableCell>
                              <TableCell>{formatDate(grant.starts_at)} ate {formatDate(grant.ends_at)}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                      <TabPagination meta={grantsQuery.data?.meta} isFetching={grantsQuery.isFetching} onPrevious={() => setGrantsFilters((current) => ({ ...current, page: Math.max(1, current.page - 1) }))} onNext={() => setGrantsFilters((current) => ({ ...current, page: current.page + 1 }))} />
                    </>
                  )}
                </div>
              </TabsContent>

              <TabsContent value="activity">
                <div className="rounded-2xl border border-border/60">
                  <div className="grid gap-3 border-b border-border/60 p-4 md:grid-cols-[minmax(0,1fr)_160px]">
                    <Input aria-label="Buscar logs" placeholder="Buscar logs do parceiro" value={activityFilters.search} onChange={(event) => setActivityFilters((current) => ({ ...current, search: event.target.value, page: 1 }))} />
                    <label className="space-y-1 text-xs text-muted-foreground">
                      <span>Logs por pagina</span>
                      <select aria-label="Logs por pagina" className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={String(activityFilters.perPage)} onChange={(event) => setActivityFilters((current) => ({ ...current, perPage: Number(event.target.value), page: 1 }))}>
                        {[10, 25, 50].map((option) => <option key={option} value={option}>{option}</option>)}
                      </select>
                    </label>
                  </div>
                  {(activityQuery.data?.data ?? []).length === 0 ? (
                    <EmptyState icon={FileText} title="Nenhum log encontrado" description="As acoes administrativas aparecerao aqui." />
                  ) : (
                    <>
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
                      <TabPagination meta={activityQuery.data?.meta} isFetching={activityQuery.isFetching} onPrevious={() => setActivityFilters((current) => ({ ...current, page: Math.max(1, current.page - 1) }))} onNext={() => setActivityFilters((current) => ({ ...current, page: current.page + 1 }))} />
                    </>
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
