import { Settings } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { ApiWallOptionsResponse, ApiWallSettings, ApiWallThemeConfig } from '@/lib/api-types';

import { HelpLabel, HelpTooltip } from '../../WallManagerHelp';
import { WallManagerSection } from '../../WallManagerSection';
import {
  resolveManagerWallLayoutOption,
  WALL_PUZZLE_ANCHOR_MODE_OPTIONS,
  WALL_PUZZLE_BURST_INTENSITY_OPTIONS,
  WALL_PUZZLE_PRESET_OPTIONS,
  WALL_SLIDER_FIELDS,
  WALL_TOGGLE_FIELDS,
  WALL_VIDEO_AUDIO_POLICY_OPTIONS,
  WALL_VIDEO_MAX_SECONDS_OPTIONS,
  WALL_VIDEO_MULTI_LAYOUT_OPTIONS,
  WALL_VIDEO_PLAYBACK_MODE_OPTIONS,
  WALL_VIDEO_PREFERRED_VARIANT_OPTIONS,
  WALL_VIDEO_RESUME_MODE_OPTIONS,
} from '../../../manager-config';

type UpdateDraft = <K extends keyof ApiWallSettings>(key: K, value: ApiWallSettings[K]) => void;

interface WallAppearanceTabProps {
  wallSettings: ApiWallSettings;
  options: ApiWallOptionsResponse;
  videoPolicySummary: string;
  onDraftChange: UpdateDraft;
}

export function WallAppearanceTab({
  wallSettings,
  options,
  videoPolicySummary,
  onDraftChange,
}: WallAppearanceTabProps) {
  const currentLayoutOption = resolveManagerWallLayoutOption(wallSettings.layout, options.layouts);
  const layoutCapabilities = currentLayoutOption?.capabilities;
  const layoutOptions = currentLayoutOption && !options.layouts.some((item) => item.value === currentLayoutOption.value)
    ? [...options.layouts, currentLayoutOption]
    : options.layouts;
  const isPuzzleLayout = currentLayoutOption?.value === 'puzzle';
  const supportsThemeConfig = Boolean(layoutCapabilities?.supports_theme_config);
  const sideThumbnailsLocked = layoutCapabilities?.supports_side_thumbnails === false;
  const themeConfig = {
    ...currentLayoutOption?.defaults.theme_config,
    ...wallSettings.theme_config,
  } satisfies ApiWallThemeConfig;

  function updateThemeConfig<K extends keyof ApiWallThemeConfig>(
    key: K,
    value: ApiWallThemeConfig[K],
  ) {
    onDraftChange('theme_config', {
      ...themeConfig,
      [key]: value,
    });
  }

  return (
    <>
      <WallManagerSection
        title={(
          <span className="flex items-center gap-2">
            <Settings className="h-4 w-4" />
            Ajustes da exibicao
            <HelpTooltip helpKey="slideshowSection" />
          </span>
        )}
        description="As alteracoes abaixo ficam neste aparelho ate voce tocar em salvar. Isso ajuda quem opera no celular e funciona melhor em internet lenta."
      >
        <div className="space-y-5">
          {WALL_SLIDER_FIELDS.map((field) => {
            const settingValue = wallSettings[field.key] as number;

            return (
              <div key={field.key}>
                <HelpLabel helpKey={field.helpKey}>{field.label}</HelpLabel>
                <div className="mt-2 flex items-center gap-3">
                  <Slider
                    value={[field.toControlValue(settingValue)]}
                    min={field.min}
                    max={field.max}
                    step={field.step}
                    onValueChange={([value]) => onDraftChange(field.key, field.fromControlValue(value))}
                    className="flex-1"
                  />
                  <span className="w-12 text-right text-sm font-medium">{field.formatValue(settingValue)}</span>
                </div>
              </div>
            );
          })}

          {WALL_TOGGLE_FIELDS.map((field) => (
            <div key={field.key} className="flex items-center justify-between gap-3">
              <div>
                <HelpLabel helpKey={field.helpKey}>{field.label}</HelpLabel>
                <p className="text-[11px] text-muted-foreground">{field.description}</p>
              </div>
              <Switch
                checked={wallSettings[field.key] as boolean}
                onCheckedChange={(checked) => onDraftChange(field.key, checked)}
              />
            </div>
          ))}

          {wallSettings.show_neon ? (
            <div className="grid gap-4 rounded-2xl border border-border/60 bg-background/60 p-4 sm:grid-cols-[minmax(0,1fr)_96px]">
              <div className="space-y-2">
                <HelpLabel helpKey="neonText" className="text-sm">Texto da chamada</HelpLabel>
                <Input
                  value={wallSettings.neon_text ?? ''}
                  onChange={(event) => onDraftChange('neon_text', event.target.value)}
                  placeholder="Compartilhe o melhor momento da noite"
                />
              </div>
              <div className="space-y-2">
                <HelpLabel helpKey="neonColor" className="text-sm">Cor da chamada</HelpLabel>
                <Input
                  type="color"
                  value={wallSettings.neon_color ?? '#ffffff'}
                  onChange={(event) => onDraftChange('neon_color', event.target.value)}
                  className="h-11 w-20 p-1"
                />
              </div>
            </div>
          ) : null}
        </div>
      </WallManagerSection>

      <WallManagerSection
        title={(
          <span className="flex items-center gap-2">
            Visual e troca de fotos
            <HelpTooltip helpKey="layoutSection" />
          </span>
        )}
        description="Escolha aqui o estilo de exibicao e a animacao de troca entre as midias."
      >
        <div className="space-y-4">
          <div className="space-y-2">
            <HelpLabel helpKey="layout" className="text-sm">Estilo da exibicao</HelpLabel>
            <Select value={wallSettings.layout} onValueChange={(value) => onDraftChange('layout', value)}>
              <SelectTrigger data-testid="wall-layout-select">
                <SelectValue placeholder="Selecione o estilo" />
              </SelectTrigger>
              <SelectContent>
                {layoutOptions.map((layout) => (
                  <SelectItem key={layout.value} value={layout.value}>{layout.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <HelpLabel helpKey="transition" className="text-sm">Animacao de troca</HelpLabel>
            <Select
              value={wallSettings.transition_effect}
              onValueChange={(value) => onDraftChange('transition_effect', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Selecione a animacao" />
              </SelectTrigger>
              <SelectContent>
                {options.transitions.map((transition) => (
                  <SelectItem key={transition.value} value={transition.value}>{transition.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <HelpLabel helpKey="orientation" className="text-sm">Orientacao aceita</HelpLabel>
            <Select
              value={wallSettings.accepted_orientation ?? 'all'}
              onValueChange={(value) => onDraftChange('accepted_orientation', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Selecione a orientacao" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas as orientacoes</SelectItem>
                <SelectItem value="landscape">Apenas paisagem (horizontal)</SelectItem>
                <SelectItem value="portrait">Apenas retrato (vertical)</SelectItem>
              </SelectContent>
            </Select>
            <p className="text-[11px] text-muted-foreground">
              Filtra quais midias aparecem no telao por orientacao. Midias quadradas passam em qualquer filtro.
            </p>
          </div>

          <div className="flex items-center justify-between gap-3">
            <div>
              <HelpLabel helpKey="sideThumbnails">Miniaturas laterais</HelpLabel>
              <p className="text-[11px] text-muted-foreground">
                {sideThumbnailsLocked
                  ? 'Este layout desliga miniaturas laterais para preservar o palco principal e o budget do board.'
                  : 'Exibe uma faixa com as proximas midias na lateral do telao, mantendo o publico engajado.'}
              </p>
            </div>
            <Switch
              data-testid="wall-side-thumbnails-switch"
              checked={wallSettings.show_side_thumbnails ?? true}
              disabled={sideThumbnailsLocked}
              onCheckedChange={(checked) => onDraftChange('show_side_thumbnails', checked)}
            />
          </div>
        </div>
      </WallManagerSection>

      {supportsThemeConfig ? (
        <WallManagerSection
          title="Configuracao do puzzle"
          description="Esses controles modelam o board do puzzle sem abrir combinacoes que a v1 ainda nao suporta."
        >
          <div className="space-y-4">
            <div className="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
              <p className="text-sm font-medium text-foreground">
                Puzzle exibe imagens. Videos entram em layout individual de fallback.
              </p>
              <p className="mt-1 text-[11px] leading-relaxed text-muted-foreground">
                Esta v1 nao usa video dentro das pecas, nao ativa blur pesado por slot e nao tenta face overlay client-side.
              </p>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <HelpLabel helpKey="layout" className="text-sm">Preset do mosaico</HelpLabel>
                <Select
                  value={themeConfig.preset ?? 'standard'}
                  onValueChange={(value) => updateThemeConfig('preset', value)}
                >
                  <SelectTrigger data-testid="wall-puzzle-preset-select">
                    <SelectValue placeholder="Selecione o preset" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_PUZZLE_PRESET_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-[11px] text-muted-foreground">
                  {WALL_PUZZLE_PRESET_OPTIONS.find((option) => option.value === (themeConfig.preset ?? 'standard'))?.description}
                </p>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="layout" className="text-sm">Peca ancora</HelpLabel>
                <Select
                  value={themeConfig.anchor_mode ?? 'event_brand'}
                  onValueChange={(value) => updateThemeConfig('anchor_mode', value)}
                >
                  <SelectTrigger data-testid="wall-puzzle-anchor-select">
                    <SelectValue placeholder="Selecione a ancora" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_PUZZLE_ANCHOR_MODE_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-[11px] text-muted-foreground">
                  {WALL_PUZZLE_ANCHOR_MODE_OPTIONS.find((option) => option.value === (themeConfig.anchor_mode ?? 'event_brand'))?.description}
                </p>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="transition" className="text-sm">Intensidade do burst</HelpLabel>
                <Select
                  value={themeConfig.burst_intensity ?? 'normal'}
                  onValueChange={(value) => updateThemeConfig('burst_intensity', value)}
                >
                  <SelectTrigger data-testid="wall-puzzle-burst-select">
                    <SelectValue placeholder="Selecione a intensidade" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_PUZZLE_BURST_INTENSITY_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-[11px] text-muted-foreground">
                  {WALL_PUZZLE_BURST_INTENSITY_OPTIONS.find((option) => option.value === (themeConfig.burst_intensity ?? 'normal'))?.description}
                </p>
              </div>

              <div className="flex items-center justify-between gap-3 rounded-2xl border border-border/60 bg-background/60 p-4">
                <div>
                  <HelpLabel helpKey="layout">Hero slot</HelpLabel>
                  <p className="text-[11px] text-muted-foreground">
                    Segura uma peca com maior permanencia visual para featured ou imagem ancora da cena.
                  </p>
                </div>
                <Switch
                  data-testid="wall-puzzle-hero-switch"
                  checked={themeConfig.hero_enabled ?? true}
                  onCheckedChange={(checked) => updateThemeConfig('hero_enabled', checked)}
                />
              </div>
            </div>

            <div className="rounded-2xl border border-border/60 bg-background/60 p-4 text-[11px] text-muted-foreground">
              Sender e caption ficam fora das pecas na v1. Nao existe floating caption por slot neste tema.
            </div>
          </div>
        </WallManagerSection>
      ) : null}

      <WallManagerSection
        title={(
          <span className="flex items-center gap-2">
            Politica de video
            <HelpTooltip helpKey="videoPolicySection" />
          </span>
        )}
        description="Configure aqui como videos entram no wall, quando podem segurar a tela e qual variante o player deve priorizar."
      >
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-3">
            <div>
              <HelpLabel helpKey="videoEnabled">Suportar videos no telao</HelpLabel>
              <p className="text-[11px] text-muted-foreground">
                Quando desligado, o wall publica apenas imagens e o upload publico bloqueia video para este evento.
              </p>
            </div>
            <Switch
              checked={wallSettings.video_enabled ?? true}
              onCheckedChange={(checked) => onDraftChange('video_enabled', checked)}
            />
          </div>

          <div className="grid gap-4 rounded-2xl border border-border/60 bg-background/60 p-4 md:grid-cols-2">
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-sm font-medium text-foreground">Rollout publico</p>
                <p className="text-[11px] text-muted-foreground">
                  Libera 1 video curto por envio no link publico deste evento quando a pipeline estiver pronta.
                </p>
              </div>
              <Switch
                checked={wallSettings.public_upload_video_enabled ?? true}
                onCheckedChange={(checked) => onDraftChange('public_upload_video_enabled', checked)}
              />
            </div>

            <div className="flex items-center justify-between gap-3">
              <div>
                <p className="text-sm font-medium text-foreground">Rollout privado</p>
                <p className="text-[11px] text-muted-foreground">
                  Define se videos vindos de WhatsApp e Telegram entram na trilha oficial com variantes de wall.
                </p>
              </div>
              <Switch
                checked={wallSettings.private_inbound_video_enabled ?? true}
                onCheckedChange={(checked) => onDraftChange('private_inbound_video_enabled', checked)}
              />
            </div>
          </div>

          <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
            <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Resumo efetivo</p>
            <p className="mt-2 text-sm leading-relaxed text-foreground/85">{videoPolicySummary}</p>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <HelpLabel helpKey="videoPlaybackMode" className="text-sm">Modo de reproducao</HelpLabel>
              <Select
                value={wallSettings.video_playback_mode}
                onValueChange={(value) => onDraftChange('video_playback_mode', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o modo" />
                </SelectTrigger>
                <SelectContent>
                  {WALL_VIDEO_PLAYBACK_MODE_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-[11px] text-muted-foreground">
                {WALL_VIDEO_PLAYBACK_MODE_OPTIONS.find((option) => option.value === wallSettings.video_playback_mode)?.description}
              </p>
            </div>

            <div className="space-y-2">
              <HelpLabel helpKey="videoMaxSeconds" className="text-sm">Duracao maxima</HelpLabel>
              <Select
                value={String(wallSettings.video_max_seconds)}
                onValueChange={(value) => onDraftChange('video_max_seconds', Number(value))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o limite" />
                </SelectTrigger>
                <SelectContent>
                  {WALL_VIDEO_MAX_SECONDS_OPTIONS.map((value) => (
                    <SelectItem key={value} value={String(value)}>{value}s</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-[11px] text-muted-foreground">
                Videos acima desse cap so entram por excecao editorial ou serao interrompidos pela policy escolhida.
              </p>
            </div>

            <div className="space-y-2">
              <HelpLabel helpKey="videoResumeMode" className="text-sm">Ao retomar apos pausa</HelpLabel>
              <Select
                value={wallSettings.video_resume_mode}
                onValueChange={(value) => onDraftChange('video_resume_mode', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o comportamento" />
                </SelectTrigger>
                <SelectContent>
                  {WALL_VIDEO_RESUME_MODE_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-[11px] text-muted-foreground">
                {WALL_VIDEO_RESUME_MODE_OPTIONS.find((option) => option.value === wallSettings.video_resume_mode)?.description}
              </p>
            </div>

            <div className="space-y-2">
              <HelpLabel helpKey="videoAudioPolicy" className="text-sm">Audio do video</HelpLabel>
              <Select
                value={wallSettings.video_audio_policy}
                onValueChange={(value) => onDraftChange('video_audio_policy', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione a politica" />
                </SelectTrigger>
                <SelectContent>
                  {WALL_VIDEO_AUDIO_POLICY_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-[11px] text-muted-foreground">
                {WALL_VIDEO_AUDIO_POLICY_OPTIONS.find((option) => option.value === wallSettings.video_audio_policy)?.description}
              </p>
            </div>

            {isPuzzleLayout ? (
              <div
                data-testid="wall-video-multi-layout-locked"
                className="space-y-2 rounded-2xl border border-border/60 bg-background/60 p-4"
              >
                <HelpLabel helpKey="videoMultiLayoutPolicy" className="text-sm">Video em multi-slot</HelpLabel>
                <p className="text-sm font-medium text-foreground">Travado em nao permitir</p>
                <p className="text-[11px] text-muted-foreground">
                  O puzzle nao abre video dentro do board. Quando a midia atual for video, o wall cai para o fallback single-item.
                </p>
              </div>
            ) : (
              <div className="space-y-2">
                <HelpLabel helpKey="videoMultiLayoutPolicy" className="text-sm">Video em multi-slot</HelpLabel>
                <Select
                  value={wallSettings.video_multi_layout_policy}
                  onValueChange={(value) => onDraftChange('video_multi_layout_policy', value)}
                >
                  <SelectTrigger data-testid="wall-video-multi-layout-select">
                    <SelectValue placeholder="Selecione a regra" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_VIDEO_MULTI_LAYOUT_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-[11px] text-muted-foreground">
                  {WALL_VIDEO_MULTI_LAYOUT_OPTIONS.find((option) => option.value === wallSettings.video_multi_layout_policy)?.description}
                </p>
              </div>
            )}

            <div className="space-y-2">
              <HelpLabel helpKey="videoPreferredVariant" className="text-sm">Variante preferida</HelpLabel>
              <Select
                value={wallSettings.video_preferred_variant}
                onValueChange={(value) => onDraftChange('video_preferred_variant', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione a variante" />
                </SelectTrigger>
                <SelectContent>
                  {WALL_VIDEO_PREFERRED_VARIANT_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-[11px] text-muted-foreground">
                {WALL_VIDEO_PREFERRED_VARIANT_OPTIONS.find((option) => option.value === wallSettings.video_preferred_variant)?.description}
              </p>
            </div>
          </div>
        </div>
      </WallManagerSection>

      <WallManagerSection
        title={(
          <span className="flex items-center gap-2">
            Mensagem quando nao ha fotos
            <HelpTooltip helpKey="idleSection" />
          </span>
        )}
        description="Use este texto para orientar o publico quando o telao ainda estiver esperando novas midias."
      >
        <div className="space-y-2">
          <HelpLabel helpKey="instructions" className="text-sm">Texto de espera</HelpLabel>
          <Textarea
            value={wallSettings.instructions_text ?? ''}
            onChange={(event) => onDraftChange('instructions_text', event.target.value)}
            className="min-h-[120px]"
            placeholder="Envie sua foto para aparecer no telao em tempo real."
          />
        </div>
      </WallManagerSection>
    </>
  );
}
