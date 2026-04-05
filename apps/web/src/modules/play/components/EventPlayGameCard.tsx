import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Image, Loader2, Save, Trash2 } from 'lucide-react';

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventMediaItem, PlayEventGame } from '@/lib/api-types';
import {
  deleteEventPlayGame,
  syncEventPlayGameAssets,
  updateEventPlayGame,
} from '@/modules/play/api/playApi';
import { PlayPublicLinkField } from '@/modules/play/components/PlayPublicLinkField';
import {
  buildGameDraftSettings,
  createGameDraft,
  type GameDraft,
} from '@/modules/play/utils/game-settings';
import { buildEventPlayGameUrl } from '@/modules/play/utils/public-links';

type EventPlayGameCardProps = {
  eventId: string;
  eventSlug: string;
  game: PlayEventGame;
  availableMedia: ApiEventMediaItem[];
};

function formatMetricLabel(label: string, value: number) {
  return `${label}: ${value}`;
}

export function EventPlayGameCard({
  eventId,
  eventSlug,
  game,
  availableMedia,
}: EventPlayGameCardProps) {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [draft, setDraft] = useState<GameDraft>(() => createGameDraft(game));
  const [selectedMediaIds, setSelectedMediaIds] = useState<number[]>(() => game.assets?.map((asset) => asset.media_id) ?? []);

  useEffect(() => {
    setDraft(createGameDraft(game));
    setSelectedMediaIds(game.assets?.map((asset) => asset.media_id) ?? []);
  }, [game]);

  const publicUrl = useMemo(
    () => buildEventPlayGameUrl(eventSlug, draft.slug || game.slug),
    [draft.slug, eventSlug, game.slug],
  );

  const saveMutation = useMutation({
    mutationFn: () => updateEventPlayGame(eventId, game.id, {
      title: draft.title,
      slug: draft.slug,
      is_active: draft.is_active,
      ranking_enabled: draft.ranking_enabled,
      sort_order: draft.sort_order,
      settings: buildGameDraftSettings(game.game_type_key, draft),
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-play-manager', eventId] });
      toast({
        title: 'Jogo atualizado',
        description: `Configuracao de ${game.title} salva com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao salvar jogo',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const syncAssetsMutation = useMutation({
    mutationFn: () => syncEventPlayGameAssets(
      eventId,
      game.id,
      selectedMediaIds.map((mediaId, index) => ({
        media_id: mediaId,
        role: 'primary',
        sort_order: index,
      })),
    ),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-play-manager', eventId] });
      toast({
        title: 'Assets atualizados',
        description: 'As fotos vinculadas ao jogo foram sincronizadas.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar assets',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteEventPlayGame(eventId, game.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-play-manager', eventId] });
      toast({
        title: 'Jogo removido',
        description: `${game.title} foi removido do evento.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao remover jogo',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  function toggleMedia(mediaId: number) {
    setSelectedMediaIds((current) => (
      current.includes(mediaId)
        ? current.filter((id) => id !== mediaId)
        : [...current, mediaId]
    ));
  }

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="space-y-4 pb-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="space-y-3">
            <div className="space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <CardTitle className="text-base">{game.title}</CardTitle>
                <Badge variant="outline">{game.game_type_name || game.game_type_key}</Badge>
                <Badge variant={draft.is_active ? 'outline' : 'secondary'}>
                  {draft.is_active ? 'Ativo' : 'Inativo'}
                </Badge>
                <Badge variant={draft.ranking_enabled ? 'outline' : 'secondary'}>
                  {draft.ranking_enabled ? 'Ranking ligado' : 'Ranking desligado'}
                </Badge>
              </div>

              <p className="text-sm text-muted-foreground">
                Edite o link publico, regras do jogo e fotos em blocos separados para evitar ruido.
              </p>
            </div>

            <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
              <span className="rounded-full bg-slate-100 px-3 py-1">{formatMetricLabel('Assets', selectedMediaIds.length)}</span>
              <span className="rounded-full bg-slate-100 px-3 py-1">{formatMetricLabel('Sessoes', game.sessions_count ?? 0)}</span>
              <span className="rounded-full bg-slate-100 px-3 py-1">{formatMetricLabel('Jogadores no ranking', game.rankings_count ?? 0)}</span>
            </div>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              if (window.confirm(`Remover o jogo "${game.title}" deste evento?`)) {
                deleteMutation.mutate();
              }
            }}
            disabled={deleteMutation.isPending}
          >
            {deleteMutation.isPending ? (
              <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
            ) : (
              <Trash2 className="mr-1.5 h-4 w-4" />
            )}
            Remover
          </Button>
        </div>

        <PlayPublicLinkField
          label="Pagina publica do jogo"
          description="Esse link abre o jogo direto para o convidado, sem passar pelo hub do evento."
          url={publicUrl}
          compact
        />
      </CardHeader>

      <CardContent className="space-y-5">
        <Accordion type="multiple" defaultValue={['public', 'rules']} className="w-full rounded-3xl border border-slate-200 bg-slate-50/60 px-4">
          <AccordionItem value="public" className="border-slate-200">
            <AccordionTrigger className="text-left text-sm font-semibold text-slate-900">
              Publicacao e link
            </AccordionTrigger>
            <AccordionContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label>Titulo</Label>
                  <Input value={draft.title} onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))} />
                </div>

                <div className="space-y-2">
                  <Label>Slug publico</Label>
                  <Input value={draft.slug} onChange={(event) => setDraft((current) => ({ ...current, slug: event.target.value }))} />
                </div>

                <div className="space-y-2">
                  <Label>Ordem</Label>
                  <Input
                    type="number"
                    value={draft.sort_order}
                    onChange={(event) => setDraft((current) => ({ ...current, sort_order: Number(event.target.value || 0) }))}
                  />
                </div>

                <div className="grid gap-3">
                  <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                    <Label htmlFor={`active-${game.id}`}>Jogo ativo</Label>
                    <Switch
                      id={`active-${game.id}`}
                      checked={draft.is_active}
                      onCheckedChange={(checked) => setDraft((current) => ({ ...current, is_active: checked }))}
                    />
                  </div>

                  <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                    <Label htmlFor={`ranking-${game.id}`}>Ranking publico</Label>
                    <Switch
                      id={`ranking-${game.id}`}
                      checked={draft.ranking_enabled}
                      onCheckedChange={(checked) => setDraft((current) => ({ ...current, ranking_enabled: checked }))}
                    />
                  </div>
                </div>
              </div>
            </AccordionContent>
          </AccordionItem>

          <AccordionItem value="rules" className="border-slate-200">
            <AccordionTrigger className="text-left text-sm font-semibold text-slate-900">
              Regras do jogo
            </AccordionTrigger>
            <AccordionContent>
              {game.game_type_key === 'puzzle' ? (
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label>Grid</Label>
                    <Select value={draft.gridSize} onValueChange={(value: GameDraft['gridSize']) => setDraft((current) => ({ ...current, gridSize: value }))}>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="2x2">2x2</SelectItem>
                        <SelectItem value="3x3">3x3</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label>Tolerancia de snap</Label>
                    <Input
                      type="number"
                      value={draft.dragTolerance}
                      onChange={(event) => setDraft((current) => ({ ...current, dragTolerance: Number(event.target.value || 0) }))}
                    />
                  </div>

                  <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                    <Label htmlFor={`snap-${game.id}`}>Snap habilitado</Label>
                    <Switch
                      id={`snap-${game.id}`}
                      checked={draft.snapEnabled}
                      onCheckedChange={(checked) => setDraft((current) => ({ ...current, snapEnabled: checked }))}
                    />
                  </div>

                  <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                    <Label htmlFor={`reference-${game.id}`}>Mostrar referencia</Label>
                    <Switch
                      id={`reference-${game.id}`}
                      checked={draft.showReferenceImage}
                      onCheckedChange={(checked) => setDraft((current) => ({ ...current, showReferenceImage: checked }))}
                    />
                  </div>
                </div>
              ) : (
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label>Pares</Label>
                    <Input
                      type="number"
                      value={draft.pairsCount}
                      onChange={(event) => setDraft((current) => ({ ...current, pairsCount: Number(event.target.value || 0) }))}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label>Dificuldade</Label>
                    <Select value={draft.difficulty} onValueChange={(value: GameDraft['difficulty']) => setDraft((current) => ({ ...current, difficulty: value }))}>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="easy">Facil</SelectItem>
                        <SelectItem value="normal">Normal</SelectItem>
                        <SelectItem value="hard">Dificil</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label>Preview inicial (seg)</Label>
                    <Input
                      type="number"
                      value={draft.showPreviewSeconds}
                      onChange={(event) => setDraft((current) => ({ ...current, showPreviewSeconds: Number(event.target.value || 0) }))}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label>Delay de retorno (ms)</Label>
                    <Input
                      type="number"
                      value={draft.flipBackDelayMs}
                      onChange={(event) => setDraft((current) => ({ ...current, flipBackDelayMs: Number(event.target.value || 0) }))}
                    />
                  </div>

                  <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 md:col-span-2">
                    <Label htmlFor={`duplicate-${game.id}`}>Permitir fotos duplicadas na fonte</Label>
                    <Switch
                      id={`duplicate-${game.id}`}
                      checked={draft.allowDuplicateSource}
                      onCheckedChange={(checked) => setDraft((current) => ({ ...current, allowDuplicateSource: checked }))}
                    />
                  </div>
                </div>
              )}
            </AccordionContent>
          </AccordionItem>

          <AccordionItem value="assets" className="border-0">
            <AccordionTrigger className="text-left text-sm font-semibold text-slate-900">
              Fotos do jogo
            </AccordionTrigger>
            <AccordionContent className="space-y-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold">Fotos vinculadas</p>
                  <p className="text-xs text-muted-foreground">
                    Se nada for selecionado, o backend usa as fotos aprovadas ou publicadas do evento como fallback.
                  </p>
                </div>

                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => syncAssetsMutation.mutate()}
                  disabled={syncAssetsMutation.isPending}
                >
                  {syncAssetsMutation.isPending ? (
                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                  ) : (
                    <Image className="mr-1.5 h-4 w-4" />
                  )}
                  Sincronizar assets
                </Button>
              </div>

              {availableMedia.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-sm text-muted-foreground">
                  Este evento ainda nao possui fotos aprovadas ou publicadas para jogos.
                </div>
              ) : (
                <div className="grid max-h-[420px] grid-cols-2 gap-3 overflow-y-auto rounded-3xl border border-slate-200 bg-white p-3 md:grid-cols-3 xl:grid-cols-4">
                  {availableMedia.map((media) => {
                    const selected = selectedMediaIds.includes(media.id);

                    return (
                      <button
                        key={media.id}
                        type="button"
                        onClick={() => toggleMedia(media.id)}
                        className={`overflow-hidden rounded-2xl border text-left transition ${selected ? 'border-emerald-400 ring-2 ring-emerald-200' : 'border-slate-200 hover:border-slate-300'}`}
                      >
                        <div className="aspect-square bg-slate-100">
                          {media.thumbnail_url ? (
                            <img src={media.thumbnail_url} alt={media.caption || media.sender_name} className="h-full w-full object-cover" />
                          ) : (
                            <div className="flex h-full items-center justify-center text-xs text-muted-foreground">
                              Sem preview
                            </div>
                          )}
                        </div>
                        <div className="flex items-center justify-between gap-2 px-3 py-2">
                          <span className="truncate text-xs text-muted-foreground">{media.sender_name || `Midia ${media.id}`}</span>
                          <Badge variant={selected ? 'outline' : 'secondary'}>
                            {selected ? 'Selecionada' : 'Livre'}
                          </Badge>
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}
            </AccordionContent>
          </AccordionItem>
        </Accordion>

        <div className="flex flex-wrap gap-2">
          <Button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
            {saveMutation.isPending ? (
              <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
            ) : (
              <Save className="mr-1.5 h-4 w-4" />
            )}
            Salvar jogo
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
