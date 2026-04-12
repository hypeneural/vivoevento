import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Plus, Save, Sparkles, Trash2, UsersRound } from 'lucide-react';

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

import { eventPeopleApi } from '../api';
import { EVENT_PERSON_SIDE_OPTIONS } from '../labels';
import type {
  EventPeoplePresetGroup,
  EventPerson,
  EventPersonGroup,
  EventPersonGroupPayload,
  EventPersonSide,
} from '../types';

type OperationTone = 'neutral' | 'success' | 'warning' | 'danger';

interface PersistentOperationStatus {
  title: string;
  description: string;
  tone: OperationTone;
}

interface EventPeopleGroupsPanelProps {
  eventId: number | string;
  selectedPerson: EventPerson | null;
  presetGroups?: EventPeoplePresetGroup[];
  onStatusChange?: (status: PersistentOperationStatus | null) => void;
}

interface GroupDraft {
  display_name: string;
  group_type: string;
  side: EventPersonSide | string;
  importance_rank: number;
  notes: string;
  status: 'active' | 'archived';
}

function emptyDraft(): GroupDraft {
  return {
    display_name: '',
    group_type: 'custom',
    side: 'neutral',
    importance_rank: 0,
    notes: '',
    status: 'active',
  };
}

function sortGroups(groups: EventPersonGroup[]): EventPersonGroup[] {
  return [...groups].sort((groupA, groupB) => {
    if (groupA.importance_rank !== groupB.importance_rank) {
      return groupB.importance_rank - groupA.importance_rank;
    }

    return groupA.display_name.localeCompare(groupB.display_name);
  });
}

function mergeGroups(current: EventPersonGroup[], incoming: EventPersonGroup[]): EventPersonGroup[] {
  const byId = new Map<number, EventPersonGroup>();

  current.forEach((group) => byId.set(group.id, group));
  incoming.forEach((group) => byId.set(group.id, group));

  return sortGroups([...byId.values()]);
}

export function EventPeopleGroupsPanel({
  eventId,
  selectedPerson,
  presetGroups = [],
  onStatusChange,
}: EventPeopleGroupsPanelProps) {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [selectedGroupId, setSelectedGroupId] = useState<number | null>(null);
  const [isCreating, setIsCreating] = useState(false);
  const [draft, setDraft] = useState<GroupDraft>(emptyDraft());

  const groupsKey = queryKeys.eventPeople.groups(eventId);
  const groupsQuery = useQuery({
    queryKey: groupsKey,
    queryFn: () => eventPeopleApi.listGroups(eventId),
    staleTime: 30_000,
  });

  const groups = groupsQuery.data ?? [];
  const selectedGroup = groups.find((group) => group.id === selectedGroupId) ?? null;
  const missingPresetGroupsCount = useMemo(() => {
    const slugs = new Set(groups.map((group) => group.slug));

    return presetGroups.filter((group) => !slugs.has(group.key)).length;
  }, [groups, presetGroups]);

  useEffect(() => {
    if (isCreating) {
      return;
    }

    if (selectedGroupId === null && groups.length > 0) {
      setSelectedGroupId(groups[0].id);
    }

    if (selectedGroupId !== null && !groups.some((group) => group.id === selectedGroupId)) {
      setSelectedGroupId(groups[0]?.id ?? null);
    }
  }, [groups, isCreating, selectedGroupId]);

  useEffect(() => {
    if (!selectedGroup || isCreating) {
      return;
    }

    setDraft({
      display_name: selectedGroup.display_name,
      group_type: selectedGroup.group_type ?? 'custom',
      side: selectedGroup.side ?? 'neutral',
      importance_rank: selectedGroup.importance_rank ?? 0,
      notes: selectedGroup.notes ?? '',
      status: selectedGroup.status === 'archived' ? 'archived' : 'active',
    });
  }, [isCreating, selectedGroup]);

  const setGroupsCache = (updater: (current: EventPersonGroup[]) => EventPersonGroup[]) => {
    queryClient.setQueryData<EventPersonGroup[]>(groupsKey, (current = []) => updater(current));
  };

  const createGroup = useMutation({
    mutationFn: (payload: EventPersonGroupPayload) => eventPeopleApi.createGroup(eventId, payload),
    onSuccess: (group) => {
      setGroupsCache((current) => mergeGroups(current, [group]));
      setSelectedGroupId(group.id);
      setIsCreating(false);
      onStatusChange?.({
        tone: 'success',
        title: 'Grupo criado',
        description: 'O nucleo social ja ficou disponivel para a operacao.',
      });
      toast({
        title: 'Grupo criado',
        description: 'O grupo foi salvo e ja aparece no painel.',
      });
    },
    onError: (error) => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao criar grupo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel criar esse grupo agora.',
      });
      toast({
        title: 'Falha ao criar grupo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel criar esse grupo agora.',
        variant: 'destructive',
      });
    },
  });

  const updateGroup = useMutation({
    mutationFn: (payload: Partial<EventPersonGroupPayload>) => eventPeopleApi.updateGroup(eventId, selectedGroupId as number, payload),
    onSuccess: (group) => {
      setGroupsCache((current) => mergeGroups(current, [group]));
      onStatusChange?.({
        tone: 'success',
        title: 'Grupo atualizado',
        description: 'Os ajustes do grupo foram salvos sem sair da pagina.',
      });
      toast({
        title: 'Grupo atualizado',
        description: 'As alteracoes do grupo foram salvas.',
      });
    },
    onError: (error) => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao atualizar grupo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel atualizar esse grupo agora.',
      });
      toast({
        title: 'Falha ao atualizar grupo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel atualizar esse grupo agora.',
        variant: 'destructive',
      });
    },
  });

  const deleteGroup = useMutation({
    mutationFn: () => eventPeopleApi.deleteGroup(eventId, selectedGroupId as number),
    onSuccess: () => {
      const deletedId = selectedGroupId;
      setGroupsCache((current) => current.filter((group) => group.id !== deletedId));
      setSelectedGroupId(null);
      onStatusChange?.({
        tone: 'warning',
        title: 'Grupo removido',
        description: 'O grupo foi excluido e os memberships vinculados sairam juntos.',
      });
      toast({
        title: 'Grupo removido',
        description: 'O grupo foi excluido.',
      });
    },
    onError: (error) => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao remover grupo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel remover esse grupo agora.',
      });
      toast({
        title: 'Falha ao remover grupo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel remover esse grupo agora.',
        variant: 'destructive',
      });
    },
  });

  const applyPresetGroups = useMutation({
    mutationFn: () => eventPeopleApi.applyPresetGroups(eventId),
    onSuccess: (groupsFromPreset) => {
      setGroupsCache((current) => mergeGroups(current, groupsFromPreset));
      onStatusChange?.({
        tone: 'success',
        title: 'Grupos do modelo aplicados',
        description: 'Os grupos sementes do tipo de evento foram trazidos para o painel.',
      });
      toast({
        title: 'Grupos do modelo aplicados',
        description: 'Os grupos sementes foram criados ou reconciliados.',
      });
    },
    onError: (error) => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao aplicar grupos do modelo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel aplicar os grupos do modelo agora.',
      });
      toast({
        title: 'Falha ao aplicar grupos do modelo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel aplicar os grupos do modelo agora.',
        variant: 'destructive',
      });
    },
  });

  const addSelectedPerson = useMutation({
    mutationFn: () => eventPeopleApi.addGroupMember(eventId, selectedGroupId as number, {
      event_person_id: selectedPerson?.id as number,
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: groupsKey });
      onStatusChange?.({
        tone: 'success',
        title: 'Pessoa adicionada ao grupo',
        description: 'O nucleo foi atualizado e as leituras locais foram reconsultadas.',
      });
      toast({
        title: 'Pessoa adicionada',
        description: 'A pessoa aberta foi vinculada ao grupo.',
      });
    },
    onError: (error) => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao adicionar pessoa ao grupo',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar esse membership agora.',
      });
      toast({
        title: 'Falha ao adicionar pessoa',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel salvar esse membership agora.',
        variant: 'destructive',
      });
    },
  });

  const removeMembership = useMutation({
    mutationFn: ({ groupId, membershipId }: { groupId: number; membershipId: number }) => eventPeopleApi.removeGroupMember(eventId, groupId, membershipId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: groupsKey });
      onStatusChange?.({
        tone: 'warning',
        title: 'Membership removido',
        description: 'A pessoa saiu do grupo e o painel foi reconciliado.',
      });
    },
    onError: (error) => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao remover membership',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel remover essa pessoa do grupo agora.',
      });
      toast({
        title: 'Falha ao remover membership',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel remover essa pessoa do grupo agora.',
        variant: 'destructive',
      });
    },
  });

  const isSelectedPersonInGroup = selectedPerson && selectedGroup
    ? selectedGroup.memberships.some((membership) => membership.event_person_id === selectedPerson.id)
    : false;

  const saveGroup = () => {
    const payload: EventPersonGroupPayload = {
      display_name: draft.display_name.trim(),
      group_type: draft.group_type.trim() || 'custom',
      side: draft.side,
      importance_rank: Number(draft.importance_rank ?? 0),
      notes: draft.notes.trim() || null,
      status: draft.status,
    };

    if (!payload.display_name) {
      onStatusChange?.({
        tone: 'warning',
        title: 'Nome obrigatorio',
        description: 'Defina um nome para o grupo antes de salvar.',
      });
      return;
    }

    if (isCreating || !selectedGroup) {
      createGroup.mutate(payload);
      return;
    }

    updateGroup.mutate(payload);
  };

  const openNewGroup = () => {
    setIsCreating(true);
    setSelectedGroupId(null);
    setDraft(emptyDraft());
  };

  const selectGroup = (groupId: number) => {
    setIsCreating(false);
    setSelectedGroupId(groupId);
  };

  return (
    <Card className="border-border/60">
      <CardHeader className="space-y-4">
        <div className="flex items-center justify-between gap-3">
          <CardTitle className="flex items-center gap-2">
            <UsersRound className="h-4 w-4 text-primary" />
            Grupos do evento
          </CardTitle>
          <Badge variant="outline">{groups.length} grupos</Badge>
        </div>
        <p className="text-sm text-muted-foreground">
          Nucleos sociais locais do evento. Essa camada desbloqueia coverage e entregas depois.
        </p>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" onClick={openNewGroup}>
            <Plus className="h-4 w-4" />
            Novo grupo
          </Button>
          {presetGroups.length > 0 ? (
            <Button type="button" variant="outline" onClick={() => applyPresetGroups.mutate()} disabled={applyPresetGroups.isPending}>
              {applyPresetGroups.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
              Criar grupos do modelo
            </Button>
          ) : null}
        </div>
        {presetGroups.length > 0 ? (
          <div className="rounded-2xl border border-dashed border-border/60 px-4 py-3 text-xs text-muted-foreground">
            {missingPresetGroupsCount} grupos sementes ainda nao foram materializados no evento.
          </div>
        ) : null}
      </CardHeader>
      <CardContent>
        <div className="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
          <div className="space-y-3">
            <ScrollArea className="h-[320px] pr-3">
              <div className="space-y-3">
                {groupsQuery.isLoading ? (
                  <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Carregando grupos...
                  </div>
                ) : null}
                {!groupsQuery.isLoading && groups.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">
                    Nenhum grupo criado ainda para esse evento.
                  </div>
                ) : null}
                {groups.map((group) => (
                  <button
                    key={group.id}
                    type="button"
                    className={`w-full rounded-2xl border px-4 py-4 text-left transition ${
                      !isCreating && selectedGroupId === group.id
                        ? 'border-primary bg-primary/10'
                        : 'border-border/60 bg-background hover:border-primary/40 hover:bg-primary/5'
                    }`}
                    onClick={() => selectGroup(group.id)}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="font-medium">{group.display_name}</p>
                        <p className="text-xs text-muted-foreground">
                          {group.group_type ?? 'custom'} · {group.side ?? 'neutral'}
                        </p>
                      </div>
                      <Badge variant="outline">{group.stats.member_count}</Badge>
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                      {group.stats.media_count} fotos locais · {group.stats.people_with_primary_photo_count} com foto principal
                    </p>
                  </button>
                ))}
              </div>
            </ScrollArea>
          </div>

          <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="group-name">Nome do grupo</Label>
                <Input
                  id="group-name"
                  value={draft.display_name}
                  onChange={(event) => setDraft((current) => ({ ...current, display_name: event.target.value }))}
                  placeholder="Ex.: Familia da noiva"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="group-type">Tipo do grupo</Label>
                <Input
                  id="group-type"
                  value={draft.group_type}
                  onChange={(event) => setDraft((current) => ({ ...current, group_type: event.target.value }))}
                  placeholder="Ex.: familia, corte, equipe"
                />
              </div>
              <div className="space-y-2">
                <Label>Lado</Label>
                <Select value={String(draft.side ?? 'neutral')} onValueChange={(value) => setDraft((current) => ({ ...current, side: value }))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {EVENT_PERSON_SIDE_OPTIONS.filter((option) => option.value !== 'all').map((option) => (
                      <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="group-rank">Importancia</Label>
                <Input
                  id="group-rank"
                  type="number"
                  value={draft.importance_rank}
                  onChange={(event) => setDraft((current) => ({ ...current, importance_rank: Number(event.target.value || 0) }))}
                />
              </div>
              <div className="space-y-2">
                <Label>Status</Label>
                <Select value={draft.status} onValueChange={(value) => setDraft((current) => ({ ...current, status: value as 'active' | 'archived' }))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Ativo</SelectItem>
                    <SelectItem value="archived">Arquivado</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="group-notes">Notas</Label>
                <Textarea
                  id="group-notes"
                  value={draft.notes}
                  onChange={(event) => setDraft((current) => ({ ...current, notes: event.target.value }))}
                  placeholder="Observacoes operacionais sobre esse nucleo"
                />
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button type="button" onClick={saveGroup} disabled={createGroup.isPending || updateGroup.isPending}>
                {createGroup.isPending || updateGroup.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                {isCreating || !selectedGroup ? 'Criar grupo' : 'Salvar grupo'}
              </Button>
              {!isCreating && selectedGroup ? (
                <Button type="button" variant="outline" onClick={() => deleteGroup.mutate()} disabled={deleteGroup.isPending}>
                  {deleteGroup.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                  Excluir grupo
                </Button>
              ) : null}
            </div>

            {!isCreating && selectedGroup ? (
              <>
                <div className="grid gap-3 sm:grid-cols-4">
                  <div className="rounded-2xl border border-border/60 bg-background/70 px-3 py-3">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Membros</p>
                    <p className="mt-1 text-lg font-semibold">{selectedGroup.stats.member_count}</p>
                  </div>
                  <div className="rounded-2xl border border-border/60 bg-background/70 px-3 py-3">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Fotos locais</p>
                    <p className="mt-1 text-lg font-semibold">{selectedGroup.stats.media_count}</p>
                  </div>
                  <div className="rounded-2xl border border-border/60 bg-background/70 px-3 py-3">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Publicadas</p>
                    <p className="mt-1 text-lg font-semibold">{selectedGroup.stats.published_media_count}</p>
                  </div>
                  <div className="rounded-2xl border border-border/60 bg-background/70 px-3 py-3">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Com foto principal</p>
                    <p className="mt-1 text-lg font-semibold">{selectedGroup.stats.people_with_primary_photo_count}</p>
                  </div>
                </div>

                <div className="rounded-2xl border border-border/60 bg-background px-4 py-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-medium">Adicionar pessoa aberta</p>
                      <p className="mt-1 text-sm text-muted-foreground">
                        Use a pessoa selecionada na coluna principal para alimentar esse grupo.
                      </p>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => addSelectedPerson.mutate()}
                      disabled={!selectedPerson || isSelectedPersonInGroup || addSelectedPerson.isPending}
                    >
                      {addSelectedPerson.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
                      {selectedPerson ? `Adicionar ${selectedPerson.display_name}` : 'Selecione uma pessoa'}
                    </Button>
                  </div>
                </div>

                <div className="space-y-3">
                  <div className="flex items-center justify-between gap-3">
                    <p className="font-medium">Membros do grupo</p>
                    <Badge variant="outline">{selectedGroup.memberships.length}</Badge>
                  </div>
                  {selectedGroup.memberships.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">
                      Ainda nao ha pessoas vinculadas a esse grupo.
                    </div>
                  ) : null}
                  {selectedGroup.memberships.map((membership) => (
                    <div key={membership.id} className="flex items-center justify-between gap-3 rounded-2xl border border-border/60 bg-background px-4 py-3">
                      <div>
                        <p className="font-medium">{membership.person?.display_name ?? `Pessoa #${membership.event_person_id}`}</p>
                        <p className="text-xs text-muted-foreground">
                          {membership.role_label ?? membership.person?.type ?? 'sem papel definido'} · {membership.person?.has_primary_photo ? 'com foto principal' : 'sem foto principal'}
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => removeMembership.mutate({ groupId: selectedGroup.id, membershipId: membership.id })}
                        disabled={removeMembership.isPending}
                      >
                        Remover
                      </Button>
                    </div>
                  ))}
                </div>
              </>
            ) : (
              <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">
                Crie um grupo novo ou escolha um grupo existente para editar memberships.
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
