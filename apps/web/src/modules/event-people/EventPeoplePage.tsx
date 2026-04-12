import { useDeferredValue, useEffect, useMemo, useState, useTransition } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft,
  Camera,
  CheckCircle2,
  Fingerprint,
  HeartHandshake,
  Image,
  ImagePlus,
  Loader2,
  Plus,
  RefreshCcw,
  Save,
  Sparkles,
  TriangleAlert,
  UploadCloud,
  UserCircle2,
  UsersRound,
} from 'lucide-react';
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
import {
  appendOptimisticRelation,
  removeOptimisticRelation,
  replaceEventPeopleDetail,
  restoreEventPeopleCache,
  snapshotEventPeopleCache,
  upsertPersonInEventPeopleLists,
} from './cache';
import {
  EVENT_PERSON_SIDE_OPTIONS,
  EVENT_PERSON_STATUS_OPTIONS,
  EVENT_PERSON_TYPE_OPTIONS,
  formatEventPersonMeta,
  formatEventPersonReferencePurpose,
  formatEventPersonReferenceStatus,
  formatEventPersonRelationType,
  formatEventPersonStatus,
  formatEventPersonSyncStatus,
} from './labels';
import type {
  EventPeopleCreatePayload,
  EventPeopleRelationPayload,
  EventPerson,
  EventPersonReferencePhotoCandidate,
  EventPersonRelation,
  EventPersonSide,
  EventPersonStatus,
  EventPersonType,
} from './types';

type MutationSnapshot = ReturnType<typeof snapshotEventPeopleCache>;
type OperationTone = 'neutral' | 'success' | 'warning' | 'danger';

interface PersistentOperationStatus {
  title: string;
  description: string;
  tone: OperationTone;
}

function emptyDraft(): EventPeopleCreatePayload {
  return { display_name: '', type: 'guest', side: 'neutral', importance_rank: 0, notes: '', status: 'active' };
}

function countLabel(count: number | null | undefined, singular: string, plural: string) {
  const safe = count ?? 0;
  return `${safe} ${safe === 1 ? singular : plural}`;
}

function buildOptimisticPerson(id: number, eventId: number, payload: EventPeopleCreatePayload): EventPerson {
  return {
    id,
    event_id: eventId,
    display_name: payload.display_name,
    slug: `optimistic-${Math.abs(id)}`,
    type: payload.type ?? 'guest',
    side: payload.side ?? 'neutral',
    avatar_media_id: null,
    avatar_face_id: null,
    avatar: { media_id: null, face_id: null },
    importance_rank: payload.importance_rank ?? 0,
    notes: payload.notes ?? null,
    status: payload.status ?? 'active',
    primary_photo: null,
    stats: [],
    reference_photos: [],
    representative_faces: [],
    relations: [],
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  };
}

function statusCardClass(tone: OperationTone) {
  if (tone === 'success') return 'border-emerald-300/70 bg-emerald-50 text-emerald-900';
  if (tone === 'warning') return 'border-amber-300/70 bg-amber-50 text-amber-900';
  if (tone === 'danger') return 'border-rose-300/70 bg-rose-50 text-rose-900';

  return 'border-border/60 bg-background text-foreground';
}

export default function EventPeoplePage() {
  const { id } = useParams<{ id: string }>();
  const eventId = id ?? '';
  const numericEventId = Number(eventId || 0);
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [isUiPending, startUiTransition] = useTransition();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<EventPersonStatus | 'all'>('active');
  const [selectedPersonId, setSelectedPersonId] = useState<number | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const [draft, setDraft] = useState<EventPeopleCreatePayload>(emptyDraft());
  const [relationDraft, setRelationDraft] = useState<EventPeopleRelationPayload | null>(null);
  const [operationStatus, setOperationStatus] = useState<PersistentOperationStatus | null>(null);
  const [showGalleryCandidates, setShowGalleryCandidates] = useState(false);
  const [referenceUploadFile, setReferenceUploadFile] = useState<File | null>(null);
  const deferredSearch = useDeferredValue(search);

  const peopleFilters = useMemo(
    () => ({ search: deferredSearch || undefined, status: statusFilter === 'all' ? undefined : statusFilter, per_page: 100 }),
    [deferredSearch, statusFilter],
  );

  const eventQuery = useQuery({
    queryKey: queryKeys.events.detail(eventId || 'none'),
    queryFn: () => getEventDetail(eventId),
    enabled: eventId !== '',
  });
  const peopleQuery = useQuery({
    queryKey: queryKeys.eventPeople.peopleList(eventId || 'none', peopleFilters),
    queryFn: () => eventPeopleApi.listPeople(eventId, peopleFilters),
    enabled: eventId !== '',
  });
  const allPeopleQuery = useQuery({
    queryKey: queryKeys.eventPeople.peopleList(eventId || 'none', { per_page: 100 }),
    queryFn: () => eventPeopleApi.listPeople(eventId, { per_page: 100 }),
    enabled: eventId !== '',
  });
  const presetsQuery = useQuery({
    queryKey: queryKeys.eventPeople.presets(eventId || 'none'),
    queryFn: () => eventPeopleApi.getPresets(eventId),
    enabled: eventId !== '',
    staleTime: 60_000,
  });
  const operationalStatusQuery = useQuery({
    queryKey: queryKeys.eventPeople.operationalStatus(eventId || 'none'),
    queryFn: () => eventPeopleApi.getOperationalStatus(eventId),
    enabled: eventId !== '',
    staleTime: 10_000,
    refetchInterval: 15_000,
  });
  const selectedPersonQuery = useQuery({
    queryKey: queryKeys.eventPeople.personDetail(eventId || 'none', selectedPersonId ?? 'none'),
    queryFn: () => eventPeopleApi.getPerson(eventId, selectedPersonId as number),
    enabled: eventId !== '' && selectedPersonId !== null && !isCreating,
  });
  const referenceCandidatesQuery = useQuery({
    queryKey: queryKeys.eventPeople.referencePhotoCandidates(eventId || 'none', selectedPersonId ?? 'none'),
    queryFn: () => eventPeopleApi.listReferencePhotoCandidates(eventId, selectedPersonId as number),
    enabled: eventId !== '' && selectedPersonId !== null && showGalleryCandidates && !isCreating,
  });

  const people = peopleQuery.data?.data ?? [];
  const allPeople = allPeopleQuery.data?.data ?? [];
  const selectedPerson = selectedPersonQuery.data ?? null;
  const selectedStats = selectedPerson?.stats?.[0] ?? null;
  const selectedReferencePhotos = (selectedPerson?.reference_photos ?? []).filter((photo) => photo.status === 'active');
  const primaryReferencePhotoId = selectedPerson?.primary_photo?.reference_photo_id ?? null;
  const selectedRepresentativeFaces = selectedPerson?.representative_faces ?? [];
  const referenceCandidates = referenceCandidatesQuery.data ?? [];
  const otherPeople = allPeople.filter((person) => person.id !== selectedPerson?.id);
  const operationalStatus = operationalStatusQuery.data;

  useEffect(() => {
    if (!isCreating && selectedPersonId === null && people.length > 0) setSelectedPersonId(people[0].id);
  }, [isCreating, people, selectedPersonId]);

  useEffect(() => {
    if (!selectedPerson || isCreating) return;

    setDraft({
      display_name: selectedPerson.display_name,
      type: selectedPerson.type ?? 'guest',
      side: selectedPerson.side ?? 'neutral',
      importance_rank: selectedPerson.importance_rank,
      notes: selectedPerson.notes ?? '',
      status: selectedPerson.status ?? 'active',
    });
    setShowGalleryCandidates(false);
    setReferenceUploadFile(null);
    setRelationDraft({
      person_a_id: selectedPerson.id,
      person_b_id: 0,
      relation_type: presetsQuery.data?.relations?.[0]?.type ?? 'friend_of',
      directionality: presetsQuery.data?.relations?.[0]?.directionality ?? 'undirected',
      is_primary: false,
      notes: '',
    });
  }, [isCreating, presetsQuery.data?.relations, selectedPerson]);

  const invalidateEventPeopleData = (personId: number | null = selectedPersonId) => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.peopleLists(eventId) });
    void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.operationalStatus(eventId) });

    if (personId !== null) {
      void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.personDetail(eventId, personId) });
    }
  };

  const startCreate = () => {
    setIsCreating(true);
    setSelectedPersonId(null);
    setDraft(emptyDraft());
  };

  const selectPerson = (personId: number) => startUiTransition(() => {
    setIsCreating(false);
    setSelectedPersonId(personId);
  });

  const createPerson = useMutation<EventPerson, unknown, EventPeopleCreatePayload, { snapshot: MutationSnapshot }>({
    mutationFn: (payload) => eventPeopleApi.createPerson(eventId, payload),
    onMutate: async (payload) => {
      await queryClient.cancelQueries({ queryKey: queryKeys.eventPeople.peopleLists(eventId) });
      const snapshot = snapshotEventPeopleCache(queryClient, eventId, selectedPersonId);
      upsertPersonInEventPeopleLists(queryClient, eventId, buildOptimisticPerson(-Date.now(), numericEventId, payload));
      setOperationStatus({
        tone: 'neutral',
        title: 'Cadastro salvo localmente',
        description: 'A pessoa ja apareceu no painel. As projecoes continuam sendo reconciliadas.',
      });
      return { snapshot };
    },
    onSuccess: (person) => {
      upsertPersonInEventPeopleLists(queryClient, eventId, person);
      replaceEventPeopleDetail(queryClient, eventId, person);
      startUiTransition(() => {
        setIsCreating(false);
        setSelectedPersonId(person.id);
      });
      setOperationStatus({
        tone: 'success',
        title: 'Pessoa criada',
        description: 'O cadastro foi confirmado localmente e ja ficou visivel para a operacao.',
      });
      toast({ title: 'Pessoa criada', description: 'O cadastro foi salvo e ja apareceu na lista.' });
    },
    onError: (error, _variables, context) => {
      if (context) restoreEventPeopleCache(queryClient, context.snapshot);
      setOperationStatus({
        tone: 'danger',
        title: 'Falha ao criar pessoa',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar esse cadastro agora.',
      });
      toast({
        title: 'Falha ao criar pessoa',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar esse cadastro agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => invalidateEventPeopleData(),
  });

  const updatePerson = useMutation<EventPerson, unknown, EventPeopleCreatePayload, { snapshot: MutationSnapshot }>({
    mutationFn: (payload) => eventPeopleApi.updatePerson(eventId, selectedPersonId as number, payload),
    onMutate: async (payload) => {
      await queryClient.cancelQueries({ queryKey: queryKeys.eventPeople.peopleLists(eventId) });
      if (selectedPersonId !== null) {
        await queryClient.cancelQueries({ queryKey: queryKeys.eventPeople.personDetail(eventId, selectedPersonId) });
      }
      const snapshot = snapshotEventPeopleCache(queryClient, eventId, selectedPersonId);
      if (selectedPerson) {
        const optimisticPerson: EventPerson = {
          ...selectedPerson,
          display_name: payload.display_name,
          type: payload.type ?? selectedPerson.type,
          side: payload.side ?? selectedPerson.side,
          importance_rank: payload.importance_rank ?? selectedPerson.importance_rank,
          notes: payload.notes ?? selectedPerson.notes,
          status: payload.status ?? selectedPerson.status,
          updated_at: new Date().toISOString(),
        };
        upsertPersonInEventPeopleLists(queryClient, eventId, optimisticPerson);
        replaceEventPeopleDetail(queryClient, eventId, optimisticPerson);
      }
      setOperationStatus({
        tone: 'neutral',
        title: 'Ajustes aplicados localmente',
        description: 'O painel foi atualizado na hora. O restante segue para reconciliacao.',
      });
      return { snapshot };
    },
    onSuccess: (person) => {
      upsertPersonInEventPeopleLists(queryClient, eventId, person);
      replaceEventPeopleDetail(queryClient, eventId, person);
      setOperationStatus({
        tone: 'success',
        title: 'Cadastro atualizado',
        description: 'As informacoes da pessoa foram salvas e permanecem visiveis no cockpit.',
      });
      toast({ title: 'Cadastro atualizado', description: 'As informacoes da pessoa foram salvas.' });
    },
    onError: (error, _variables, context) => {
      if (context) restoreEventPeopleCache(queryClient, context.snapshot);
      setOperationStatus({
        tone: 'danger',
        title: 'Falha ao atualizar pessoa',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel atualizar esse cadastro agora.',
      });
      toast({
        title: 'Falha ao atualizar pessoa',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel atualizar esse cadastro agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => invalidateEventPeopleData(),
  });

  const saveRelation = useMutation<EventPersonRelation, unknown, EventPeopleRelationPayload, { snapshot: MutationSnapshot }>({
    mutationFn: (payload) => eventPeopleApi.createRelation(eventId, payload),
    onMutate: async (payload) => {
      const snapshot = snapshotEventPeopleCache(queryClient, eventId, selectedPersonId);
      if (selectedPersonId !== null) {
        await queryClient.cancelQueries({ queryKey: queryKeys.eventPeople.personDetail(eventId, selectedPersonId) });
        const otherPerson = allPeople.find((person) => person.id === payload.person_b_id);
        appendOptimisticRelation(queryClient, eventId, selectedPersonId, {
          id: -Date.now(),
          event_id: numericEventId,
          person_pair_key: `optimistic:${payload.person_a_id}:${payload.person_b_id}`,
          relation_type: payload.relation_type,
          directionality: payload.directionality ?? 'undirected',
          source: 'manual',
          confidence: payload.confidence ?? null,
          strength: payload.strength ?? null,
          is_primary: payload.is_primary ?? false,
          notes: payload.notes ?? null,
          other_person: otherPerson
            ? {
              id: otherPerson.id,
              display_name: otherPerson.display_name,
              type: otherPerson.type ?? null,
              side: otherPerson.side ?? null,
              status: otherPerson.status ?? null,
            }
            : null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        });
      }
      setOperationStatus({
        tone: 'neutral',
        title: 'Relacao salva localmente',
        description: 'O vinculo ja aparece no detalhe da pessoa enquanto o modulo recalcula as leituras.',
      });
      return { snapshot };
    },
    onSuccess: () => {
      setOperationStatus({
        tone: 'success',
        title: 'Relacao criada',
        description: 'O vinculo foi salvo e o painel segue em reconciliacao leve.',
      });
      toast({ title: 'Relacao criada', description: 'O vinculo entre as pessoas foi salvo.' });
    },
    onError: (error, _variables, context) => {
      if (context) restoreEventPeopleCache(queryClient, context.snapshot);
      setOperationStatus({
        tone: 'danger',
        title: 'Falha ao criar relacao',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar a relacao agora.',
      });
      toast({
        title: 'Falha ao criar relacao',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar a relacao agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => invalidateEventPeopleData(),
  });

  const deleteRelation = useMutation<void, unknown, number, { snapshot: MutationSnapshot }>({
    mutationFn: (relationId) => eventPeopleApi.deleteRelation(eventId, relationId),
    onMutate: async (relationId) => {
      const snapshot = snapshotEventPeopleCache(queryClient, eventId, selectedPersonId);
      if (selectedPersonId !== null) {
        await queryClient.cancelQueries({ queryKey: queryKeys.eventPeople.personDetail(eventId, selectedPersonId) });
        removeOptimisticRelation(queryClient, eventId, selectedPersonId, relationId);
      }
      setOperationStatus({
        tone: 'neutral',
        title: 'Relacao removida localmente',
        description: 'O vinculo saiu da tela na hora. O restante da leitura sera revalidado em seguida.',
      });
      return { snapshot };
    },
    onSuccess: () => {
      setOperationStatus({
        tone: 'success',
        title: 'Relacao removida',
        description: 'O vinculo foi removido e o cockpit segue consistente.',
      });
      toast({ title: 'Relacao removida', description: 'O vinculo foi removido da pessoa.' });
    },
    onError: (error, _variables, context) => {
      if (context) restoreEventPeopleCache(queryClient, context.snapshot);
      setOperationStatus({
        tone: 'danger',
        title: 'Falha ao remover relacao',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel remover a relacao agora.',
      });
      toast({
        title: 'Falha ao remover relacao',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel remover a relacao agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => invalidateEventPeopleData(),
  });

  const addGalleryReference = useMutation<EventPerson, unknown, { event_media_face_id: number }, { snapshot: MutationSnapshot }>({
    mutationFn: (payload) => eventPeopleApi.addGalleryReferencePhoto(eventId, selectedPersonId as number, {
      ...payload,
      purpose: 'matching',
    }),
    onMutate: async () => {
      const snapshot = snapshotEventPeopleCache(queryClient, eventId, selectedPersonId);
      setOperationStatus({
        tone: 'neutral',
        title: 'Referencia adicionada',
        description: 'A selecao da galeria foi registrada e esta sincronizando.',
      });
      return { snapshot };
    },
    onSuccess: (person) => {
      upsertPersonInEventPeopleLists(queryClient, eventId, person);
      replaceEventPeopleDetail(queryClient, eventId, person);
      setOperationStatus({
        tone: 'success',
        title: 'Referencia da galeria salva',
        description: 'A foto selecionada entrou como referencia humana.',
      });
      toast({ title: 'Referencia salva', description: 'A foto da galeria foi salva como referencia.' });
    },
    onError: (error, _variables, context) => {
      if (context) restoreEventPeopleCache(queryClient, context.snapshot);
      setOperationStatus({
        tone: 'danger',
        title: 'Falha ao salvar referencia',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar essa referencia agora.',
      });
      toast({
        title: 'Falha ao salvar referencia',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar essa referencia agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => invalidateEventPeopleData(),
  });

  const uploadReferencePhoto = useMutation<EventPerson, unknown, File, { snapshot: MutationSnapshot }>({
    mutationFn: (file) => eventPeopleApi.uploadReferencePhoto(eventId, selectedPersonId as number, {
      file,
      purpose: 'matching',
    }),
    onMutate: async () => {
      const snapshot = snapshotEventPeopleCache(queryClient, eventId, selectedPersonId);
      setOperationStatus({
        tone: 'neutral',
        title: 'Upload em andamento',
        description: 'A foto de referencia esta sendo enviada e validada.',
      });
      return { snapshot };
    },
    onSuccess: (person) => {
      upsertPersonInEventPeopleLists(queryClient, eventId, person);
      replaceEventPeopleDetail(queryClient, eventId, person);
      setReferenceUploadFile(null);
      setOperationStatus({
        tone: 'success',
        title: 'Referencia enviada',
        description: 'A foto manual entrou como referencia humana da pessoa.',
      });
      toast({ title: 'Referencia enviada', description: 'A foto foi registrada como referencia humana.' });
    },
    onError: (error, _variables, context) => {
      if (context) restoreEventPeopleCache(queryClient, context.snapshot);
      setOperationStatus({
        tone: 'danger',
        title: 'Falha no upload',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel enviar essa foto agora.',
      });
      toast({
        title: 'Falha no upload',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel enviar essa foto agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => invalidateEventPeopleData(),
  });

  const setPrimaryReferencePhoto = useMutation<EventPerson, unknown, number, { snapshot: MutationSnapshot }>({
    mutationFn: (referencePhotoId) => eventPeopleApi.setPrimaryReferencePhoto(eventId, selectedPersonId as number, referencePhotoId),
    onMutate: async () => {
      const snapshot = snapshotEventPeopleCache(queryClient, eventId, selectedPersonId);
      setOperationStatus({
        tone: 'neutral',
        title: 'Atualizando foto principal',
        description: 'A definicao da foto principal foi registrada localmente.',
      });
      return { snapshot };
    },
    onSuccess: (person) => {
      upsertPersonInEventPeopleLists(queryClient, eventId, person);
      replaceEventPeopleDetail(queryClient, eventId, person);
      setOperationStatus({
        tone: 'success',
        title: 'Foto principal definida',
        description: 'A foto principal ja aparece na ficha da pessoa.',
      });
      toast({ title: 'Foto principal definida', description: 'A foto principal foi atualizada.' });
    },
    onError: (error, _variables, context) => {
      if (context) restoreEventPeopleCache(queryClient, context.snapshot);
      setOperationStatus({
        tone: 'danger',
        title: 'Falha ao definir foto principal',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel definir a foto principal agora.',
      });
      toast({
        title: 'Falha ao definir foto principal',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel definir a foto principal agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => invalidateEventPeopleData(),
  });

  const savePending = createPerson.isPending || updatePerson.isPending;
  const relationPending = saveRelation.isPending || deleteRelation.isPending;
  const primaryPhotoLabel = selectedPerson?.primary_photo?.media_id
    ? `Foto #${selectedPerson.primary_photo.media_id}`
    : selectedPerson?.primary_photo?.reference_upload_media_id
      ? `Upload #${selectedPerson.primary_photo.reference_upload_media_id}`
      : selectedPerson?.primary_photo?.best_media_id
        ? `Foto #${selectedPerson.primary_photo.best_media_id}`
        : selectedPerson?.primary_photo?.latest_media_id
          ? `Foto #${selectedPerson.primary_photo.latest_media_id}`
          : 'Nenhuma foto principal definida';
  const avatarLabel = selectedPerson?.avatar?.face_id ? `Rosto #${selectedPerson.avatar.face_id}` : 'Nenhum avatar definido';

  const cockpitCards = [
    { title: 'Pessoas ativas', value: operationalStatus?.people_active ?? 0, helper: `${operationalStatus?.people_draft ?? 0} rascunhos`, icon: UserCircle2 },
    { title: 'Revisoes pendentes', value: operationalStatus?.review_queue_pending ?? 0, helper: `${operationalStatus?.review_queue_conflict ?? 0} conflitos`, icon: TriangleAlert },
    { title: 'Sincronizacao em fila', value: operationalStatus?.aws_sync_pending ?? 0, helper: `${operationalStatus?.aws_sync_failed ?? 0} falhas`, icon: RefreshCcw },
    { title: 'Confirmacoes locais', value: operationalStatus?.assignments_confirmed ?? 0, helper: 'rostos confirmados', icon: CheckCircle2 },
  ];

  const persistentStatuses = useMemo(() => {
    const items: Array<PersistentOperationStatus & { key: string }> = [];
    if (operationStatus) items.push({ key: 'operation', ...operationStatus });
    if (operationalStatusQuery.isFetching) items.push({ key: 'refreshing', tone: 'neutral', title: 'Atualizando painel', description: 'Os contadores operacionais continuam sendo sincronizados sem trocar de tela.' });
    if ((operationalStatus?.review_queue_conflict ?? 0) > 0) items.push({ key: 'conflicts', tone: 'danger', title: `${operationalStatus?.review_queue_conflict ?? 0} conflitos precisam de revisao`, description: 'Existem identidades em disputa que dependem de decisao humana agora.' });
    if ((operationalStatus?.review_queue_pending ?? 0) > 0) items.push({ key: 'pending', tone: 'warning', title: `${operationalStatus?.review_queue_pending ?? 0} revisoes pendentes`, description: 'O sistema encontrou rostos que ainda precisam de confirmacao.' });
    if ((operationalStatus?.aws_sync_failed ?? 0) > 0) items.push({ key: 'failed', tone: 'danger', title: `${operationalStatus?.aws_sync_failed ?? 0} sincronizacoes falharam`, description: 'A operacao local continua valida, mas existem pendencias tecnicas a reconciliar.' });
    else if ((operationalStatus?.aws_sync_pending ?? 0) > 0) items.push({ key: 'syncing', tone: 'warning', title: `${operationalStatus?.aws_sync_pending ?? 0} sincronizacoes em fila`, description: 'O ack local do operador ja vale. O restante continua sendo processado em segundo plano.' });
    return items;
  }, [operationStatus, operationalStatus, operationalStatusQuery.isFetching]);

  const savePerson = () => {
    if (draft.display_name.trim() === '') return toast({ title: 'Nome obrigatorio', description: 'Informe o nome da pessoa.', variant: 'destructive' });
    const payload = { ...draft, display_name: draft.display_name.trim() };
    return isCreating ? createPerson.mutate(payload) : updatePerson.mutate(payload);
  };

  const saveCurrentRelation = () => {
    if (!selectedPerson || !relationDraft || relationDraft.person_b_id <= 0) return toast({ title: 'Relacao incompleta', description: 'Escolha a outra pessoa antes de salvar.', variant: 'destructive' });
    saveRelation.mutate({ ...relationDraft, person_a_id: selectedPerson.id, notes: relationDraft.notes?.trim() || null });
  };

  const toggleGalleryCandidates = () => {
    if (!selectedPerson || isCreating) return;
    setShowGalleryCandidates((current) => !current);
  };

  const handleUploadReference = () => {
    if (!selectedPerson || !referenceUploadFile) {
      return toast({ title: 'Selecione uma foto', description: 'Escolha uma foto de referencia antes de enviar.', variant: 'destructive' });
    }
    uploadReferencePhoto.mutate(referenceUploadFile);
  };

  const handleSelectCandidate = (candidate: EventPersonReferencePhotoCandidate) => {
    if (!selectedPerson) return;
    addGalleryReference.mutate({ event_media_face_id: candidate.event_media_face_id });
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title={eventQuery.data ? `Pessoas de ${eventQuery.data.title}` : 'Pessoas do evento'}
        description="Organize as pessoas do evento, salve relacoes importantes e acompanhe estados persistentes sem sair da operacao."
        actions={(
          <>
            <Button variant="outline" asChild>
              <Link to={`/events/${eventId}`}>
                <ArrowLeft className="h-4 w-4" />
                Voltar ao evento
              </Link>
            </Button>
            <Button variant="outline" onClick={() => invalidateEventPeopleData()}>
              <RefreshCcw className="h-4 w-4" />
              Atualizar
            </Button>
            <Button onClick={startCreate}>
              <Plus className="h-4 w-4" />
              Nova pessoa
            </Button>
          </>
        )}
      />

      <div aria-live="polite" className="grid gap-3 xl:grid-cols-2">
        {persistentStatuses.map((status) => (
          <div key={status.key} className={`rounded-2xl border px-4 py-4 ${statusCardClass(status.tone)}`}>
            <p className="font-medium">{status.title}</p>
            <p className="mt-1 text-sm opacity-90">{status.description}</p>
          </div>
        ))}
      </div>

      <div className="grid gap-4 xl:grid-cols-4">
        {cockpitCards.map((item) => (
          <Card key={item.title} className="border-border/60">
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm">
                <item.icon className="h-4 w-4 text-primary" />
                {item.title}
              </CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold">
              {item.value}
              <span className="ml-2 text-sm font-normal text-muted-foreground">{item.helper}</span>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 xl:grid-cols-[340px_minmax(0,1fr)]">
        <Card className="border-border/60">
          <CardHeader className="space-y-4">
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2">
                <UserCircle2 className="h-5 w-5 text-primary" />
                <CardTitle>Pessoas do evento</CardTitle>
              </div>
              <Badge variant="outline">{peopleQuery.data?.meta.total ?? 0} pessoas</Badge>
            </div>
            <div className="space-y-3">
              <div className="space-y-2">
                <Label htmlFor="event-people-search">Buscar pessoa</Label>
                <Input id="event-people-search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Busque pelo nome" />
              </div>
              <div className="space-y-2">
                <Label>Status</Label>
                <Select value={statusFilter} onValueChange={(value) => setStatusFilter(value as EventPersonStatus | 'all')}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {EVENT_PERSON_STATUS_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <ScrollArea className="h-[680px] pr-4">
              <div className="space-y-3">
                {peopleQuery.isLoading ? <div className="flex items-center justify-center py-10 text-sm text-muted-foreground"><Loader2 className="mr-2 h-4 w-4 animate-spin" />Carregando pessoas...</div> : null}
                {!peopleQuery.isLoading && people.length === 0 ? <div className="rounded-2xl border border-dashed border-border/60 px-4 py-8 text-center text-sm text-muted-foreground">Nenhuma pessoa encontrada com esse filtro.</div> : null}
                {people.map((person) => (
                  <button
                    key={person.id}
                    type="button"
                    className={`w-full rounded-2xl border px-4 py-4 text-left transition ${selectedPersonId === person.id && !isCreating ? 'border-primary bg-primary/10' : 'border-border/60 bg-background hover:border-primary/40 hover:bg-primary/5'}`}
                    onClick={() => selectPerson(person.id)}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-medium">{person.display_name}</p>
                        <p className="text-xs text-muted-foreground">{formatEventPersonMeta(person)}</p>
                      </div>
                      <Badge variant="outline">{formatEventPersonStatus(person.status)}</Badge>
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                      {countLabel(person.stats?.[0]?.media_count, 'foto', 'fotos')} - {countLabel(person.reference_photos?.length, 'referencia humana', 'referencias humanas')}
                    </p>
                  </button>
                ))}
              </div>
            </ScrollArea>
          </CardContent>
        </Card>

        <div className="grid gap-4">
          <div className="grid gap-4 lg:grid-cols-4">
            <Card className="border-border/60"><CardHeader className="pb-2"><CardTitle className="flex items-center gap-2 text-sm"><UserCircle2 className="h-4 w-4 text-primary" />Pessoa</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{isCreating ? 'Nova pessoa' : selectedPerson?.display_name ?? 'Selecione'}</CardContent></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardTitle className="flex items-center gap-2 text-sm"><Camera className="h-4 w-4 text-primary" />Fotos encontradas</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{selectedStats?.media_count ?? 0}<span className="ml-2 text-sm font-normal text-muted-foreground">na galeria</span></CardContent></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardTitle className="flex items-center gap-2 text-sm"><UserCircle2 className="h-4 w-4 text-primary" />Avatar do catalogo</CardTitle></CardHeader><CardContent className="text-base font-semibold">{avatarLabel}</CardContent></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardTitle className="flex items-center gap-2 text-sm"><Image className="h-4 w-4 text-primary" />Foto principal</CardTitle></CardHeader><CardContent className="text-base font-semibold">{primaryPhotoLabel}</CardContent></Card>
          </div>
          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
            <Card className="border-border/60">
              <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle>{isCreating ? 'Nova pessoa' : 'Cadastro e relacoes'}</CardTitle>
                {isUiPending ? <Badge variant="secondary">Atualizando painel</Badge> : null}
              </CardHeader>
              <CardContent className="space-y-5">
                <div className="grid gap-4 lg:grid-cols-2">
                  <div className="space-y-2"><Label htmlFor="person-name">Nome</Label><Input id="person-name" value={draft.display_name} onChange={(event) => setDraft((current) => ({ ...current, display_name: event.target.value }))} placeholder="Ex.: Mae da noiva" /></div>
                  <div className="space-y-2"><Label>Tipo</Label><Select value={String(draft.type ?? 'guest')} onValueChange={(value) => setDraft((current) => ({ ...current, type: value as EventPersonType }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{EVENT_PERSON_TYPE_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select></div>
                  <div className="space-y-2"><Label>Lado</Label><Select value={String(draft.side ?? 'neutral')} onValueChange={(value) => setDraft((current) => ({ ...current, side: value as EventPersonSide }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{EVENT_PERSON_SIDE_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select></div>
                  <div className="space-y-2"><Label>Status</Label><Select value={String(draft.status ?? 'active')} onValueChange={(value) => setDraft((current) => ({ ...current, status: value as EventPersonStatus }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{EVENT_PERSON_STATUS_OPTIONS.filter((option) => option.value !== 'all').map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select></div>
                  <div className="space-y-2 lg:col-span-2"><Label htmlFor="person-notes">Notas</Label><Textarea id="person-notes" value={draft.notes ?? ''} onChange={(event) => setDraft((current) => ({ ...current, notes: event.target.value }))} placeholder="Observacoes sobre essa pessoa" /></div>
                </div>
                <div className="flex flex-wrap gap-2"><Button onClick={savePerson} disabled={savePending}>{savePending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}{isCreating ? 'Criar pessoa' : 'Salvar ajustes'}</Button>{!isCreating ? <Button variant="outline" onClick={startCreate}><Plus className="h-4 w-4" />Cadastrar outra pessoa</Button> : null}</div>

                {!isCreating && selectedPerson ? <>
                  <div className="grid gap-3 md:grid-cols-4"><div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Sozinha</p><p className="mt-1 text-lg font-semibold">{selectedStats?.solo_media_count ?? 0}</p></div><div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Com outras</p><p className="mt-1 text-lg font-semibold">{selectedStats?.with_others_media_count ?? 0}</p></div><div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Publicadas</p><p className="mt-1 text-lg font-semibold">{selectedStats?.published_media_count ?? 0}</p></div><div className="rounded-2xl border border-border/60 bg-background/60 px-3 py-3"><p className="text-xs uppercase tracking-wide text-muted-foreground">Pendentes</p><p className="mt-1 text-lg font-semibold">{selectedStats?.pending_media_count ?? 0}</p></div></div>
                  <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div className="space-y-3"><div className="flex items-center justify-between gap-3"><h3 className="font-semibold">Relacoes importantes</h3><Badge variant="outline">{selectedPerson.relations?.length ?? 0} salvas</Badge></div>{(selectedPerson.relations ?? []).length === 0 ? <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">Nenhuma relacao salva para essa pessoa.</div> : null}{(selectedPerson.relations ?? []).map((relation) => <div key={relation.id} className="rounded-2xl border border-border/60 bg-background/70 px-4 py-4"><div className="flex items-center justify-between gap-3"><div><p className="font-medium">{formatEventPersonRelationType(relation.relation_type)}</p><p className="text-sm text-muted-foreground">{relation.other_person?.display_name ?? relation.person_b?.display_name ?? 'Pessoa relacionada'}</p></div><Button variant="outline" size="sm" onClick={() => deleteRelation.mutate(relation.id)} disabled={relationPending}>Remover</Button></div>{relation.notes ? <p className="mt-3 text-sm text-muted-foreground">{relation.notes}</p> : null}</div>)}</div>
                    <div className="rounded-3xl border border-border/60 bg-background/70 p-4"><div className="flex items-center gap-2"><HeartHandshake className="h-4 w-4 text-primary" /><p className="font-medium">Relacionar pessoas</p></div><p className="mt-1 text-sm text-muted-foreground">Escolha com quem essa pessoa se relaciona e salve o vinculo.</p><div className="mt-4 space-y-3"><div className="space-y-2"><Label>Outra pessoa</Label><Select value={relationDraft?.person_b_id ? String(relationDraft.person_b_id) : '__unselected__'} onValueChange={(value) => setRelationDraft((current) => current ? ({ ...current, person_b_id: value === '__unselected__' ? 0 : Number(value) }) : current)}><SelectTrigger><SelectValue placeholder="Escolha a pessoa" /></SelectTrigger><SelectContent><SelectItem value="__unselected__">Escolha a pessoa</SelectItem>{otherPeople.map((person) => <SelectItem key={person.id} value={String(person.id)}>{person.display_name}</SelectItem>)}</SelectContent></Select></div><div className="space-y-2"><Label>Tipo de relacao</Label><Select value={relationDraft?.relation_type ?? '__unselected__'} onValueChange={(value) => { if (value === '__unselected__') return; const preset = presetsQuery.data?.relations?.find((item) => item.type === value); setRelationDraft((current) => current ? ({ ...current, relation_type: value, directionality: preset?.directionality ?? current.directionality }) : current); }}><SelectTrigger><SelectValue placeholder="Escolha o tipo" /></SelectTrigger><SelectContent><SelectItem value="__unselected__">Escolha o tipo</SelectItem>{(presetsQuery.data?.relations ?? []).map((item) => <SelectItem key={item.type} value={item.type}>{item.label}</SelectItem>)}</SelectContent></Select></div><div className="space-y-2"><Label>Observacao</Label><Textarea value={relationDraft?.notes ?? ''} onChange={(event) => setRelationDraft((current) => current ? ({ ...current, notes: event.target.value }) : current)} placeholder="Ex.: casal principal, familia proxima, padrinhos" /></div><Button onClick={saveCurrentRelation} disabled={relationPending}>{relationPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <UsersRound className="h-4 w-4" />}Salvar relacao</Button></div></div>
                  </div>
                </> : null}
              </CardContent>
            </Card>

            <div className="grid gap-4">
              <Card className="border-border/60"><CardHeader><CardTitle className="flex items-center gap-2"><Sparkles className="h-4 w-4 text-primary" />Modelo do evento</CardTitle></CardHeader><CardContent className="space-y-2"><p className="text-sm text-muted-foreground">Atalhos para cadastrar as pessoas mais importantes do evento sem montar tudo do zero.</p>{(presetsQuery.data?.people ?? []).map((preset) => <button key={preset.key} type="button" className="w-full rounded-2xl border border-border/60 bg-background px-4 py-3 text-left transition hover:border-primary/40 hover:bg-primary/5" onClick={() => createPerson.mutate({ display_name: preset.label, type: preset.type, side: preset.side, importance_rank: preset.importance_rank, status: 'active' })}><div className="flex items-center justify-between gap-3"><div><p className="font-medium">{preset.label}</p><p className="text-xs text-muted-foreground">{formatEventPersonMeta({ type: preset.type, side: preset.side }, 'Pessoa do evento')}</p></div><Sparkles className="h-4 w-4 text-primary" /></div></button>)}</CardContent></Card>
              <Card className="border-border/60"><CardHeader><CardTitle className="flex items-center gap-2"><UserCircle2 className="h-4 w-4 text-primary" />Avatar e foto principal</CardTitle></CardHeader><CardContent className="space-y-3 text-sm"><div className="rounded-2xl border border-border/60 bg-background px-4 py-3"><p className="font-medium">Avatar do catalogo</p><p className="mt-1 text-muted-foreground">{selectedPerson?.avatar?.media_id ? `Foto #${selectedPerson.avatar.media_id} com ${avatarLabel.toLowerCase()}` : 'Nenhum avatar definido ainda.'}</p></div><div className="rounded-2xl border border-border/60 bg-background px-4 py-3"><p className="font-medium">Foto principal</p><p className="mt-1 text-muted-foreground">{selectedPerson?.primary_photo?.best_media_id ? primaryPhotoLabel : 'A melhor foto humana ainda nao foi definida.'}</p></div></CardContent></Card>
              <Card className="border-border/60">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Camera className="h-4 w-4 text-primary" />
                    Fotos de referencia
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <p className="text-sm text-muted-foreground">Referencias humanas usadas para ancorar matching e navegacao visual dessa pessoa.</p>
                  <div className="flex flex-wrap gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={toggleGalleryCandidates}
                      disabled={!selectedPerson || isCreating}
                    >
                      <ImagePlus className="h-4 w-4" />
                      {showGalleryCandidates ? 'Fechar galeria' : 'Escolher da galeria'}
                    </Button>
                  </div>

                  {showGalleryCandidates ? (
                    <div className="rounded-2xl border border-dashed border-border/60 px-4 py-4">
                      <div className="flex items-center justify-between gap-3">
                        <p className="font-medium">Faces confirmadas na galeria</p>
                        {referenceCandidatesQuery.isFetching ? <Badge variant="secondary">Atualizando</Badge> : null}
                      </div>
                      <div className="mt-3 space-y-2">
                        {referenceCandidatesQuery.isLoading ? (
                          <div className="flex items-center justify-center gap-2 py-4 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Carregando candidatos...
                          </div>
                        ) : null}
                        {!referenceCandidatesQuery.isLoading && referenceCandidates.length === 0 ? (
                          <div className="rounded-2xl border border-border/60 bg-background px-4 py-4 text-sm text-muted-foreground">
                            Nenhuma face confirmada disponivel para essa pessoa.
                          </div>
                        ) : null}
                        {referenceCandidates.map((candidate) => (
                          <div key={candidate.assignment_id} className="flex items-center gap-3 rounded-2xl border border-border/60 bg-background px-3 py-3">
                            {candidate.media?.thumbnail_url ? (
                              <img
                                src={candidate.media.thumbnail_url}
                                alt="Foto candidata"
                                className="h-12 w-12 rounded-xl object-cover"
                                loading="lazy"
                              />
                            ) : (
                              <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-dashed border-border/70 text-xs text-muted-foreground">Foto</div>
                            )}
                            <div className="min-w-0 flex-1">
                              <p className="font-medium">Foto #{candidate.event_media_id ?? '---'}</p>
                              <p className="text-xs text-muted-foreground">Rosto #{candidate.face_index ?? '--'} · {candidate.quality_tier ?? 'sem qualidade'}</p>
                            </div>
                            <Button
                              type="button"
                              size="sm"
                              onClick={() => handleSelectCandidate(candidate)}
                              disabled={addGalleryReference.isPending}
                            >
                              Usar como referencia
                            </Button>
                          </div>
                        ))}
                      </div>
                    </div>
                  ) : null}

                  <div className="rounded-2xl border border-border/60 bg-background px-4 py-4">
                    <p className="font-medium">Enviar foto de referencia</p>
                    <p className="mt-1 text-xs text-muted-foreground">Use uma selfie com uma pessoa dominante. Fotos de grupo sao bloqueadas.</p>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                      <Input
                        type="file"
                        accept="image/*"
                        onChange={(event) => setReferenceUploadFile(event.target.files?.[0] ?? null)}
                      />
                      <Button
                        type="button"
                        onClick={handleUploadReference}
                        disabled={!referenceUploadFile || uploadReferencePhoto.isPending}
                      >
                        {uploadReferencePhoto.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <UploadCloud className="h-4 w-4" />}
                        Enviar foto de referencia
                      </Button>
                    </div>
                  </div>

                  {selectedReferencePhotos.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">Nenhuma foto de referencia ativa para essa pessoa.</div>
                  ) : null}
                  {selectedReferencePhotos.map((photo, index) => (
                    <div key={photo.id} className="rounded-2xl border border-border/60 bg-background px-4 py-3">
                      <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                          {photo.upload_media?.preview_url ? (
                            <img
                              src={photo.upload_media.preview_url}
                              alt="Referencia enviada"
                              className="h-12 w-12 rounded-xl object-cover"
                              loading="lazy"
                            />
                          ) : (
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-dashed border-border/70 text-xs text-muted-foreground">Foto</div>
                          )}
                          <div>
                            <p className="font-medium">Referencia {index + 1}</p>
                            <p className="text-xs text-muted-foreground">
                              {photo.face?.event_media_id ? `Foto #${photo.face.event_media_id}` : photo.upload_media?.original_filename ?? 'Foto vinculada'}
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          {primaryReferencePhotoId === photo.id ? (
                            <Badge variant="secondary">Foto principal</Badge>
                          ) : (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() => setPrimaryReferencePhoto.mutate(photo.id)}
                              disabled={setPrimaryReferencePhoto.isPending}
                            >
                              Definir foto principal
                            </Button>
                          )}
                          <Badge variant="outline">{formatEventPersonReferenceStatus(photo.status)}</Badge>
                        </div>
                      </div>
                      <p className="mt-2 text-xs text-muted-foreground">{formatEventPersonReferencePurpose(photo.purpose)}</p>
                    </div>
                  ))}
                </CardContent>
              </Card>
              <Card className="border-border/60"><CardHeader><CardTitle className="flex items-center gap-2"><Fingerprint className="h-4 w-4 text-primary" />Referencias tecnicas</CardTitle></CardHeader><CardContent className="space-y-2"><p className="text-sm text-muted-foreground">Este conjunto tecnico e derivado localmente e segue para sincronizacao sem mudar a intencao humana da pessoa.</p>{selectedRepresentativeFaces.length === 0 ? <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">Nenhuma referencia tecnica projetada para essa pessoa.</div> : null}{selectedRepresentativeFaces.map((representative, index) => <div key={representative.id} className="rounded-2xl border border-border/60 bg-background px-4 py-3"><div className="flex items-center justify-between gap-3"><div><p className="font-medium">Representacao {index + 1}</p><p className="text-xs text-muted-foreground">{representative.face?.event_media_id ? `Foto #${representative.face.event_media_id}` : 'Foto selecionada'}</p></div><Badge variant={representative.sync_status === 'synced' ? 'secondary' : representative.sync_status === 'failed' ? 'destructive' : 'outline'}>{formatEventPersonSyncStatus(representative.sync_status)}</Badge></div></div>)}</CardContent></Card>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
