import { Check, ChevronLeft, ChevronRight, Copy, FastForward, ImageIcon, Pin, ShieldBan, ShieldCheck, Star, X } from 'lucide-react';
import { Link } from 'react-router-dom';

import { AspectRatio } from '@/components/ui/aspect-ratio';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { ApiEventMediaDetail, ApiEventMediaItem } from '@/lib/api-types';
import { ChannelBadge, MediaStatusBadge } from '@/shared/components/StatusBadges';

import type { ModerationMediaAction } from './ModerationMediaCard';
import { MediaActionButton } from './ModerationMediaCard';
import { ModerationMediaSurface, resolveModerationSurfaceAsset } from './ModerationMediaSurface';
import { formatDateTime, getAspectRatio, getOrientationLabel } from '../utils';
import type { ModerationQueueProgress } from '../feed-utils';

interface ModerationReviewPanelProps {
  media: ApiEventMediaItem | ApiEventMediaDetail | null;
  canModerate: boolean;
  isBusy: (action?: ModerationMediaAction) => boolean;
  onAction: (action: ModerationMediaAction) => void;
  onApproveAndNext?: () => void;
  onOpenPreview: () => void;
  canGoPrevious?: boolean;
  canGoNext?: boolean;
  onPrevious?: () => void;
  onNext?: () => void;
  autoAdvanceEnabled?: boolean;
  onAutoAdvanceChange?: (checked: boolean) => void;
  senderBlockBusy?: boolean;
  senderBlockDuration?: string;
  onSenderBlockDurationChange?: (value: string) => void;
  onSenderBlockToggle?: (checked: boolean) => void;
  duplicateClusterItems?: ApiEventMediaItem[];
  duplicateClusterBusy?: boolean;
  onOpenDuplicateItem?: (mediaId: number) => void;
  onRejectDuplicateCluster?: () => void;
  queueProgress?: ModerationQueueProgress;
}

function hasAiEvaluations(media: ApiEventMediaItem | ApiEventMediaDetail): media is ApiEventMediaDetail {
  return 'latest_safety_evaluation' in media || 'latest_vlm_evaluation' in media;
}

function formatScore(value: number) {
  return value.toFixed(value >= 0.1 ? 2 : 3);
}

export function ModerationReviewPanel({
  media,
  canModerate,
  isBusy,
  onAction,
  onApproveAndNext,
  onOpenPreview,
  canGoPrevious = false,
  canGoNext = false,
  onPrevious,
  onNext,
  autoAdvanceEnabled = false,
  onAutoAdvanceChange,
  senderBlockBusy = false,
  senderBlockDuration = '7d',
  onSenderBlockDurationChange,
  onSenderBlockToggle,
  duplicateClusterItems = [],
  duplicateClusterBusy = false,
  onOpenDuplicateItem,
  onRejectDuplicateCluster,
  queueProgress,
}: ModerationReviewPanelProps) {
  if (!media) {
    return (
      <div className="flex min-h-[420px] items-center justify-center rounded-[28px] border border-dashed border-border/60 bg-background/75 p-8 text-center">
        <div className="space-y-2">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary">
            <ImageIcon className="h-6 w-6" />
          </div>
          <p className="text-base font-semibold">Nenhuma midia selecionada</p>
          <p className="max-w-sm text-sm text-muted-foreground">
            Escolha uma foto ou video na grade para revisar, destacar ou fixar na galeria.
          </p>
        </div>
      </div>
    );
  }

  const canApprove = media.status !== 'approved' && media.status !== 'published';
  const canReject = media.status !== 'rejected';
  const surfaceAsset = resolveModerationSurfaceAsset(media, 'preview');
  const aiEvaluations = hasAiEvaluations(media)
    ? {
        safety: media.latest_safety_evaluation ?? null,
        vlm: media.latest_vlm_evaluation ?? null,
      }
    : {
        safety: null,
        vlm: null,
      };
  const senderIdentity = media.sender_phone || media.sender_lid || media.sender_external_id || null;
  const canManageSenderBlock = canModerate && !!media.sender_blacklist_enabled && !!media.sender_recommended_identity_value && !!onSenderBlockToggle;
  const senderBlockSummary = media.sender_blocked
    ? media.sender_block_expires_at
      ? `Bloqueado ate ${formatDateTime(media.sender_block_expires_at)}`
      : 'Bloqueio sem prazo definido'
    : 'Remetente liberado para novas midias';
  const decisionReason = media.decision_override_reason ?? null;
  const queuePositionLabel = queueProgress?.pendingPosition
    ? `Pendente ${queueProgress.pendingPosition} de ${queueProgress.pendingTotal}`
    : queueProgress?.currentPosition
      ? `Item ${queueProgress.currentPosition} de ${queueProgress.total}`
      : 'Sem posicao ativa';
  const queueRemainingLabel = queueProgress?.pendingRemainingAfterCurrent !== null && queueProgress?.pendingRemainingAfterCurrent !== undefined
    ? `${queueProgress.pendingRemainingAfterCurrent} pendentes depois desta`
    : `${queueProgress?.pendingTotal ?? 0} pendentes no recorte`;

  return (
    <div className="overflow-hidden rounded-[28px] border border-border/60 bg-background/90 shadow-sm">
      <button type="button" className="block w-full text-left" onClick={onOpenPreview}>
        <div className="bg-muted">
          <AspectRatio ratio={getAspectRatio(media)}>
            <ModerationMediaSurface
              media={media}
              variant="preview"
              className="h-full w-full"
            />
          </AspectRatio>
        </div>
      </button>

      <div className="space-y-5 p-5">
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-3xl border border-border/60 bg-muted/20 p-3">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Navegacao</p>
            <p className="mt-1 text-sm font-medium text-foreground">Use os botoes ou os atalhos J e K para percorrer a fila.</p>
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="rounded-full"
              disabled={!canGoPrevious}
              onClick={onPrevious}
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="rounded-full"
              disabled={!canGoNext}
              onClick={onNext}
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div className="grid gap-3 rounded-3xl border border-border/60 bg-muted/20 p-4 sm:grid-cols-2">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Posicao na fila</p>
            <p className="mt-1 text-lg font-semibold tabular-nums">{queuePositionLabel}</p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Fila restante</p>
            <p className="mt-1 text-sm font-medium text-muted-foreground">{queueRemainingLabel}</p>
            {queueProgress ? (
              <p className="mt-1 text-xs text-muted-foreground">
                {queueProgress.loadedPendingTotal} pendentes carregadas de {queueProgress.loadedTotal} midias visiveis.
              </p>
            ) : null}
          </div>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-border/60 bg-muted/20 p-4">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Ritmo</p>
            <p className="mt-1 text-sm font-medium text-foreground">Auto-advance move o foco para a proxima pendente depois de aprovar ou reprovar com sucesso.</p>
            <p className="mt-1 text-xs text-muted-foreground">Use Shift + A para aprovar e avancar mesmo com o toggle desligado.</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              variant="outline"
              className="rounded-full"
              disabled={!canModerate || !canApprove || isBusy('approve')}
              onClick={onApproveAndNext}
            >
              <FastForward className="h-4 w-4" />
              Aprovar e proxima
            </Button>
            <div className="flex items-center gap-3 rounded-full border border-border/60 bg-background/80 px-4 py-2">
              <div>
                <p className="text-sm font-medium">Auto-advance</p>
                <p className="text-[11px] text-muted-foreground">{autoAdvanceEnabled ? 'Ligado' : 'Desligado'}</p>
              </div>
              <Switch
                checked={autoAdvanceEnabled}
                disabled={!canModerate || !onAutoAdvanceChange}
                onCheckedChange={onAutoAdvanceChange}
                aria-label="Ativar auto-advance"
              />
            </div>
          </div>
        </div>

        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <MediaStatusBadge status={media.status as never} />
            <ChannelBadge channel={media.channel as never} />
            <Badge variant="outline">{getOrientationLabel(media.orientation)}</Badge>
            {media.is_duplicate_candidate ? (
              <Badge className="border-0 bg-amber-500/90 text-white">
                <Copy className="h-3.5 w-3.5" />
                Possivel duplicata
              </Badge>
            ) : null}
            {media.is_featured ? (
              <Badge className="border-0 bg-amber-500/95 text-white">
                <Star className="h-3.5 w-3.5 fill-current" />
                Favorita
              </Badge>
            ) : null}
            {media.is_pinned ? (
              <Badge className="border-0 bg-sky-600/95 text-white">
                <Pin className="h-3.5 w-3.5" />
                Fixada
              </Badge>
            ) : null}
          </div>

          <div>
            <p className="text-lg font-semibold">{media.event_title || 'Evento sem titulo'}</p>
            <p className="text-sm text-muted-foreground">{media.sender_name || 'Convidado'}</p>
          </div>
        </div>

        {decisionReason ? (
          <div className="space-y-2">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Motivo da decisao</p>
            <p className="rounded-3xl border border-border/60 bg-background/70 p-4 text-sm leading-6 text-muted-foreground">
              {decisionReason}
            </p>
          </div>
        ) : null}

        <div className="grid gap-3 rounded-3xl border border-border/60 bg-muted/20 p-4 sm:grid-cols-2">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Recebida</p>
            <p className="mt-1 text-sm font-medium">{formatDateTime(media.created_at)}</p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Publicada</p>
            <p className="mt-1 text-sm font-medium">{formatDateTime(media.published_at)}</p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Proporcao</p>
            <p className="mt-1 text-sm font-medium">
              {(media.width ?? 0) > 0 && (media.height ?? 0) > 0
                ? `${media.width} x ${media.height}px`
                : getOrientationLabel(media.orientation)}
            </p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Galeria</p>
            <p className="mt-1 text-sm font-medium">
              {media.is_pinned ? 'Fixada no topo' : media.is_featured ? 'Favorita' : 'Fluxo normal'}
            </p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Arquivo</p>
            <p className="mt-1 text-sm font-medium">{media.original_filename || media.mime_type || 'Nao informado'}</p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Tipo</p>
            <p className="mt-1 text-sm font-medium">{media.mime_type || media.media_type}</p>
          </div>
          <div className="sm:col-span-2">
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Deduplicacao</p>
            <p className="mt-1 text-sm font-medium">
              {media.is_duplicate_candidate
                ? `Agrupada em ${media.duplicate_group_key}`
                : 'Nenhuma similaridade relevante detectada ate agora'}
            </p>
          </div>
        </div>

        {media.is_duplicate_candidate ? (
          <div className="space-y-3 rounded-3xl border border-border/60 bg-muted/20 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Cluster de duplicata</p>
                <p className="mt-1 text-sm font-medium text-foreground">
                  {duplicateClusterItems.length > 0
                    ? `${duplicateClusterItems.length} midia${duplicateClusterItems.length > 1 ? 's' : ''} semelhante${duplicateClusterItems.length > 1 ? 's' : ''} pronta${duplicateClusterItems.length > 1 ? 's' : ''} para revisar.`
                    : 'Nenhuma outra midia do grupo foi carregada para revisao agora.'}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Grupo {media.duplicate_group_key || 'sem chave'}.
                </p>
              </div>

              {canModerate && duplicateClusterItems.length > 0 && onRejectDuplicateCluster ? (
                <Button
                  type="button"
                  variant="outline"
                  className="rounded-full"
                  disabled={duplicateClusterBusy}
                  onClick={onRejectDuplicateCluster}
                >
                  <Copy className="h-4 w-4" />
                  Rejeitar demais como duplicada
                </Button>
              ) : null}
            </div>

            {duplicateClusterItems.length > 0 ? (
              <div className="grid gap-3 sm:grid-cols-2">
                {duplicateClusterItems.map((clusterItem) => (
                  <button
                    key={clusterItem.id}
                    type="button"
                    className="overflow-hidden rounded-3xl border border-border/60 bg-background/80 text-left transition hover:border-primary/40 hover:bg-background"
                    onClick={() => onOpenDuplicateItem?.(clusterItem.id)}
                    aria-label={`Abrir duplicata ${clusterItem.id}`}
                  >
                    <AspectRatio ratio={1}>
                      <ModerationMediaSurface media={clusterItem} className="h-full w-full" />
                    </AspectRatio>
                    <div className="space-y-1 p-3">
                      <p className="text-sm font-semibold text-foreground">{clusterItem.sender_name || 'Convidado'}</p>
                      <p className="line-clamp-2 text-xs text-muted-foreground">
                        {clusterItem.caption || clusterItem.event_title || 'Sem legenda adicional'}
                      </p>
                    </div>
                  </button>
                ))}
              </div>
            ) : null}
          </div>
        ) : null}

        <div className="space-y-3 rounded-3xl border border-border/60 bg-muted/20 p-4">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex min-w-0 items-center gap-3">
              <Avatar className="h-12 w-12 border border-border/60">
                <AvatarImage src={media.sender_avatar_url ?? undefined} alt={media.sender_name || 'Remetente'} />
                <AvatarFallback>{(media.sender_name || 'EV').slice(0, 2).toUpperCase()}</AvatarFallback>
              </Avatar>
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold">{media.sender_name || 'Convidado'}</p>
                <p className="truncate text-xs text-muted-foreground">{senderIdentity || 'Sem identidade rastreavel'}</p>
              </div>
            </div>
            <Badge className={media.sender_blocked ? 'border-0 bg-rose-600/95 text-white' : 'border-0 bg-emerald-600/95 text-white'}>
              {media.sender_blocked ? <ShieldBan className="h-3.5 w-3.5" /> : <ShieldCheck className="h-3.5 w-3.5" />}
              {media.sender_blocked ? 'Bloqueado' : 'Liberado'}
            </Badge>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Midias deste remetente</p>
              <p className="mt-1 text-sm font-medium">{media.sender_media_count ?? 0}</p>
            </div>
            <div>
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Identidade usada no bloqueio</p>
              <p className="mt-1 text-sm font-medium">{media.sender_recommended_identity_value || 'Nao disponivel'}</p>
            </div>
            <div className="sm:col-span-2">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Estado do remetente</p>
              <p className="mt-1 text-sm font-medium">{senderBlockSummary}</p>
              {media.sender_block_reason ? (
                <p className="mt-1 text-sm text-muted-foreground">Motivo: {media.sender_block_reason}</p>
              ) : null}
            </div>
          </div>

          <div className="grid gap-3 rounded-2xl border border-border/60 bg-background/70 p-4 sm:grid-cols-[minmax(0,1fr)_220px]">
            <div className="space-y-1">
              <p className="text-sm font-semibold">Bloqueio rapido do remetente</p>
              <p className="text-sm text-muted-foreground">
                Ative o switch para bloquear novas midias deste autor sem sair da fila.
              </p>
            </div>
            <div className="space-y-2">
              <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Prazo do bloqueio</p>
              <Select
                value={senderBlockDuration}
                onValueChange={onSenderBlockDurationChange}
                disabled={!canManageSenderBlock || senderBlockBusy || !!media.sender_blocked}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o prazo" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="24h">24 horas</SelectItem>
                  <SelectItem value="7d">7 dias</SelectItem>
                  <SelectItem value="30d">30 dias</SelectItem>
                  <SelectItem value="forever">Sem prazo</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="sm:col-span-2 flex items-center justify-between gap-3 rounded-2xl border border-border/60 bg-muted/20 p-3">
              <div>
                <p className="text-sm font-medium">{media.sender_blocked ? 'Remetente bloqueado' : 'Permitir remetente'}</p>
                <p className="text-xs text-muted-foreground">
                  {canManageSenderBlock
                    ? 'O bloqueio vale para este evento e pode ser revertido depois.'
                    : media.sender_blacklist_enabled
                      ? 'Esta midia nao traz identidade suficiente para aplicar bloqueio rapido.'
                      : 'O pacote atual deste evento nao habilita bloqueio por remetente.'}
                </p>
              </div>
              <Switch
                checked={!!media.sender_blocked}
                disabled={!canManageSenderBlock || senderBlockBusy}
                onCheckedChange={onSenderBlockToggle}
                aria-label="Bloquear remetente"
              />
            </div>
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Legenda</p>
          <p className="rounded-3xl border border-border/60 bg-background/70 p-4 text-sm leading-6 text-muted-foreground">
            {media.caption || 'Sem legenda enviada.'}
          </p>
        </div>

        <div className="space-y-2">
          <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Leitura IA</p>
          <div className="space-y-3 rounded-3xl border border-border/60 bg-background/70 p-4">
            <div className="flex flex-wrap gap-2">
              {media.safety_status ? <Badge variant="outline">safety: {media.safety_status}</Badge> : null}
              {media.face_index_status ? <Badge variant="outline">face: {media.face_index_status}</Badge> : null}
              {media.vlm_status ? <Badge variant="outline">vlm: {media.vlm_status}</Badge> : null}
              {aiEvaluations.vlm?.mode_applied ? <Badge variant="outline">modo: {aiEvaluations.vlm.mode_applied}</Badge> : null}
            </div>

            {'indexed_faces_count' in media && typeof media.indexed_faces_count === 'number' ? (
              <p className="text-sm text-muted-foreground">
                Faces indexadas: {media.indexed_faces_count}
              </p>
            ) : null}

            {aiEvaluations.safety ? (
              <div className="space-y-2 rounded-2xl border border-border/50 bg-muted/20 p-3">
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Safety</p>
                <p className="text-sm font-medium">
                  {aiEvaluations.safety.decision}
                  {aiEvaluations.safety.review_required ? ' · review' : ''}
                  {aiEvaluations.safety.blocked ? ' · bloqueado' : ''}
                </p>
                {aiEvaluations.safety.reason_codes?.length ? (
                  <p className="text-sm text-muted-foreground">
                    Motivos: {aiEvaluations.safety.reason_codes.join(', ')}
                  </p>
                ) : null}
                {aiEvaluations.safety.category_scores && Object.keys(aiEvaluations.safety.category_scores).length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {Object.entries(aiEvaluations.safety.category_scores).map(([key, value]) => (
                      <Badge key={key} variant="secondary">
                        {key}: {formatScore(value)}
                      </Badge>
                    ))}
                  </div>
                ) : null}
              </div>
            ) : null}

            {aiEvaluations.vlm ? (
              <div className="space-y-2 rounded-2xl border border-border/50 bg-muted/20 p-3">
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">VLM</p>
                <p className="text-sm font-medium">
                  {aiEvaluations.vlm.decision}
                  {aiEvaluations.vlm.review_required ? ' · review' : ''}
                </p>
                {aiEvaluations.vlm.reason ? (
                  <p className="text-sm text-muted-foreground">{aiEvaluations.vlm.reason}</p>
                ) : null}
                {aiEvaluations.vlm.short_caption ? (
                  <p className="text-sm text-muted-foreground">
                    Legenda IA: {aiEvaluations.vlm.short_caption}
                  </p>
                ) : null}
                {aiEvaluations.vlm.tags?.length ? (
                  <div className="flex flex-wrap gap-2">
                    {aiEvaluations.vlm.tags.map((tag) => (
                      <Badge key={tag} variant="secondary">{tag}</Badge>
                    ))}
                  </div>
                ) : null}
              </div>
            ) : null}

            {!aiEvaluations.safety && !aiEvaluations.vlm ? (
              <p className="text-sm text-muted-foreground">
                Os detalhes estruturados de safety e VLM aparecem aqui quando o fast lane concluir.
              </p>
            ) : null}
          </div>
        </div>

        <div className="grid gap-2 sm:grid-cols-2">
          <MediaActionButton
            label="Aprovar"
            icon={Check}
            tone="approve"
            busy={isBusy('approve')}
            disabled={!canModerate || !canApprove}
            onClick={() => onAction('approve')}
          />
          <MediaActionButton
            label="Reprovar"
            icon={X}
            tone="reject"
            busy={isBusy('reject')}
            disabled={!canModerate || !canReject}
            onClick={() => onAction('reject')}
          />
          <MediaActionButton
            label={media.is_featured ? 'Remover favorito' : 'Favoritar'}
            icon={Star}
            tone="favorite"
            active={!!media.is_featured}
            busy={isBusy('favorite')}
            disabled={!canModerate}
            onClick={() => onAction('favorite')}
          />
          <MediaActionButton
            label={media.is_pinned ? 'Desafixar' : 'Fixar na galeria'}
            icon={Pin}
            tone="pin"
            active={!!media.is_pinned}
            busy={isBusy('pin')}
            disabled={!canModerate}
            onClick={() => onAction('pin')}
          />
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button asChild variant="outline" className="rounded-2xl">
            <Link to={`/events/${media.event_id}`}>Abrir evento</Link>
          </Button>
          {surfaceAsset.url ? (
            <Button variant="ghost" className="rounded-2xl" onClick={onOpenPreview}>
              Ver ampliado
            </Button>
          ) : null}
          {media.original_url ? (
            <Button asChild variant="ghost" className="rounded-2xl">
              <a href={media.original_url} target="_blank" rel="noreferrer">Abrir original</a>
            </Button>
          ) : null}
        </div>

        {!canModerate ? (
          <p className="text-sm text-muted-foreground">
            Sua sessao esta em modo visualizacao. As acoes de moderacao exigem permissao `media.moderate`.
          </p>
        ) : (
          <p className="text-xs text-muted-foreground">
            Atalhos: A aprova, R reprova, F favorita, P fixa, X marca, Enter amplia, Esc fecha.
          </p>
        )}
      </div>
    </div>
  );
}
