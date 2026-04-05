import { useEffect, useMemo } from 'react';
import { Link, useParams } from 'react-router-dom';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import {
  ExternalLink,
  Gamepad2,
  Image,
  LayoutGrid,
  Loader2,
  Plus,
  Puzzle,
  Save,
  Settings2,
} from 'lucide-react';

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventMediaItem } from '@/lib/api-types';
import { listEventMedia } from '@/modules/events/api';
import {
  createEventPlayGame,
  fetchEventPlayManager,
  updateEventPlaySettings,
} from '@/modules/play/api/playApi';
import { EventPlayGameCard } from '@/modules/play/components/EventPlayGameCard';
import { PlayAnalyticsPanel } from '@/modules/play/components/PlayAnalyticsPanel';
import { PlayPublicLinkField } from '@/modules/play/components/PlayPublicLinkField';
import {
  buildDefaultGameSettings,
} from '@/modules/play/utils/game-settings';
import {
  buildEventPlayGameUrl,
  buildEventPlayHubUrl,
} from '@/modules/play/utils/public-links';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';

const settingsSchema = z.object({
  is_enabled: z.boolean(),
  memory_enabled: z.boolean(),
  puzzle_enabled: z.boolean(),
  memory_card_count: z.coerce.number().min(2).max(20),
  puzzle_piece_count: z.coerce.number().min(4).max(25),
  auto_refresh_assets: z.boolean(),
  ranking_enabled: z.boolean(),
});

const createGameSchema = z.object({
  game_type_key: z.string().min(1),
  title: z.string().min(3, 'Informe um titulo'),
  slug: z.string().optional(),
  is_active: z.boolean(),
  ranking_enabled: z.boolean(),
  sort_order: z.coerce.number().min(0).max(999),
});

type SettingsFormValues = z.infer<typeof settingsSchema>;
type CreateGameFormValues = z.infer<typeof createGameSchema>;

function filterPublished(media: ApiEventMediaItem[]) {
  return media.filter((item) => item.status === 'approved' || item.status === 'published');
}

export default function EventPlayManagerPage() {
  const { id } = useParams<{ id: string }>();
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const managerQuery = useQuery({
    queryKey: ['event-play-manager', id],
    enabled: !!id,
    queryFn: () => fetchEventPlayManager(id as string),
  });

  const mediaQuery = useQuery({
    queryKey: ['event-play-media', id],
    enabled: !!id,
    queryFn: () => listEventMedia(id as string, 60),
  });

  const settingsForm = useForm<SettingsFormValues>({
    resolver: zodResolver(settingsSchema),
    defaultValues: {
      is_enabled: false,
      memory_enabled: true,
      puzzle_enabled: true,
      memory_card_count: 6,
      puzzle_piece_count: 9,
      auto_refresh_assets: true,
      ranking_enabled: true,
    },
  });

  const createForm = useForm<CreateGameFormValues>({
    resolver: zodResolver(createGameSchema),
    defaultValues: {
      game_type_key: 'memory',
      title: '',
      slug: '',
      is_active: true,
      ranking_enabled: true,
      sort_order: 0,
    },
  });

  useEffect(() => {
    if (!managerQuery.data) {
      return;
    }

    settingsForm.reset(managerQuery.data.settings);

    const firstGameType = managerQuery.data.catalog[0]?.key ?? 'memory';
    createForm.reset({
      game_type_key: firstGameType,
      title: '',
      slug: '',
      is_active: true,
      ranking_enabled: managerQuery.data.settings.ranking_enabled,
      sort_order: managerQuery.data.games.length,
    });
  }, [createForm, managerQuery.data, settingsForm]);

  const updateSettingsMutation = useMutation({
    mutationFn: (values: SettingsFormValues) => updateEventPlaySettings(id as string, values),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-play-manager', id] });
      toast({
        title: 'Play atualizado',
        description: 'As configuracoes gerais do modulo foram salvas.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao salvar configuracoes',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const createGameMutation = useMutation({
    mutationFn: (values: CreateGameFormValues) => createEventPlayGame(id as string, {
      ...values,
      settings: buildDefaultGameSettings(values.game_type_key, settingsForm.getValues()),
    }),
    onSuccess: async (_, values) => {
      await queryClient.invalidateQueries({ queryKey: ['event-play-manager', id] });
      toast({
        title: 'Jogo criado',
        description: `${values.title} foi adicionado ao evento.`,
      });

      createForm.reset({
        ...values,
        title: '',
        slug: '',
        sort_order: (managerQuery.data?.games.length ?? 0) + 1,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao criar jogo',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const publishedMedia = useMemo(
    () => filterPublished(mediaQuery.data?.data ?? []),
    [mediaQuery.data?.data],
  );

  const watchedSettings = settingsForm.watch();

  const managerStats = useMemo(() => {
    const data = managerQuery.data;

    if (!data) {
      return {
        totalGames: 0,
        activeGames: 0,
        assetsLinked: 0,
        publishedAssets: 0,
      };
    }

    return {
      totalGames: data.games.length,
      activeGames: data.games.filter((game) => game.is_active).length,
      assetsLinked: data.games.reduce((total, game) => total + (game.assets?.length ?? 0), 0),
      publishedAssets: publishedMedia.length,
    };
  }, [managerQuery.data, publishedMedia.length]);

  const enabledCatalog = useMemo(() => {
    const data = managerQuery.data;
    if (!data) return [];

    return data.catalog.filter((item) => {
      if (item.key === 'memory') return watchedSettings.memory_enabled;
      if (item.key === 'puzzle') return watchedSettings.puzzle_enabled;

      return true;
    });
  }, [managerQuery.data, watchedSettings.memory_enabled, watchedSettings.puzzle_enabled]);

  if (!id) {
    return <p className="text-sm text-destructive">Evento invalido.</p>;
  }

  if (managerQuery.isLoading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center">
        <Loader2 className="h-6 w-6 animate-spin text-primary" />
      </div>
    );
  }

  if (managerQuery.isError || !managerQuery.data) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-destructive">
        Nao foi possivel carregar a gestao do Play para este evento.
      </div>
    );
  }

  const manager = managerQuery.data;
  const hubUrl = buildEventPlayHubUrl(manager.event.slug);

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Play - ${manager.event.title}`}
        description="Organize os jogos do evento com links publicos visiveis, configuracao clara e assets separados por card."
        actions={(
          <div className="flex flex-wrap gap-2">
            <Button asChild variant="outline">
              <Link to={`/events/${id}`}>
                Voltar ao evento
              </Link>
            </Button>
            <Button asChild>
              <Link to={`/e/${manager.event.slug}/play`} target="_blank" rel="noreferrer">
                <ExternalLink className="mr-1.5 h-4 w-4" />
                Abrir hub publico
              </Link>
            </Button>
          </div>
        )}
      />

      <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <StatsCard title="Jogos configurados" value={managerStats.totalGames} icon={Gamepad2} />
        <StatsCard title="Jogos ativos" value={managerStats.activeGames} icon={LayoutGrid} />
        <StatsCard title="Assets vinculados" value={managerStats.assetsLinked} icon={Image} />
        <StatsCard title="Fotos elegiveis" value={managerStats.publishedAssets} icon={Puzzle} />
      </div>

      <div className="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
        <Card className="border-white/70 bg-white/90 shadow-sm">
          <CardHeader className="space-y-1 pb-3">
            <CardTitle className="text-base">Links publicos e atalhos</CardTitle>
            <p className="text-sm text-muted-foreground">
              O hub do evento e os links diretos de cada jogo ficam visiveis aqui, com copiar e abrir em um clique.
            </p>
          </CardHeader>
          <CardContent className="space-y-4">
            <PlayPublicLinkField
              label="Hub publico do Play"
              description="Use este endereco para divulgar a home publica de jogos do evento."
              url={hubUrl}
            />

            <div className="space-y-3">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold">Links diretos dos jogos</p>
                  <p className="text-xs text-muted-foreground">
                    Mesmo jogos inativos continuam com slug visivel para configuracao e validacao.
                  </p>
                </div>
                <Badge variant="outline">{manager.games.length} jogos</Badge>
              </div>

              {manager.games.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-muted-foreground">
                  Crie um jogo para gerar uma URL publica individual.
                </div>
              ) : (
                <div className="max-h-[360px] space-y-3 overflow-y-auto pr-1">
                  {manager.games.map((game) => (
                    <PlayPublicLinkField
                      key={game.id}
                      label={game.title}
                      description={game.is_active ? 'Link publico pronto para divulgacao.' : 'Slug configurado, mas o jogo ainda esta inativo.'}
                      url={buildEventPlayGameUrl(manager.event.slug, game.slug)}
                      compact
                    />
                  ))}
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        <Card className="border-white/70 bg-white/90 shadow-sm">
          <CardHeader className="space-y-1 pb-3">
            <CardTitle className="text-base">Criar jogo no evento</CardTitle>
            <p className="text-sm text-muted-foreground">
              Crie o jogo e depois ajuste regras, link publico e selecao de fotos dentro do card do jogo.
            </p>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={createForm.handleSubmit((values) => createGameMutation.mutate(values))}>
              <div className="space-y-2">
                <Label>Tipo de jogo</Label>
                <Controller
                  name="game_type_key"
                  control={createForm.control}
                  render={({ field }) => (
                    <Select value={field.value} onValueChange={field.onChange}>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione um tipo" />
                      </SelectTrigger>
                      <SelectContent>
                        {enabledCatalog.map((item) => (
                          <SelectItem key={item.key} value={item.key}>
                            {item.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                />
              </div>

              <div className="space-y-2">
                <Label>Titulo</Label>
                <Input placeholder="Ex.: Memoria do casamento" {...createForm.register('title')} />
              </div>

              <div className="space-y-2">
                <Label>Slug</Label>
                <Input placeholder="Opcional. Se vazio, o backend gera." {...createForm.register('slug')} />
              </div>

              <div className="space-y-2">
                <Label>Ordem</Label>
                <Input type="number" {...createForm.register('sort_order', { valueAsNumber: true })} />
              </div>

              <div className="grid gap-3 md:grid-cols-2">
                <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <Label htmlFor="create-active">Ativo</Label>
                  <Controller
                    name="is_active"
                    control={createForm.control}
                    render={({ field }) => (
                      <Switch id="create-active" checked={field.value} onCheckedChange={field.onChange} />
                    )}
                  />
                </div>

                <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <Label htmlFor="create-ranking">Ranking</Label>
                  <Controller
                    name="ranking_enabled"
                    control={createForm.control}
                    render={({ field }) => (
                      <Switch id="create-ranking" checked={field.value} onCheckedChange={field.onChange} />
                    )}
                  />
                </div>
              </div>

              <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
                O backend continua gerando sessao publica, runtime de assets e ranking por jogo. Depois da criacao, o card do jogo concentra todo o restante.
              </div>

              <Button type="submit" disabled={createGameMutation.isPending || enabledCatalog.length === 0}>
                {createGameMutation.isPending ? (
                  <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                ) : (
                  <Plus className="mr-1.5 h-4 w-4" />
                )}
                Criar jogo
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-4">
        <div>
          <h2 className="text-lg font-semibold tracking-tight">Jogos do evento</h2>
          <p className="text-sm text-muted-foreground">
            Cada card mostra a URL publica do jogo logo no topo e separa publicacao, regras e fotos em blocos recolhiveis.
          </p>
        </div>

        {manager.games.length === 0 ? (
          <Card className="border-dashed border-slate-200 bg-slate-50/80 shadow-none">
            <CardContent className="px-6 py-10 text-center">
              <p className="text-sm font-medium">Nenhum jogo configurado ainda.</p>
              <p className="mt-2 text-sm text-muted-foreground">
                Crie o primeiro jogo usando o bloco acima para ativar a camada publica do Play.
              </p>
            </CardContent>
          </Card>
        ) : (
          manager.games.map((game) => (
            <EventPlayGameCard
              key={game.id}
              eventId={id}
              eventSlug={manager.event.slug}
              game={game}
              availableMedia={publishedMedia}
            />
          ))
        )}
      </div>

      <Card className="border-white/70 bg-white/90 shadow-sm">
        <CardHeader className="space-y-1 pb-3">
          <CardTitle className="flex items-center gap-2 text-base">
            <Settings2 className="h-4 w-4" />
            Modulo Play do evento
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Configuracoes globais, catalogo liberado e analytics avancados ficam organizados aqui embaixo para nao competir com os jogos.
          </p>
        </CardHeader>
        <CardContent>
          <Accordion type="single" collapsible defaultValue="settings" className="w-full">
            <AccordionItem value="settings">
              <AccordionTrigger>Configuracoes gerais do modulo</AccordionTrigger>
              <AccordionContent>
                <form className="space-y-5" onSubmit={settingsForm.handleSubmit((values) => updateSettingsMutation.mutate(values))}>
                  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                      <Label htmlFor="play-enabled">Play ativo no evento</Label>
                      <Controller
                        name="is_enabled"
                        control={settingsForm.control}
                        render={({ field }) => (
                          <Switch id="play-enabled" checked={field.value} onCheckedChange={field.onChange} />
                        )}
                      />
                    </div>

                    <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                      <Label htmlFor="memory-enabled">Catalogo Memory</Label>
                      <Controller
                        name="memory_enabled"
                        control={settingsForm.control}
                        render={({ field }) => (
                          <Switch id="memory-enabled" checked={field.value} onCheckedChange={field.onChange} />
                        )}
                      />
                    </div>

                    <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                      <Label htmlFor="puzzle-enabled">Catalogo Puzzle</Label>
                      <Controller
                        name="puzzle_enabled"
                        control={settingsForm.control}
                        render={({ field }) => (
                          <Switch id="puzzle-enabled" checked={field.value} onCheckedChange={field.onChange} />
                        )}
                      />
                    </div>

                    <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                      <Label htmlFor="play-ranking">Ranking global habilitado</Label>
                      <Controller
                        name="ranking_enabled"
                        control={settingsForm.control}
                        render={({ field }) => (
                          <Switch id="play-ranking" checked={field.value} onCheckedChange={field.onChange} />
                        )}
                      />
                    </div>

                    <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                      <Label htmlFor="play-auto-assets">Fallback automatico de assets</Label>
                      <Controller
                        name="auto_refresh_assets"
                        control={settingsForm.control}
                        render={({ field }) => (
                          <Switch id="play-auto-assets" checked={field.value} onCheckedChange={field.onChange} />
                        )}
                      />
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="memory-card-count">Pares padrao do Memory</Label>
                      <Input id="memory-card-count" type="number" {...settingsForm.register('memory_card_count', { valueAsNumber: true })} />
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="puzzle-piece-count">Pecas base do Puzzle</Label>
                      <Input id="puzzle-piece-count" type="number" {...settingsForm.register('puzzle_piece_count', { valueAsNumber: true })} />
                    </div>
                  </div>

                  <Button type="submit" disabled={updateSettingsMutation.isPending}>
                    {updateSettingsMutation.isPending ? (
                      <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                    ) : (
                      <Save className="mr-1.5 h-4 w-4" />
                    )}
                    Salvar configuracoes
                  </Button>
                </form>
              </AccordionContent>
            </AccordionItem>

            <AccordionItem value="catalog">
              <AccordionTrigger>Catalogo disponivel</AccordionTrigger>
              <AccordionContent>
                <div className="grid gap-4 md:grid-cols-2">
                  {manager.catalog.map((item) => {
                    const enabled = enabledCatalog.some((catalogItem) => catalogItem.key === item.key);

                    return (
                      <div key={item.key} className={`rounded-3xl border p-4 ${enabled ? 'border-emerald-200 bg-emerald-50/70' : 'border-slate-200 bg-slate-50/80'}`}>
                        <div className="flex items-center justify-between gap-3">
                          <div>
                            <p className="font-semibold">{item.name}</p>
                            <p className="mt-1 text-sm text-muted-foreground">{item.description}</p>
                          </div>
                          <Badge variant={enabled ? 'outline' : 'secondary'}>
                            {enabled ? 'Liberado' : 'Bloqueado'}
                          </Badge>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </AccordionContent>
            </AccordionItem>

            <AccordionItem value="analytics">
              <AccordionTrigger>Analytics do Play</AccordionTrigger>
              <AccordionContent>
                <PlayAnalyticsPanel eventId={id} games={manager.games} />
              </AccordionContent>
            </AccordionItem>
          </Accordion>
        </CardContent>
      </Card>
    </div>
  );
}
