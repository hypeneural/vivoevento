import { type ReactNode } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Check, Copy, Monitor } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type {
  ApiWallInsightsRecentItem,
  ApiWallLiveSnapshotResponse,
  ApiWallSettings,
  ApiWallSimulationPreviewItem,
  ApiWallSimulationResponse,
} from '@/lib/api-types';

import { HelpTooltip } from '../../WallManagerHelp';
import { WallManagerSection } from '../../WallManagerSection';
import { WALL_INSIGHTS_COPY, formatWallRecentStatusLabel } from '../../../wall-copy';
import { getWallMediaSemanticMeta, getWallSourceMeta } from '../../../wall-source-meta';
import { formatWallRelativeTime } from '../../../wall-view-models';
import { WallAdvanceClock } from './WallAdvanceClock';
import { WallDraftPreviewCard } from './WallDraftPreviewCard';
import { WallUpcomingTimeline } from './WallUpcomingTimeline';

interface WallHeroStageProps {
  activeTab: 'live' | 'upcoming';
  onTabChange: (tab: 'live' | 'upcoming') => void;
  isLive: boolean;
  isPaused: boolean;
  status: string;
  selectedMedia: ApiWallInsightsRecentItem | null;
  liveSnapshot: ApiWallLiveSnapshotResponse | null;
  eventTitle: string;
  eventSchedule: string;
  wallCode: string;
  copied: boolean;
  onCopyWallCode: () => void;
  hasUnsavedChanges: boolean;
  onOpenSelectedMediaDetails: () => void;
  wallSettings: ApiWallSettings;
  selectionSummary: string;
  simulationSummary: ApiWallSimulationResponse['summary'] | null;
  simulationPreview: ApiWallSimulationPreviewItem[];
  simulationExplanation: string[];
  isSimulationLoading: boolean;
  isSimulationError: boolean;
  isSimulationRefreshing: boolean;
  isSimulationDraftPending: boolean;
}

export function WallHeroStage({
  activeTab,
  onTabChange,
  isLive,
  isPaused,
  status,
  selectedMedia,
  liveSnapshot,
  eventTitle,
  eventSchedule,
  wallCode,
  copied,
  onCopyWallCode,
  hasUnsavedChanges,
  onOpenSelectedMediaDetails,
  wallSettings,
  selectionSummary,
  simulationSummary,
  simulationPreview,
  simulationExplanation,
  isSimulationLoading,
  isSimulationError,
  isSimulationRefreshing,
  isSimulationDraftPending,
}: WallHeroStageProps) {
  const activeLiveItem = selectedMedia ?? liveSnapshot?.currentItem ?? null;
  const selectedMediaSourceMeta = activeLiveItem ? getWallSourceMeta(activeLiveItem.source) : null;
  const activeLiveMediaMeta = activeLiveItem
    ? getWallMediaSemanticMeta({
        isVideo: activeLiveItem.isVideo,
        durationSeconds: activeLiveItem.durationSeconds,
        videoPolicyLabel: activeLiveItem.videoPolicyLabel,
      })
    : null;
  const currentSnapshotSourceMeta = liveSnapshot?.currentItem
    ? getWallSourceMeta(liveSnapshot.currentItem.source)
    : null;
  const currentSnapshotMediaMeta = liveSnapshot?.currentItem
    ? getWallMediaSemanticMeta({
        isVideo: liveSnapshot.currentItem.isVideo,
        durationSeconds: liveSnapshot.currentItem.durationSeconds,
        videoPolicyLabel: liveSnapshot.currentItem.videoPolicyLabel,
      })
    : null;
  const nextSnapshotSourceMeta = liveSnapshot?.nextItem
    ? getWallSourceMeta(liveSnapshot.nextItem.source)
    : null;
  const nextSnapshotMediaMeta = liveSnapshot?.nextItem
    ? getWallMediaSemanticMeta({
        isVideo: liveSnapshot.nextItem.isVideo,
        durationSeconds: liveSnapshot.nextItem.durationSeconds,
        videoPolicyLabel: liveSnapshot.nextItem.videoPolicyLabel,
      })
    : null;
  const activeLiveSenderName = activeLiveItem?.senderName || 'Convidado';
  const activeLiveCaption = activeLiveItem && 'caption' in activeLiveItem ? activeLiveItem.caption ?? null : null;
  const activeLiveRelativeTime = selectedMedia
    ? formatWallRelativeTime(selectedMedia.createdAt, 'Agora')
    : formatWallRelativeTime(liveSnapshot?.currentItem?.createdAt, 'Agora');
  const activeLiveStatusLabel = selectedMedia
    ? formatWallRecentStatusLabel(selectedMedia.status)
    : (liveSnapshot?.wallStatusLabel ?? 'Ao vivo');
  const previewMedia = selectedMedia
    ? {
        previewUrl: selectedMedia.previewUrl,
        senderName: selectedMedia.senderName,
        sourceType: selectedMedia.source,
        isFeatured: selectedMedia.isFeatured ?? false,
        caption: null,
      }
    : liveSnapshot?.currentItem
      ? {
          previewUrl: liveSnapshot.currentItem.previewUrl,
          senderName: liveSnapshot.currentItem.senderName,
          sourceType: liveSnapshot.currentItem.source,
          isFeatured: liveSnapshot.currentItem.isFeatured ?? false,
          caption: liveSnapshot.currentItem.caption ?? null,
        }
    : simulationPreview[0]
      ? {
          previewUrl: simulationPreview[0].preview_url ?? null,
          senderName: simulationPreview[0].sender_name,
          sourceType: simulationPreview[0].source_type ?? 'whatsapp',
          isFeatured: simulationPreview[0].is_featured,
          caption: simulationPreview[0].caption ?? null,
        }
      : null;
  const previewUpcomingItems = simulationPreview.slice(1, 4).map((item) => ({
    itemId: item.item_id,
    previewUrl: item.preview_url ?? null,
    senderName: item.sender_name,
  }));

  return (
    <WallManagerSection
      title="Transmissao"
      description="Acompanhe o que esta em foco no palco e navegue entre a tela atual e a fila prevista."
    >
      <Tabs
        value={activeTab}
        onValueChange={(value) => onTabChange(value as 'live' | 'upcoming')}
        activationMode="automatic"
      >
        <TabsList aria-label="Visoes do palco do telao" className="w-full justify-start">
          <TabsTrigger value="live">Ao vivo</TabsTrigger>
          <TabsTrigger value="upcoming">Proximas fotos</TabsTrigger>
        </TabsList>

        <TabsContent value="live" forceMount className="mt-4">
          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div className="overflow-hidden rounded-3xl border border-border/60 bg-muted/30">
              <div className="aspect-[4/3] sm:aspect-video">
                <AnimatePresence mode="wait" initial={false}>
                  {activeLiveItem ? (
                    <motion.div
                      key={activeLiveItem.id}
                      initial={{ opacity: 0, scale: 0.985 }}
                      animate={{ opacity: 1, scale: 1 }}
                      exit={{ opacity: 0, scale: 0.985 }}
                      transition={{ duration: 0.2, ease: 'easeOut' }}
                      className="relative h-full overflow-hidden bg-neutral-950"
                    >
                      {activeLiveItem.previewUrl ? (
                        <img
                          src={activeLiveItem.previewUrl}
                          alt={`Midia em foco no telao enviada por ${activeLiveSenderName}`}
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <div className="flex h-full items-center justify-center px-6 text-center text-sm text-white/70">
                          Essa midia chegou sem miniatura pronta para o palco.
                        </div>
                      )}

                      <div className="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/40 to-transparent" />
                      <div className="absolute left-5 top-5">
                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-400/25 bg-emerald-500/15 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.16em] text-emerald-100">
                          Ao vivo
                        </span>
                      </div>
                      <div className="absolute inset-x-0 bottom-0 space-y-3 p-5 text-white">
                        <div className="space-y-1">
                          <p className="text-xs uppercase tracking-[0.18em] text-white/60">
                            {selectedMedia ? WALL_INSIGHTS_COPY.selectedMedia : 'Agora no telao'}
                          </p>
                          <h3 className="text-xl font-semibold">
                            {activeLiveSenderName}
                          </h3>
                          {activeLiveCaption ? (
                            <p className="max-w-2xl text-sm text-white/80">
                              {activeLiveCaption}
                            </p>
                          ) : null}
                        </div>

                        <div className="flex flex-wrap gap-2 text-xs">
                          {selectedMediaSourceMeta ? (
                            <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 font-medium ${selectedMediaSourceMeta.chipClassName}`}>
                              <selectedMediaSourceMeta.Icon className="h-3.5 w-3.5" />
                              {selectedMediaSourceMeta.label}
                            </span>
                          ) : null}
                          {activeLiveMediaMeta?.isVideo ? (
                            <span className={`inline-flex rounded-full border px-2 py-1 font-medium ${activeLiveMediaMeta.chipClassName}`}>
                              {activeLiveMediaMeta.badgeLabel}
                            </span>
                          ) : null}
                          <span className="inline-flex rounded-full border border-white/15 bg-white/10 px-2 py-1 font-medium text-white/80">
                            {activeLiveStatusLabel}
                          </span>
                          <span className="inline-flex rounded-full border border-white/15 bg-white/10 px-2 py-1 font-medium text-white/80">
                            {activeLiveRelativeTime}
                          </span>
                          {!selectedMedia && liveSnapshot?.currentItem ? (
                            <WallAdvanceClock
                              advancedAt={liveSnapshot.advancedAt}
                              intervalMs={wallSettings.interval_ms}
                              isLive={isLive}
                              isPaused={isPaused}
                            />
                          ) : null}
                        </div>
                      </div>
                    </motion.div>
                  ) : isLive || isPaused ? (
                    <motion.div
                      key={status}
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      exit={{ opacity: 0 }}
                      transition={{ duration: 0.2, ease: 'easeOut' }}
                      className="flex h-full items-center justify-center bg-gradient-to-br from-neutral-900 via-neutral-950 to-neutral-900 px-6 text-center"
                    >
                      <div className="space-y-3">
                        <Monitor className="mx-auto h-14 w-14 text-orange-400/80" />
                        <p className="text-base font-medium text-white/80 sm:text-lg">
                          {isLive
                            ? 'Telao ativo aguardando a primeira confirmacao do player.'
                            : 'Telao pausado aguardando novo comando.'}
                        </p>
                        <p className="mx-auto max-w-md text-sm leading-relaxed text-white/60">
                          {isLive
                            ? 'Assim que o player confirmar a midia atual, o palco mostra o item ao vivo e libera o relogio operacional.'
                            : 'A ultima exibicao fica congelada ate um novo comando de retomada ou troca de status.'}
                        </p>
                      </div>
                    </motion.div>
                  ) : (
                    <motion.div
                      key="wall-idle"
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      exit={{ opacity: 0 }}
                      transition={{ duration: 0.2, ease: 'easeOut' }}
                      className="flex h-full items-center justify-center px-6 text-center"
                    >
                      <div className="space-y-3">
                        <Monitor className="mx-auto h-12 w-12 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                          O telao ainda nao esta em exibicao. Inicie o telao para comecar a mostrar as midias.
                        </p>
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            </div>

            <div className="space-y-3">
              <WallDraftPreviewCard
                settings={wallSettings}
                previewMedia={previewMedia}
                upcomingItems={previewUpcomingItems}
              />

              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                {selectedMedia ? (
                  <InfoCard
                    label={WALL_INSIGHTS_COPY.selectedMedia}
                    value={selectedMedia.senderName || 'Convidado'}
                    detail={[
                      selectedMediaSourceMeta?.label,
                      activeLiveMediaMeta?.isVideo ? activeLiveMediaMeta.detailLabel : null,
                      activeLiveMediaMeta?.isVideo ? activeLiveMediaMeta.operationalLabel : null,
                      formatWallRecentStatusLabel(selectedMedia.status),
                      formatWallRelativeTime(selectedMedia.createdAt, 'Agora'),
                    ].filter(Boolean).join(' - ')}
                    action={(
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 px-2 text-xs"
                        aria-label="Ver detalhes da midia selecionada"
                        onClick={onOpenSelectedMediaDetails}
                      >
                        Ver detalhes
                      </Button>
                    )}
                  />
                ) : null}
                {liveSnapshot?.currentItem ? (
                  <InfoCard
                    label="Agora no telao"
                    value={liveSnapshot.currentItem.senderName || 'Convidado'}
                    detail={[
                      currentSnapshotSourceMeta?.label,
                      currentSnapshotMediaMeta?.isVideo ? currentSnapshotMediaMeta.detailLabel : null,
                      currentSnapshotMediaMeta?.isVideo ? currentSnapshotMediaMeta.operationalLabel : null,
                      liveSnapshot.currentItem.caption,
                      formatWallRelativeTime(liveSnapshot.currentItem.createdAt, 'Agora'),
                    ].filter(Boolean).join(' - ')}
                    tone="live"
                  />
                ) : isLive ? (
                  <InfoCard
                    label="Agora no telao"
                    value="Sincronizando a midia atual do telao."
                    detail="O player ainda nao confirmou qual item esta visivel neste instante."
                    tone="live"
                  />
                ) : null}
                {liveSnapshot?.nextItem ? (
                  <InfoCard
                    label="Proxima no telao"
                    value={liveSnapshot.nextItem.senderName || 'Convidado'}
                    detail={[
                      nextSnapshotSourceMeta?.label,
                      nextSnapshotMediaMeta?.isVideo ? nextSnapshotMediaMeta.detailLabel : null,
                      nextSnapshotMediaMeta?.isVideo ? nextSnapshotMediaMeta.operationalLabel : null,
                      liveSnapshot.nextItem.caption,
                      liveSnapshot.nextItem.layoutHint ? `Layout ${formatLayoutHintLabel(liveSnapshot.nextItem.layoutHint)}` : null,
                    ].filter(Boolean).join(' - ')}
                    tone="next"
                  />
                ) : isLive ? (
                  <InfoCard
                    label="Proxima no telao"
                    value="A proxima exibicao aparece assim que a fila confirmar a ordem."
                    detail="Enquanto isso, a aba Proximas fotos continua mostrando a previsao mais provavel."
                    tone="next"
                  />
                ) : null}
                <InfoCard label="Evento" value={eventTitle} detail={eventSchedule} />
                <InfoCard
                  label="Codigo do telao"
                  value={wallCode}
                  detail="Use este codigo para identificar o telao em suporte ou operacao."
                  action={(
                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={onCopyWallCode}>
                      {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                    </Button>
                  )}
                  helpKey="wallCode"
                />
                <InfoCard
                  label="Alteracoes pendentes"
                  value={hasUnsavedChanges ? 'Sim' : 'Nao'}
                  detail={hasUnsavedChanges ? 'Existe ajuste local esperando salvar.' : 'Tudo salvo e sincronizado.'}
                />
              </div>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="upcoming" forceMount className="mt-4">
          <WallUpcomingTimeline
            selectionSummary={selectionSummary}
            simulationSummary={simulationSummary}
            simulationPreview={simulationPreview}
            simulationExplanation={simulationExplanation}
            isLoading={isSimulationLoading}
            isError={isSimulationError}
            isRefreshing={isSimulationRefreshing}
            isDraftPending={isSimulationDraftPending}
          />
        </TabsContent>
      </Tabs>
    </WallManagerSection>
  );
}

function formatLayoutHintLabel(value: NonNullable<ApiWallLiveSnapshotResponse['currentItem']>['layoutHint']) {
  switch (value) {
    case 'cinematic':
      return 'Cinematografico';
    case 'fullscreen':
      return 'Tela cheia';
    case 'split':
      return 'Tela dividida';
    case 'polaroid':
      return 'Polaroid';
    case 'kenburns':
      return 'Ken Burns';
    case 'spotlight':
      return 'Holofote';
    case 'gallery':
      return 'Galeria de arte';
    case 'carousel':
      return 'Carrossel';
    case 'mosaic':
      return 'Mosaico';
    case 'grid':
      return 'Grade';
    default:
      return value ?? 'Auto';
  }
}

function InfoCard({
  label,
  value,
  detail,
  action,
  helpKey,
  tone = 'default',
}: {
  label: string;
  value: string;
  detail: string;
  action?: ReactNode;
  helpKey?: Parameters<typeof HelpTooltip>[0]['helpKey'];
  tone?: 'default' | 'live' | 'next';
}) {
  const toneClasses =
    tone === 'live'
      ? 'border-emerald-500/30 bg-emerald-500/[0.07] shadow-[inset_0_1px_0_rgba(16,185,129,0.08)]'
      : tone === 'next'
        ? 'border-sky-500/30 bg-sky-500/[0.06] shadow-[inset_0_1px_0_rgba(14,165,233,0.08)]'
        : 'border-border/60 bg-background/70';

  const labelToneClasses =
    tone === 'live'
      ? 'text-emerald-700'
      : tone === 'next'
        ? 'text-sky-700'
        : 'text-muted-foreground';

  return (
    <div className={cn('rounded-2xl border p-4', toneClasses)}>
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-1.5">
          <p className={cn('text-xs uppercase tracking-[0.16em]', labelToneClasses)}>{label}</p>
          {helpKey ? <HelpTooltip helpKey={helpKey} /> : null}
        </div>
        {action}
      </div>
      <p className="mt-2 text-base font-semibold">{value}</p>
      <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{detail}</p>
    </div>
  );
}
