import { useDeferredValue, useEffect, useMemo, useState, useTransition } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2, Plus, RefreshCcw, Save, Sparkles, UsersRound } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/query-client';
import { getEventDetail } from '@/modules/events/api';
import { PageHeader } from '@/shared/components/PageHeader';

import { eventPeopleApi } from './api';
import type { EventPeopleCreatePayload, EventPeopleRelationPayload, EventPersonSide, EventPersonStatus, EventPersonType } from './types';

const TYPE_OPTIONS: Array<{ value: EventPersonType; label: string }> = [
  { value: 'guest', label: 'Convidado' }, { value: 'bride', label: 'Noiva' }, { value: 'groom', label: 'Noivo' },
  { value: 'mother', label: 'Mae' }, { value: 'father', label: 'Pai' }, { value: 'friend', label: 'Amigo(a)' },
  { value: 'groomsman', label: 'Padrinho' }, { value: 'bridesmaid', label: 'Madrinha' }, { value: 'vendor', label: 'Fornecedor' },
  { value: 'staff', label: 'Equipe' }, { value: 'speaker', label: 'Palestrante' }, { value: 'artist', label: 'Artista' }, { value: 'executive', label: 'Executivo' },
];
const SIDE_OPTIONS: Array<{ value: EventPersonSide; label: string }> = [
  { value: 'neutral', label: 'Neutro' }, { value: 'bride_side', label: 'Lado da noiva' }, { value: 'groom_side', label: 'Lado do noivo' },
  { value: 'host_side', label: 'Lado anfitriao' }, { value: 'company_side', label: 'Lado empresa' },
];
const STATUS_OPTIONS: Array<{ value: EventPersonStatus | 'all'; label: string }> = [
  { value: 'all', label: 'Todos' }, { value: 'active', label: 'Ativos' }, { value: 'draft', label: 'Rascunho' }, { value: 'hidden', label: 'Ocultos' },
];

function emptyDraft(): EventPeopleCreatePayload {
  return { display_name: '', type: 'guest', side: 'neutral', importance_rank: 0, notes: '', status: 'active' };
}

function relationLabel(type: string) {
  return ({
    spouse_of: 'Conjuge de', mother_of: 'Mae de', father_of: 'Pai de', sibling_of: 'Irmao de', child_of: 'Filho(a) de',
    friend_of: 'Amigo de', vendor_of_event: 'Fornecedor do evento', photographer_of_event: 'Fotografo do evento',
    ceremonialist_of_event: 'Cerimonialista do evento', works_with: 'Trabalha com', teammate_of: 'Colega de equipe',
    manager_of: 'Gestor de', speaker_with: 'Participa com', sponsor_of: 'Patrocinador de',
  } as Record<string, string>)[type] ?? type;
}

export default function EventPeoplePage() {
  const { id } = useParams<{ id: string }>();
  const eventId = id ?? '';
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [isUiPending, startUiTransition] = useTransition();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<EventPersonStatus | 'all'>('active');
  const [selectedPersonId, setSelectedPersonId] = useState<number | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const [draft, setDraft] = useState<EventPeopleCreatePayload>(emptyDraft());
  const [relationDraft, setRelationDraft] = useState<EventPeopleRelationPayload | null>(null);
  const deferredSearch = useDeferredValue(search);

  const peopleFilters = useMemo(() => ({ search: deferredSearch || undefined, status: statusFilter === 'all' ? undefined : statusFilter, per_page: 100 }), [deferredSearch, statusFilter]);
  const eventQuery = useQuery({ queryKey: queryKeys.events.detail(eventId || 'none'), queryFn: () => getEventDetail(eventId), enabled: eventId !== '' });
  const peopleQuery = useQuery({ queryKey: queryKeys.eventPeople.peopleList(eventId || 'none', peopleFilters), queryFn: () => eventPeopleApi.listPeople(eventId, peopleFilters), enabled: eventId !== '' });
  const allPeopleQuery = useQuery({ queryKey: queryKeys.eventPeople.peopleList(eventId || 'none', { per_page: 100 }), queryFn: () => eventPeopleApi.listPeople(eventId, { per_page: 100 }), enabled: eventId !== '' });
  const presetsQuery = useQuery({ queryKey: queryKeys.eventPeople.presets(eventId || 'none'), queryFn: () => eventPeopleApi.getPresets(eventId), enabled: eventId !== '', staleTime: 60_000 });
  const selectedPersonQuery = useQuery({ queryKey: queryKeys.eventPeople.personDetail(eventId || 'none', selectedPersonId ?? 'none'), queryFn: () => eventPeopleApi.getPerson(eventId, selectedPersonId as number), enabled: eventId !== '' && selectedPersonId !== null && !isCreating });

  const people = peopleQuery.data?.data ?? [];
  const allPeople = allPeopleQuery.data?.data ?? [];
  const selectedPerson = selectedPersonQuery.data ?? null;
  const selectedStats = selectedPerson?.stats?.[0] ?? null;

  useEffect(() => {
    if (!isCreating && selectedPersonId === null && people.length > 0) setSelectedPersonId(people[0].id);
  }, [isCreating, people, selectedPersonId]);

  useEffect(() => {
    if (!selectedPerson || isCreating) return;
    setDraft({ display_name: selectedPerson.display_name, type: selectedPerson.type ?? 'guest', side: selectedPerson.side ?? 'neutral', importance_rank: selectedPerson.importance_rank, notes: selectedPerson.notes ?? '', status: selectedPerson.status ?? 'active' });
    setRelationDraft({
      person_a_id: selectedPerson.id,
      person_b_id: 0,
      relation_type: presetsQuery.data?.relations?.[0]?.type ?? 'friend_of',
      directionality: presetsQuery.data?.relations?.[0]?.directionality ?? 'undirected',
      is_primary: false,
      notes: '',
    });
  }, [isCreating, presetsQuery.data?.relations, selectedPerson]);

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.peopleLists(eventId) });
    if (selectedPersonId !== null) void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.personDetail(eventId, selectedPersonId) });
  };

  const createPerson = useMutation({
    mutationFn: (payload: EventPeopleCreatePayload) => eventPeopleApi.createPerson(eventId, payload),
    onSuccess: (person) => { invalidate(); startUiTransition(() => { setIsCreating(false); setSelectedPersonId(person.id); }); toast({ title: 'Pessoa criada', description: 'Cadastro manual salvo localmente.' }); },
    onError: (error) => toast({ title: 'Falha ao criar pessoa', description: error instanceof ApiError ? error.message : 'Nao foi possivel criar a pessoa.', variant: 'destructive' }),
  });
  const updatePerson = useMutation({
    mutationFn: (payload: EventPeopleCreatePayload) => eventPeopleApi.updatePerson(eventId, selectedPersonId as number, payload),
    onSuccess: () => { invalidate(); toast({ title: 'Pessoa atualizada', description: 'Ajustes manuais salvos localmente.' }); },
    onError: (error) => toast({ title: 'Falha ao atualizar pessoa', description: error instanceof ApiError ? error.message : 'Nao foi possivel atualizar a pessoa.', variant: 'destructive' }),
  });
  const saveRelation = useMutation({
    mutationFn: (payload: EventPeopleRelationPayload) => eventPeopleApi.createRelation(eventId, payload),
    onSuccess: () => { invalidate(); toast({ title: 'Relacao criada', description: 'Vinculo manual salvo no evento.' }); },
    onError: (error) => toast({ title: 'Falha ao criar relacao', description: error instanceof ApiError ? error.message : 'Nao foi possivel criar a relacao.', variant: 'destructive' }),
  });
  const deleteRelation = useMutation({
    mutationFn: (relationId: number) => eventPeopleApi.deleteRelation(eventId, relationId),
    onSuccess: () => { invalidate(); toast({ title: 'Relacao removida', description: 'A relacao manual foi excluida.' }); },
    onError: (error) => toast({ title: 'Falha ao remover relacao', description: error instanceof ApiError ? error.message : 'Nao foi possivel remover a relacao.', variant: 'destructive' }),
  });

  const savePending = createPerson.isPending || updatePerson.isPending;
  const relationPending = saveRelation.isPending || deleteRelation.isPending;
  const otherPeople = allPeople.filter((person) => person.id !== selectedPerson?.id);

  const onSavePerson = () => {
    if (draft.display_name.trim() === '') return toast({ title: 'Nome obrigatorio', description: 'Informe o nome da pessoa.', variant: 'destructive' });
    const payload = { ...draft, display_name: draft.display_name.trim() };
    if (isCreating) return createPerson.mutate(payload);
    return updatePerson.mutate(payload);
  };

  const onSaveRelation = () => {
    if (!selectedPerson || !relationDraft || relationDraft.person_b_id <= 0) return toast({ title: 'Relacao incompleta', description: 'Escolha a outra pessoa para salvar.', variant: 'destructive' });
    saveRelation.mutate({ ...relationDraft, person_a_id: selectedPerson.id, notes: relationDraft.notes?.trim() || null });
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title={eventQuery.data ? `Pessoas de ${eventQuery.data.title}` : 'Pessoas do evento'}
        description="CRUD manual, relacoes declaradas, presets e representatives curados com sync AWS assincrono fora do hot path."
        actions={(
          <>
            <Button variant="outline" asChild><Link to={`/events/${eventId}`}><ArrowLeft className="h-4 w-4" />Voltar ao evento</Link></Button>
            <Button variant="outline" onClick={invalidate}><RefreshCcw className="h-4 w-4" />Atualizar</Button>
            <Button onClick={() => { setIsCreating(true); setSelectedPersonId(null); setDraft(emptyDraft()); }}><Plus className="h-4 w-4" />Nova pessoa</Button>
          </>
        )}
      />

      <div className="grid gap-4 xl:grid-cols-[340px_minmax(0,1fr)]">
        <Card className="border-border/60">
          <CardHeader className="space-y-4">
            <div className="flex items-center justify-between gap-3"><CardTitle>Catalogo local</CardTitle><Badge variant="outline">{peopleQuery.data?.meta.total ?? 0} pessoas</Badge></div>
            <div className="space-y-3">
              <div className="space-y-2"><Label htmlFor="event-people-search">Buscar pessoa</Label><Input id="event-people-search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Filtre sem tocar AWS" /></div>
              <div className="space-y-2"><Label>Status</Label><Select value={statusFilter} onValueChange={(value) => setStatusFilter(value as EventPersonStatus | 'all')}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{STATUS_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select></div>
            </div>
          </CardHeader>
          <CardContent>
            <ScrollArea className="h-[680px] pr-4">
              <div className="space-y-3">
                {peopleQuery.isLoading ? <div className="flex items-center justify-center py-10 text-sm text-muted-foreground"><Loader2 className="mr-2 h-4 w-4 animate-spin" />Carregando pessoas...</div> : null}
                {!peopleQuery.isLoading && people.length === 0 ? <div className="rounded-2xl border border-dashed border-border/60 px-4 py-8 text-center text-sm text-muted-foreground">Nenhuma pessoa com esse filtro.</div> : null}
                {people.map((person) => (
                  <button key={person.id} type="button" className={`w-full rounded-2xl border px-4 py-4 text-left transition ${selectedPersonId === person.id && !isCreating ? 'border-primary bg-primary/10' : 'border-border/60 bg-background hover:border-primary/40 hover:bg-primary/5'}`} onClick={() => startUiTransition(() => { setIsCreating(false); setSelectedPersonId(person.id); })}>
                    <div className="flex items-start justify-between gap-3">
                      <div><p className="font-medium">{person.display_name}</p><p className="text-xs text-muted-foreground">{[person.type, person.side].filter(Boolean).join(' - ') || 'Pessoa do evento'}</p></div>
                      <Badge variant="outline">{person.status}</Badge>
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">{person.stats?.[0]?.media_count ?? 0} midias - {person.representative_faces?.length ?? 0} reps</p>
                  </button>
                ))}
              </div>
            </ScrollArea>
          </CardContent>
        </Card>

        <div className="grid gap-4">
          <div className="grid gap-4 lg:grid-cols-3">
            <Card className="border-border/60"><CardHeader className="pb-2"><CardTitle className="text-sm">Pessoa</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{isCreating ? 'Nova pessoa' : selectedPerson?.display_name ?? 'Selecione'}</CardContent></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardTitle className="text-sm">Cobertura local</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{selectedStats?.media_count ?? 0}<span className="ml-2 text-sm font-normal text-muted-foreground">midias</span></CardContent></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardTitle className="text-sm">Representatives AWS</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{selectedPerson?.representative_faces?.length ?? 0}<span className="ml-2 text-sm font-normal text-muted-foreground">curados</span></CardContent></Card>
          </div>

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <Card className="border-border/60">
              <CardHeader className="flex flex-row items-center justify-between gap-3"><CardTitle>{isCreating ? 'Criar pessoa' : 'Cadastro manual e relacoes'}</CardTitle>{isUiPending ? <Badge variant="secondary">Atualizando UI</Badge> : null}</CardHeader>
              <CardContent className="space-y-5">
                <div className="grid gap-4 lg:grid-cols-2">
                  <div className="space-y-2"><Label htmlFor="person-name">Nome</Label><Input id="person-name" value={draft.display_name} onChange={(event) => setDraft((current) => ({ ...current, display_name: event.target.value }))} placeholder="Ex.: Mae da noiva" /></div>
                  <div className="space-y-2"><Label>Tipo</Label><Select value={String(draft.type ?? 'guest')} onValueChange={(value) => setDraft((current) => ({ ...current, type: value }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{TYPE_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select></div>
                  <div className="space-y-2"><Label>Lado</Label><Select value={String(draft.side ?? 'neutral')} onValueChange={(value) => setDraft((current) => ({ ...current, side: value as EventPersonSide }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{SIDE_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select></div>
                  <div className="space-y-2"><Label>Status</Label><Select value={String(draft.status ?? 'active')} onValueChange={(value) => setDraft((current) => ({ ...current, status: value as EventPersonStatus }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{STATUS_OPTIONS.filter((option) => option.value !== 'all').map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select></div>
                  <div className="space-y-2 lg:col-span-2"><Label htmlFor="person-notes">Notas</Label><Textarea id="person-notes" value={draft.notes ?? ''} onChange={(event) => setDraft((current) => ({ ...current, notes: event.target.value }))} placeholder="Observacoes operacionais da pessoa" /></div>
                </div>
                <div className="flex flex-wrap gap-2"><Button onClick={onSavePerson} disabled={savePending}>{savePending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}{isCreating ? 'Criar pessoa' : 'Salvar ajustes'}</Button>{!isCreating ? <Button variant="outline" onClick={() => { setIsCreating(true); setSelectedPersonId(null); setDraft(emptyDraft()); }}><Plus className="h-4 w-4" />Nova pessoa manual</Button> : null}</div>

                {!isCreating && selectedPerson ? (
                  <>
                    <div className="grid gap-3 md:grid-cols-4">
                      <div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Solo</p><p className="mt-1 text-lg font-semibold">{selectedStats?.solo_media_count ?? 0}</p></div>
                      <div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Com outras</p><p className="mt-1 text-lg font-semibold">{selectedStats?.with_others_media_count ?? 0}</p></div>
                      <div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Publicadas</p><p className="mt-1 text-lg font-semibold">{selectedStats?.published_media_count ?? 0}</p></div>
                      <div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Pendentes</p><p className="mt-1 text-lg font-semibold">{selectedStats?.pending_media_count ?? 0}</p></div>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                      <div className="space-y-3">
                        <div className="flex items-center justify-between gap-3"><h3 className="font-semibold">Relacoes manuais</h3><Badge variant="outline">{selectedPerson.relations?.length ?? 0} relacoes</Badge></div>
                        {(selectedPerson.relations ?? []).length === 0 ? <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">Nenhuma relacao manual para essa pessoa.</div> : null}
                        {(selectedPerson.relations ?? []).map((relation) => (
                          <div key={relation.id} className="rounded-2xl border border-border/60 bg-background/70 px-4 py-4">
                            <div className="flex items-center justify-between gap-3"><div><p className="font-medium">{relationLabel(relation.relation_type)}</p><p className="text-sm text-muted-foreground">{relation.other_person?.display_name ?? relation.person_b?.display_name ?? 'Pessoa relacionada'}</p></div><Button variant="outline" size="sm" onClick={() => deleteRelation.mutate(relation.id)} disabled={relationPending}>Remover</Button></div>
                            {relation.notes ? <p className="mt-3 text-sm text-muted-foreground">{relation.notes}</p> : null}
                          </div>
                        ))}
                      </div>

                      <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
                        <p className="font-medium">Nova relacao</p>
                        <p className="mt-1 text-sm text-muted-foreground">Separada da coocorrencia inferida.</p>
                        <div className="mt-4 space-y-3">
                          <div className="space-y-2"><Label>Outra pessoa</Label><Select value={relationDraft?.person_b_id ? String(relationDraft.person_b_id) : '__unselected__'} onValueChange={(value) => setRelationDraft((current) => current ? ({ ...current, person_b_id: value === '__unselected__' ? 0 : Number(value) }) : current)}><SelectTrigger><SelectValue placeholder="Escolha a pessoa" /></SelectTrigger><SelectContent><SelectItem value="__unselected__">Escolha a pessoa</SelectItem>{otherPeople.map((person) => <SelectItem key={person.id} value={String(person.id)}>{person.display_name}</SelectItem>)}</SelectContent></Select></div>
                          <div className="space-y-2"><Label>Tipo de relacao</Label><Select value={relationDraft?.relation_type ?? '__unselected__'} onValueChange={(value) => { if (value === '__unselected__') return; const preset = presetsQuery.data?.relations?.find((item) => item.type === value); setRelationDraft((current) => current ? ({ ...current, relation_type: value, directionality: preset?.directionality ?? current.directionality }) : current); }}><SelectTrigger><SelectValue placeholder="Escolha o tipo" /></SelectTrigger><SelectContent><SelectItem value="__unselected__">Escolha o tipo</SelectItem>{(presetsQuery.data?.relations ?? []).map((item) => <SelectItem key={item.type} value={item.type}>{item.label}</SelectItem>)}</SelectContent></Select></div>
                          <div className="space-y-2"><Label>Notas</Label><Textarea value={relationDraft?.notes ?? ''} onChange={(event) => setRelationDraft((current) => current ? ({ ...current, notes: event.target.value }) : current)} placeholder="Contexto da relacao" /></div>
                          <Button onClick={onSaveRelation} disabled={relationPending}>{relationPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <UsersRound className="h-4 w-4" />}Criar relacao</Button>
                        </div>
                      </div>
                    </div>
                  </>
                ) : null}
              </CardContent>
            </Card>

            <div className="grid gap-4">
              <Card className="border-border/60">
                <CardHeader><CardTitle>Presets do evento</CardTitle></CardHeader>
                <CardContent className="space-y-2">
                  <p className="text-sm text-muted-foreground">Crie rapidamente pessoas importantes sem cair em formulario vazio.</p>
                  {(presetsQuery.data?.people ?? []).map((preset) => (
                    <button key={preset.key} type="button" className="w-full rounded-2xl border border-border/60 bg-background px-4 py-3 text-left transition hover:border-primary/40 hover:bg-primary/5" onClick={() => createPerson.mutate({ display_name: preset.label, type: preset.type, side: preset.side, importance_rank: preset.importance_rank, status: 'active' })}>
                      <div className="flex items-center justify-between gap-3"><div><p className="font-medium">{preset.label}</p><p className="text-xs text-muted-foreground">{[preset.type, preset.side].join(' - ')}</p></div><Sparkles className="h-4 w-4 text-primary" /></div>
                    </button>
                  ))}
                </CardContent>
              </Card>

              <Card className="border-border/60">
                <CardHeader><CardTitle>Representatives AWS</CardTitle></CardHeader>
                <CardContent className="space-y-2">
                  <p className="text-sm text-muted-foreground">Faces curadas localmente; sync remoto sempre fora do hot path.</p>
                  {(selectedPerson?.representative_faces ?? []).length === 0 ? <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">Nenhuma face representativa curada para a pessoa selecionada.</div> : null}
                  {(selectedPerson?.representative_faces ?? []).map((representative) => (
                    <div key={representative.id} className="rounded-2xl border border-border/60 bg-background px-4 py-3">
                      <div className="flex items-center justify-between gap-3"><div><p className="font-medium">Rosto #{(representative.face?.face_index ?? 0) + 1}</p><p className="text-xs text-muted-foreground">score {representative.rank_score.toFixed(2)}{representative.face?.event_media_id ? ` - midia #${representative.face.event_media_id}` : ''}</p></div><Badge variant={representative.sync_status === 'synced' ? 'secondary' : representative.sync_status === 'failed' ? 'destructive' : 'outline'}>{representative.sync_status}</Badge></div>
                    </div>
                  ))}
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
