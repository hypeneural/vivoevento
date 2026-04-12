import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Images, Link2, RefreshCcw } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { queryKeys } from '@/lib/query-client';

import { eventPeopleApi } from '../api';
import type {
  EventPeopleRelationalCollectionsResponse,
  EventPerson,
  EventRelationalCollection,
} from '../types';

type OperationTone = 'neutral' | 'success' | 'warning' | 'danger';

interface PersistentOperationStatus {
  title: string;
  description: string;
  tone: OperationTone;
}

interface EventPeopleRelationalCollectionsPanelProps {
  eventId: number | string;
  selectedPerson: EventPerson | null;
  onStatusChange?: (status: PersistentOperationStatus | null) => void;
}

const collectionTypeLabels: Record<string, string> = {
  person_best_of: 'Pessoa',
  pair_best_of: 'Par',
  group_best_of: 'Grupo',
  family_moment: 'Familia',
  must_have_delivery: 'Entrega pronta',
};

function visibilityBadgeVariant(visibility: string) {
  return visibility === 'public_ready' ? 'default' : 'outline';
}

function collectionCaption(collection: EventRelationalCollection) {
  const published = collection.published_item_count ?? 0;
  const total = collection.item_count ?? 0;

  if (collection.collection_type === 'must_have_delivery') {
    return `${published}/${total} itens ja publicados para link futuro`;
  }

  if (collection.collection_type === 'pair_best_of') {
    return `${total} fotos fortes do vinculo principal`;
  }

  if (collection.collection_type === 'family_moment') {
    return `${total} fotos com leitura de momento familiar`;
  }

  if (collection.collection_type === 'group_best_of') {
    return `${total} fotos fortes do grupo`;
  }

  return `${total} fotos fortes dessa pessoa`;
}

function isCollectionForPerson(collection: EventRelationalCollection, personId: number) {
  return collection.person_a?.id === personId
    || collection.person_b?.id === personId
    || collection.collection_key === `person-best-of:${personId}`;
}

function CollectionCard({ collection }: { collection: EventRelationalCollection }) {
  return (
    <div className="rounded-2xl border border-border/60 bg-background px-4 py-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="font-medium">{collection.display_name}</p>
          <p className="text-xs text-muted-foreground">{collectionCaption(collection)}</p>
        </div>
        <div className="flex flex-wrap justify-end gap-2">
          <Badge variant="secondary">{collectionTypeLabels[collection.collection_type] ?? collection.collection_type}</Badge>
          <Badge variant={visibilityBadgeVariant(collection.visibility)}>
            {collection.visibility === 'public_ready' ? 'Publico pronto' : 'Interno'}
          </Badge>
        </div>
      </div>

      {collection.visibility === 'public_ready' && collection.share_token ? (
        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          <div className="flex items-center gap-2">
            <Link2 className="h-3.5 w-3.5" />
            Entrega publica pronta por token seguro.
          </div>
          {collection.public_url ? (
            <Button asChild variant="outline" size="sm" className="h-7 rounded-full px-3 text-xs">
              <a href={collection.public_url} target="_blank" rel="noreferrer">
                Abrir entrega publica
              </a>
            </Button>
          ) : null}
        </div>
      ) : null}

      <div className="mt-3 flex flex-wrap gap-2">
        {collection.items.slice(0, 3).map((item) => (
          <div
            key={item.id}
            className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-xl border border-border/60 bg-muted/20"
          >
            {item.media?.thumbnail_url ? (
              <img
                src={item.media.thumbnail_url}
                alt={`Midia da colecao ${collection.display_name}`}
                className="h-full w-full object-cover"
                loading="lazy"
              />
            ) : (
              <span className="px-2 text-center text-[10px] text-muted-foreground">Midia #{item.event_media_id}</span>
            )}
          </div>
        ))}
        {collection.items.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border/60 px-3 py-2 text-xs text-muted-foreground">
            Sem midias materializadas ainda.
          </div>
        ) : null}
      </div>
    </div>
  );
}

export function EventPeopleRelationalCollectionsPanel({
  eventId,
  selectedPerson,
  onStatusChange,
}: EventPeopleRelationalCollectionsPanelProps) {
  const queryClient = useQueryClient();
  const queryKey = queryKeys.eventPeople.relationalCollections(eventId);

  const relationalCollectionsQuery = useQuery({
    queryKey,
    queryFn: () => eventPeopleApi.getRelationalCollections(eventId),
    enabled: String(eventId) !== '',
    staleTime: 20_000,
  });

  const refreshMutation = useMutation<EventPeopleRelationalCollectionsResponse>({
    mutationFn: () => eventPeopleApi.refreshRelationalCollections(eventId),
    onMutate: () => {
      onStatusChange?.({
        tone: 'neutral',
        title: 'Gerando colecoes relacionais',
        description: 'As entregas por vinculo estao sendo recalculadas no backend local.',
      });
    },
    onSuccess: (data) => {
      queryClient.setQueryData(queryKey, data);
      onStatusChange?.({
        tone: 'success',
        title: 'Colecoes atualizadas',
        description: 'As entregas por pessoa, par e grupo ja foram recalculadas.',
      });
    },
    onError: () => {
      onStatusChange?.({
        tone: 'danger',
        title: 'Falha ao gerar colecoes',
        description: 'Nao foi possivel atualizar as entregas relacionais agora.',
      });
    },
  });

  const summary = relationalCollectionsQuery.data?.summary;
  const collections = relationalCollectionsQuery.data?.collections ?? [];

  const readyCollections = useMemo(
    () => collections.filter((collection) => collection.visibility === 'public_ready').slice(0, 3),
    [collections],
  );

  const selectedCollections = useMemo(() => {
    if (!selectedPerson) return [];

    return collections.filter((collection) => isCollectionForPerson(collection, selectedPerson.id)).slice(0, 4);
  }, [collections, selectedPerson]);

  const relationshipCollections = useMemo(
    () => collections.filter((collection) => collection.collection_type !== 'person_best_of').slice(0, 4),
    [collections],
  );

  const spotlightCollections = selectedCollections.length > 0 ? selectedCollections : relationshipCollections;

  return (
    <Card className="border-border/60">
      <CardHeader className="flex flex-row items-center justify-between gap-3">
        <div className="space-y-1">
          <CardTitle className="flex items-center gap-2">
            <Images className="h-4 w-4 text-primary" />
            Momentos e entregas
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Colecoes locais por pessoa, par, grupo e must-have, prontas para operador e futuras entregas guest-facing.
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => refreshMutation.mutate()}
          disabled={refreshMutation.isPending}
        >
          <RefreshCcw className={refreshMutation.isPending ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
          Gerar agora
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Colecoes</p>
            <p className="text-lg font-semibold">{summary?.total_collections ?? 0}</p>
          </div>
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Prontas</p>
            <p className="text-lg font-semibold">{summary?.public_ready_collections ?? 0}</p>
          </div>
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Internas</p>
            <p className="text-lg font-semibold">{summary?.internal_collections ?? 0}</p>
          </div>
          <div className="rounded-2xl border border-border/60 bg-background px-3 py-2">
            <p className="text-xs uppercase text-muted-foreground">Must-have</p>
            <p className="text-lg font-semibold">{summary?.must_have_deliveries ?? 0}</p>
          </div>
        </div>

        {relationalCollectionsQuery.isLoading ? (
          <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">
            Carregando colecoes relacionais...
          </div>
        ) : null}

        {!relationalCollectionsQuery.isLoading && collections.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-border/60 px-4 py-6 text-sm text-muted-foreground">
            Nenhuma colecao foi materializada ainda para esse evento.
          </div>
        ) : null}

        {readyCollections.length > 0 ? (
          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <p className="text-sm font-semibold">Entregas prontas</p>
              <Badge variant="outline">{readyCollections.length}</Badge>
            </div>
            <div className="grid gap-3 xl:grid-cols-3">
              {readyCollections.map((collection) => (
                <CollectionCard key={collection.id} collection={collection} />
              ))}
            </div>
          </div>
        ) : null}

        {spotlightCollections.length > 0 ? (
          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <p className="text-sm font-semibold">
                {selectedPerson ? `Colecoes ligadas a ${selectedPerson.display_name}` : 'Colecoes por vinculo'}
              </p>
              {summary?.last_generated_at ? (
                <span className="text-xs text-muted-foreground">Atualizado em {new Date(summary.last_generated_at).toLocaleString('pt-BR')}</span>
              ) : null}
            </div>
            <div className="grid gap-3 xl:grid-cols-2">
              {spotlightCollections.map((collection) => (
                <CollectionCard key={collection.id} collection={collection} />
              ))}
            </div>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
