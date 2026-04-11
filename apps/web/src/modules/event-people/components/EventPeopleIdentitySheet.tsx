import { useDeferredValue, useEffect, useMemo, useState, useTransition } from 'react';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Loader2, ScanFace, Split, UserPlus2, UsersRound } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { queryKeys } from '@/lib/query-client';

import { eventPeopleApi } from '../api';
import {
  EVENT_PERSON_SIDE_OPTIONS,
  EVENT_PERSON_TYPE_OPTIONS,
  formatEventPersonAssignmentStatus,
  formatEventPersonMeta,
  formatEventPersonQualityTier,
  formatEventPersonReviewStatus,
} from '../labels';
import type {
  EventMediaFacePeople,
  EventPeopleCreatePayload,
  EventPerson,
  EventPersonConflictCandidate,
  EventPersonSide,
  EventPersonType,
} from '../types';

export interface EventPeopleIdentitySheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  eventId?: number | string | null;
  face: EventMediaFacePeople | null;
  pendingAction?: 'confirm' | 'create' | 'ignore' | 'split' | 'merge' | null;
  onConfirmExisting: (personId: number) => void;
  onCreatePerson: (payload: EventPeopleCreatePayload) => void;
  onIgnore: () => void;
  onSplit: () => void;
  onMerge: (sourcePersonId: number, targetPersonId: number) => void;
}

function candidateToPerson(candidate: EventPersonConflictCandidate): EventPerson {
  return {
    id: candidate.id,
    event_id: 0,
    display_name: candidate.display_name,
    slug: String(candidate.id),
    type: candidate.type,
    side: candidate.side,
    avatar_media_id: null,
    avatar_face_id: null,
    importance_rank: 0,
    notes: null,
    status: candidate.status ?? 'active',
    created_at: null,
    updated_at: null,
  };
}

function buildCandidatePool(face: EventMediaFacePeople | null, people: EventPerson[]): EventPerson[] {
  if (!face) return people;

  const map = new Map<number, EventPerson>();

  people.forEach((person) => {
    map.set(person.id, person);
  });

  if (face.current_assignment?.person) {
    map.set(face.current_assignment.person.id, face.current_assignment.person);
  }

  (face.review_item?.payload.candidate_people ?? []).forEach((candidate) => {
    map.set(candidate.id, candidateToPerson(candidate));
  });

  return Array.from(map.values());
}

export function EventPeopleIdentitySheet({
  open,
  onOpenChange,
  eventId,
  face,
  pendingAction = null,
  onConfirmExisting,
  onCreatePerson,
  onIgnore,
  onSplit,
  onMerge,
}: EventPeopleIdentitySheetProps) {
  const [search, setSearch] = useState('');
  const [selectedPersonId, setSelectedPersonId] = useState<number | null>(null);
  const [draftName, setDraftName] = useState('');
  const [draftType, setDraftType] = useState<EventPersonType>('guest');
  const [draftSide, setDraftSide] = useState<EventPersonSide>('neutral');
  const [mergeSourcePersonId, setMergeSourcePersonId] = useState<number | null>(null);
  const [mergeTargetPersonId, setMergeTargetPersonId] = useState<number | null>(null);
  const [tab, setTab] = useState<'existing' | 'new' | 'conflict'>('existing');
  const [isTabPending, startTabTransition] = useTransition();

  const deferredSearch = useDeferredValue(search);
  const reviewItem = face?.review_item ?? null;
  const isConflict = reviewItem?.type === 'identity_conflict' || reviewItem?.status === 'conflict';
  const changeTab = (nextTab: 'existing' | 'new' | 'conflict') => {
    startTabTransition(() => setTab(nextTab));
  };

  const peopleQuery = useQuery({
    queryKey: queryKeys.eventPeople.peopleList(eventId ?? 'none', {
      search: deferredSearch || undefined,
      status: 'active',
      per_page: 12,
    }),
    queryFn: () => eventPeopleApi.listPeople(eventId as number | string, {
      search: deferredSearch || undefined,
      status: 'active',
      per_page: 12,
    }),
    enabled: open && !!eventId,
    staleTime: 15_000,
  });

  const people = useMemo(() => buildCandidatePool(face, peopleQuery.data?.data ?? []), [face, peopleQuery.data?.data]);

  useEffect(() => {
    if (!open) return;

    setSearch('');
    setDraftName('');
    setDraftType('guest');
    setDraftSide('neutral');
    setSelectedPersonId(face?.current_assignment?.person?.id ?? null);

    const candidateIds = (face?.review_item?.payload.candidate_people ?? []).map((candidate) => candidate.id);
    const currentPersonId = face?.current_assignment?.person?.id ?? face?.review_item?.payload.current_person_id ?? null;

    if (isConflict && candidateIds.length >= 2) {
      const defaultTarget = currentPersonId && candidateIds.includes(currentPersonId) ? currentPersonId : candidateIds[0];
      const defaultSource = candidateIds.find((candidateId) => candidateId !== defaultTarget) ?? candidateIds[0];

      setMergeSourcePersonId(defaultSource);
      setMergeTargetPersonId(defaultTarget);
      setTab('conflict');

      return;
    }

    setMergeSourcePersonId(null);
    setMergeTargetPersonId(null);
    setTab('existing');
  }, [face?.current_assignment, face?.id, face?.review_item?.payload.candidate_people, face?.review_item?.payload.current_person_id, isConflict, open]);

  const activeAction = pendingAction !== null;
  const createDisabled = draftName.trim() === '' || activeAction;
  const confirmDisabled = selectedPersonId === null || activeAction;
  const mergeDisabled = !mergeSourcePersonId || !mergeTargetPersonId || mergeSourcePersonId === mergeTargetPersonId || activeAction;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full overflow-y-auto p-0 sm:max-w-xl">
        <div className="flex h-full flex-col">
          <SheetHeader className="border-b border-border/60 px-6 py-5">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={isConflict ? 'destructive' : 'outline'}>
                {isConflict ? 'Conflito de identidade' : 'Confirmacao guiada'}
              </Badge>
                {reviewItem?.status ? <Badge variant="secondary">{formatEventPersonReviewStatus(reviewItem.status)}</Badge> : null}
              {face?.quality.tier ? <Badge variant="outline">{formatEventPersonQualityTier(face.quality.tier)}</Badge> : null}
            </div>
            <SheetTitle className="mt-2">Quem e esta pessoa?</SheetTitle>
            <SheetDescription>
              {reviewItem?.payload.question || 'Confirme a identidade do rosto, ajuste a pessoa certa ou reabra a revisao.'}
            </SheetDescription>
          </SheetHeader>

          <div className="flex-1 space-y-5 px-6 py-5">
            {!face ? (
              <div className="rounded-2xl border border-border/60 bg-background/80 px-4 py-6 text-sm text-muted-foreground">
                Selecione um rosto no overlay ou na inbox para revisar a identidade.
              </div>
            ) : (
              <>
                <div className="grid gap-3 rounded-2xl border border-border/60 bg-background/80 p-4 text-sm sm:grid-cols-2">
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Rosto</p>
                    <p className="mt-1 font-medium">#{face.face_index + 1}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Qualidade</p>
                    <p className="mt-1 font-medium">
                      {face.quality.score !== null && face.quality.score !== undefined
                        ? `${Math.round(face.quality.score * 100)}%`
                        : 'Nao informado'}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Identidade atual</p>
                    <p className="mt-1 font-medium">
                      {face.current_assignment?.person?.display_name || 'Sem confirmacao'}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Tamanho do rosto</p>
                    <p className="mt-1 font-medium">
                      {Math.round(face.bbox.w * 100)}% × {Math.round(face.bbox.h * 100)}%
                    </p>
                  </div>
                </div>

                <Tabs value={tab} onValueChange={(value) => changeTab(value as 'existing' | 'new' | 'conflict')}>
                  <TabsList className="grid w-full grid-cols-3">
                    <TabsTrigger value="existing" onClick={() => changeTab('existing')}>Pessoa existente</TabsTrigger>
                    <TabsTrigger value="new" onClick={() => changeTab('new')}>Criar pessoa</TabsTrigger>
                    <TabsTrigger value="conflict" disabled={!isConflict} onClick={() => changeTab('conflict')}>Conflito</TabsTrigger>
                  </TabsList>

                  <TabsContent value="existing" className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="event-people-search">Buscar pessoa no evento</Label>
                      <Input
                        id="event-people-search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Digite o nome da pessoa"
                      />
                    </div>

                    <div className="rounded-2xl border border-border/60 bg-background/80">
                      <ScrollArea className="h-72">
                        <div className="space-y-2 p-3">
                          {peopleQuery.isLoading ? (
                            <div className="flex items-center justify-center py-10 text-sm text-muted-foreground">
                              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                              Carregando pessoas...
                            </div>
                          ) : null}

                          {!peopleQuery.isLoading && people.length === 0 ? (
                            <div className="py-10 text-center text-sm text-muted-foreground">
                              Nenhuma pessoa encontrada com esse filtro.
                            </div>
                          ) : null}

                          {people.map((person) => {
                            const selected = selectedPersonId === person.id;

                            return (
                              <button
                                key={person.id}
                                type="button"
                                className={`w-full rounded-2xl border px-3 py-3 text-left transition ${
                                  selected
                                    ? 'border-primary bg-primary/10'
                                    : 'border-border/50 bg-background hover:border-primary/40 hover:bg-primary/5'
                                }`}
                                onClick={() => setSelectedPersonId(person.id)}
                              >
                                <div className="flex items-center justify-between gap-3">
                                  <div>
                                    <p className="font-medium">{person.display_name}</p>
                                    <p className="text-xs text-muted-foreground">
                                  {formatEventPersonMeta(person, 'Pessoa ativa do evento')}
                                    </p>
                                  </div>
                                  {selected ? <Badge>Selecionada</Badge> : null}
                                </div>
                              </button>
                            );
                          })}
                        </div>
                      </ScrollArea>
                    </div>

                    <Button type="button" className="w-full" disabled={confirmDisabled} onClick={() => selectedPersonId && onConfirmExisting(selectedPersonId)}>
                      {pendingAction === 'confirm' ? <Loader2 className="h-4 w-4 animate-spin" /> : <UsersRound className="h-4 w-4" />}
                      Confirmar nessa pessoa
                    </Button>
                  </TabsContent>

                  <TabsContent value="new" className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="event-people-name">Nome da pessoa</Label>
                      <Input
                        id="event-people-name"
                        value={draftName}
                        onChange={(event) => setDraftName(event.target.value)}
                        placeholder="Ex.: Mae da noiva"
                      />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                      <div className="space-y-2">
                        <Label>Tipo</Label>
                        <Select value={draftType} onValueChange={(value) => setDraftType(value as EventPersonType)}>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {EVENT_PERSON_TYPE_OPTIONS.map((option) => (
                              <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label>Lado</Label>
                        <Select value={draftSide} onValueChange={(value) => setDraftSide(value as EventPersonSide)}>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {EVENT_PERSON_SIDE_OPTIONS.map((option) => (
                              <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    <Button
                      type="button"
                      className="w-full"
                      disabled={createDisabled}
                      onClick={() => onCreatePerson({
                        display_name: draftName.trim(),
                        type: draftType,
                        side: draftSide,
                      })}
                    >
                      {pendingAction === 'create' ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserPlus2 className="h-4 w-4" />}
                      Criar pessoa e confirmar
                    </Button>
                  </TabsContent>

                  <TabsContent value="conflict" className="space-y-4">
                    <div className="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm">
                      <div className="flex items-center gap-2 font-medium text-destructive">
                        <AlertTriangle className="h-4 w-4" />
                        Identidades concorrentes para o mesmo rosto
                      </div>
                      <p className="mt-2 text-muted-foreground">
                        Revise as pessoas abaixo e escolha qual cadastro deve continuar.
                      </p>
                    </div>

                    <div className="space-y-3">
                      {(reviewItem?.payload.candidate_people ?? []).map((candidate) => (
                        <div key={candidate.id} className="rounded-2xl border border-border/60 bg-background/80 px-3 py-3">
                          <p className="font-medium">{candidate.display_name}</p>
                          <p className="text-xs text-muted-foreground">
                            {[formatEventPersonAssignmentStatus(candidate.assignment_status), formatEventPersonMeta(candidate, '')].filter(Boolean).join(' - ') || 'Candidata ao ajuste'}
                          </p>
                        </div>
                      ))}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                      <div className="space-y-2">
                        <Label>Origem</Label>
                        <Select value={mergeSourcePersonId ? String(mergeSourcePersonId) : undefined} onValueChange={(value) => setMergeSourcePersonId(Number(value))}>
                          <SelectTrigger>
                            <SelectValue placeholder="Escolha a origem" />
                          </SelectTrigger>
                          <SelectContent>
                            {people.map((person) => (
                              <SelectItem key={person.id} value={String(person.id)}>{person.display_name}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label>Destino</Label>
                        <Select value={mergeTargetPersonId ? String(mergeTargetPersonId) : undefined} onValueChange={(value) => setMergeTargetPersonId(Number(value))}>
                          <SelectTrigger>
                            <SelectValue placeholder="Escolha o destino" />
                          </SelectTrigger>
                          <SelectContent>
                            {people.map((person) => (
                              <SelectItem key={person.id} value={String(person.id)}>{person.display_name}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    <Button type="button" className="w-full" disabled={mergeDisabled} onClick={() => mergeSourcePersonId && mergeTargetPersonId && onMerge(mergeSourcePersonId, mergeTargetPersonId)}>
                      {pendingAction === 'merge' ? <Loader2 className="h-4 w-4 animate-spin" /> : <UsersRound className="h-4 w-4" />}
                      Mesclar pessoas
                    </Button>
                  </TabsContent>
                </Tabs>
              </>
            )}
          </div>

          <SheetFooter className="border-t border-border/60 px-6 py-4">
            <div className="flex w-full flex-col gap-2 sm:flex-row sm:justify-between sm:space-x-0">
              <div className="flex flex-col gap-2 sm:flex-row">
                <Button type="button" variant="outline" disabled={!reviewItem || activeAction} onClick={onIgnore}>
                  {pendingAction === 'ignore' ? <Loader2 className="h-4 w-4 animate-spin" /> : <ScanFace className="h-4 w-4" />}
                  Ignorar rosto
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  disabled={!face?.current_assignment || !reviewItem || activeAction}
                  onClick={onSplit}
                >
                  {pendingAction === 'split' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Split className="h-4 w-4" />}
                  Reabrir revisao
                </Button>
              </div>
              {isTabPending ? (
                <div className="flex items-center text-xs text-muted-foreground">
                  <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                  Atualizando painel
                </div>
              ) : null}
            </div>
          </SheetFooter>
        </div>
      </SheetContent>
    </Sheet>
  );
}

export default EventPeopleIdentitySheet;
