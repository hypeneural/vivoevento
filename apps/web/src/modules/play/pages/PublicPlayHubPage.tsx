import { useEffect } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ExternalLink, Loader2, Trophy } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { fetchPublicPlayManifest } from '@/modules/play/api/playApi';
import { PwaInstallBanner } from '@/modules/play/components/PwaInstallBanner';
import { getPlayPrefetchPolicy, schedulePlayIdleTask, warmPlayableGameRuntime, warmPublicGameExperience } from '@/modules/play/utils/runtime-prefetch';

export default function PublicPlayHubPage() {
  const { slug } = useParams<{ slug: string }>();

  const manifestQuery = useQuery({
    queryKey: ['public-play-manifest', slug],
    enabled: !!slug,
    queryFn: () => fetchPublicPlayManifest(slug as string),
  });

  useEffect(() => {
    if (!slug || !manifestQuery.data) {
      return undefined;
    }

    const policy = getPlayPrefetchPolicy();
    if (!policy.allowViewportWarmup) {
      return undefined;
    }

    return schedulePlayIdleTask(() => {
      manifestQuery.data?.games.slice(0, 2).forEach((game) => {
        warmPublicGameExperience({
          eventSlug: slug,
          gameSlug: game.slug,
          gameTypeKey: game.game_type_key,
        }, 'viewport');
      });
    });
  }, [manifestQuery.data, slug]);

  if (manifestQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 text-white">
        <Loader2 className="h-6 w-6 animate-spin" />
      </div>
    );
  }

  if (manifestQuery.isError || !manifestQuery.data) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 px-6 text-center text-sm text-white/80">
        O hub publico do Play nao esta disponivel para este evento.
      </div>
    );
  }

  const manifest = manifestQuery.data;

  return (
    <div className="min-h-[100dvh] bg-slate-950 text-white">
      <section className="relative overflow-hidden">
        {manifest.event.cover_image_url ? (
          <img
            src={manifest.event.cover_image_url}
            alt={manifest.event.title}
            className="absolute inset-0 h-full w-full object-cover opacity-20"
          />
        ) : null}
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(34,197,94,0.18),_transparent_35%),linear-gradient(135deg,#020617_5%,#0f172a_55%,#111827_100%)]" />

        <div className="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-16 md:px-10">
          <div className="max-w-3xl space-y-4">
            <Badge variant="secondary" className="w-fit bg-white/10 text-white hover:bg-white/10">
              Evento Vivo Play
            </Badge>
            <div className="space-y-2">
              <h1 className="text-3xl font-semibold tracking-tight md:text-5xl">{manifest.event.title}</h1>
              <p className="max-w-2xl text-sm text-white/70 md:text-base">
                Escolha um jogo, envie sua pontuacao e acompanhe o ranking do evento em tempo real.
              </p>
            </div>
          </div>

          <PwaInstallBanner />

          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {manifest.games.map((game) => (
              <Card key={game.id} className="border-white/10 bg-white/5 shadow-none backdrop-blur">
                <CardContent className="space-y-5 p-5">
                  <div className="space-y-2">
                    <div className="flex items-center gap-2">
                      <Badge variant="outline" className="border-white/20 bg-white/5 text-white">
                        {game.game_type_name || game.game_type_key}
                      </Badge>
                      <Badge variant={game.ranking_enabled ? 'outline' : 'secondary'} className="border-white/20 bg-white/5 text-white">
                        {game.ranking_enabled ? 'Ranking on' : 'Ranking off'}
                      </Badge>
                    </div>
                    <h2 className="text-xl font-semibold">{game.title}</h2>
                    <p className="text-sm text-white/65">
                      slug `{game.slug}` · ordem {game.sort_order}
                    </p>
                  </div>

                  <div className="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/75">
                    <Trophy className="h-4 w-4 text-emerald-300" />
                    Ranking por jogo e historico das ultimas partidas ligados pela API.
                  </div>

                  <div className="flex gap-2">
                    <Button asChild className="flex-1">
                      <Link
                        to={`/e/${manifest.event.slug}/play/${game.slug}`}
                        onMouseEnter={() => {
                          warmPublicGameExperience({
                            eventSlug: manifest.event.slug,
                            gameSlug: game.slug,
                            gameTypeKey: game.game_type_key,
                          });
                          void warmPlayableGameRuntime(game.game_type_key, 'intent');
                        }}
                        onFocus={() => warmPublicGameExperience({
                          eventSlug: manifest.event.slug,
                          gameSlug: game.slug,
                          gameTypeKey: game.game_type_key,
                        })}
                        onTouchStart={() => {
                          warmPublicGameExperience({
                            eventSlug: manifest.event.slug,
                            gameSlug: game.slug,
                            gameTypeKey: game.game_type_key,
                          });
                          void warmPlayableGameRuntime(game.game_type_key, 'intent');
                        }}
                      >
                        Jogar agora
                      </Link>
                    </Button>
                    <Button asChild variant="outline" className="border-white/20 bg-white/5 text-white hover:bg-white/10 hover:text-white">
                      <Link to={`/e/${manifest.event.slug}/gallery`} target="_blank" rel="noreferrer">
                        <ExternalLink className="mr-1.5 h-4 w-4" />
                        Galeria
                      </Link>
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
