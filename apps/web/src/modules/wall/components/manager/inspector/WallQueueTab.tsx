import { Settings } from 'lucide-react';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import type {
  ApiWallEventPhaseOption,
  ApiWallSelectionModeOption,
  ApiWallSelectionPolicy,
  ApiWallSettings,
} from '@/lib/api-types';

import { HelpLabel, HelpTooltip } from '../../WallManagerHelp';
import { WallManagerSection } from '../../WallManagerSection';
import {
  WALL_COOLDOWN_OPTIONS,
  WALL_REPLAY_MINUTE_OPTIONS,
  WALL_VOLUME_THRESHOLD_OPTIONS,
  WALL_WINDOW_MINUTE_OPTIONS,
} from '../../../manager-config';

type UpdateDraft = <K extends keyof ApiWallSettings>(key: K, value: ApiWallSettings[K]) => void;
type UpdateSelectionPolicy = <K extends keyof ApiWallSelectionPolicy>(
  key: K,
  value: ApiWallSelectionPolicy[K],
) => void;

interface WallQueueTabProps {
  wallSettings: ApiWallSettings;
  selectionModes: ApiWallSelectionModeOption[];
  eventPhases: ApiWallEventPhaseOption[];
  selectionSummary: string;
  onSelectionModeChange: (value: string) => void;
  onDraftChange: UpdateDraft;
  onSelectionPolicyChange: UpdateSelectionPolicy;
}

export function WallQueueTab({
  wallSettings,
  selectionModes,
  eventPhases,
  selectionSummary,
  onSelectionModeChange,
  onDraftChange,
  onSelectionPolicyChange,
}: WallQueueTabProps) {
  return (
    <>
      <WallManagerSection
        title={(
          <span className="flex items-center gap-2">
            <Settings className="h-4 w-4" />
            Modo do telao
            <HelpTooltip helpKey="selectionMode" />
          </span>
        )}
        description="Escolha primeiro o comportamento base da fila. Os controles abaixo podem refinar esse preset."
      >
        <div className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <HelpLabel helpKey="selectionMode" className="text-sm">Comportamento base</HelpLabel>
              <Select value={wallSettings.selection_mode} onValueChange={onSelectionModeChange}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o modo" />
                </SelectTrigger>
                <SelectContent>
                  {selectionModes.map((mode) => (
                    <SelectItem key={mode.value} value={mode.value}>{mode.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <HelpLabel helpKey="eventPhase" className="text-sm">Fase do evento</HelpLabel>
              <Select
                value={wallSettings.event_phase}
                onValueChange={(value) => onDraftChange('event_phase', value as ApiWallSettings['event_phase'])}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione a fase" />
                </SelectTrigger>
                <SelectContent>
                  {eventPhases.map((phase) => (
                    <SelectItem key={phase.value} value={phase.value}>{phase.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs leading-relaxed text-muted-foreground">
                {eventPhases.find((phase) => phase.value === wallSettings.event_phase)?.description
                  ?? 'A fase aplica contexto operacional por cima do modo escolhido.'}
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
            <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Resumo do comportamento</p>
            <p className="mt-2 text-sm leading-relaxed text-foreground/90">{selectionSummary}</p>
          </div>
        </div>
      </WallManagerSection>

      <WallManagerSection
        title={(
          <span className="flex items-center gap-2">
            Fila e justica
            <HelpTooltip helpKey="fairnessSection" />
          </span>
        )}
        description="Essas regras evitam que uma unica pessoa domine o telao ao enviar muitas fotos."
      >
        <div className="space-y-5">
          <div>
            <HelpLabel helpKey="maxEligibleItems">Backlog elegivel por remetente</HelpLabel>
            <div className="mt-2 flex items-center gap-3">
              <Slider
                value={[wallSettings.selection_policy.max_eligible_items_per_sender]}
                min={1}
                max={12}
                step={1}
                onValueChange={([value]) => onSelectionPolicyChange('max_eligible_items_per_sender', value)}
                className="flex-1"
              />
              <span className="w-12 text-right text-sm font-medium">
                {wallSettings.selection_policy.max_eligible_items_per_sender}
              </span>
            </div>
          </div>

          <div>
            <HelpLabel helpKey="maxReplaysPerItem">Maximo de repeticoes por foto</HelpLabel>
            <div className="mt-2 flex items-center gap-3">
              <Slider
                value={[wallSettings.selection_policy.max_replays_per_item]}
                min={0}
                max={6}
                step={1}
                onValueChange={([value]) => onSelectionPolicyChange('max_replays_per_item', value)}
                className="flex-1"
              />
              <span className="w-12 text-right text-sm font-medium">
                {wallSettings.selection_policy.max_replays_per_item}
              </span>
            </div>
            <p className="mt-2 text-[11px] text-muted-foreground">
              Se todas as fotos atingirem esse limite, a tela libera novas reprises para a exibicao nao ficar vazia.
            </p>
          </div>

          <div className="space-y-4 rounded-2xl border border-border/60 bg-background/60 p-4">
            <div>
              <HelpLabel helpKey="replayAdaptiveSection">Repeticao por volume da fila</HelpLabel>
              <p className="text-[11px] text-muted-foreground">
                Esse ajuste fica salvo no telao para manter o mesmo comportamento em qualquer aparelho conectado.
              </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <HelpLabel helpKey="lowVolumeThreshold" className="text-sm">Fila baixa ate</HelpLabel>
                <Select
                  value={String(wallSettings.selection_policy.low_volume_max_items)}
                  onValueChange={(value) => onSelectionPolicyChange('low_volume_max_items', Number(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione o limite" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_VOLUME_THRESHOLD_OPTIONS.map((value) => (
                      <SelectItem key={`low-${value}`} value={String(value)}>{value} itens</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="mediumVolumeThreshold" className="text-sm">Fila media ate</HelpLabel>
                <Select
                  value={String(wallSettings.selection_policy.medium_volume_max_items)}
                  onValueChange={(value) => onSelectionPolicyChange('medium_volume_max_items', Number(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione o limite" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_VOLUME_THRESHOLD_OPTIONS
                      .filter((option) => option > wallSettings.selection_policy.low_volume_max_items)
                      .map((value) => (
                        <SelectItem key={`medium-${value}`} value={String(value)}>{value} itens</SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
              <div className="space-y-2">
                <HelpLabel helpKey="replayIntervalLow" className="text-sm">Repeticao com fila curta</HelpLabel>
                <Select
                  value={String(wallSettings.selection_policy.replay_interval_low_minutes)}
                  onValueChange={(value) => onSelectionPolicyChange('replay_interval_low_minutes', Number(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Tempo" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_REPLAY_MINUTE_OPTIONS.map((value) => (
                      <SelectItem key={`replay-low-${value}`} value={String(value)}>{value} min</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="replayIntervalMedium" className="text-sm">Repeticao com fila media</HelpLabel>
                <Select
                  value={String(wallSettings.selection_policy.replay_interval_medium_minutes)}
                  onValueChange={(value) => onSelectionPolicyChange('replay_interval_medium_minutes', Number(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Tempo" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_REPLAY_MINUTE_OPTIONS.map((value) => (
                      <SelectItem key={`replay-medium-${value}`} value={String(value)}>{value} min</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="replayIntervalHigh" className="text-sm">Repeticao com fila cheia</HelpLabel>
                <Select
                  value={String(wallSettings.selection_policy.replay_interval_high_minutes)}
                  onValueChange={(value) => onSelectionPolicyChange('replay_interval_high_minutes', Number(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Tempo" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_REPLAY_MINUTE_OPTIONS.map((value) => (
                      <SelectItem key={`replay-high-${value}`} value={String(value)}>{value} min</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <div className="space-y-2">
            <HelpLabel helpKey="senderCooldown" className="text-sm">Tempo minimo entre aparicoes</HelpLabel>
            <Select
              value={String(wallSettings.selection_policy.sender_cooldown_seconds)}
              onValueChange={(value) => onSelectionPolicyChange('sender_cooldown_seconds', Number(value))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Selecione o cooldown" />
              </SelectTrigger>
              <SelectContent>
                {WALL_COOLDOWN_OPTIONS.map((value) => (
                  <SelectItem key={value} value={String(value)}>
                    {value === 0 ? 'Sem cooldown' : `${value}s`}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div>
            <HelpLabel helpKey="senderWindowLimit">Limite por remetente na janela</HelpLabel>
            <div className="mt-2 flex items-center gap-3">
              <Slider
                value={[wallSettings.selection_policy.sender_window_limit]}
                min={1}
                max={6}
                step={1}
                onValueChange={([value]) => onSelectionPolicyChange('sender_window_limit', value)}
                className="flex-1"
              />
              <span className="w-12 text-right text-sm font-medium">
                {wallSettings.selection_policy.sender_window_limit}
              </span>
            </div>
          </div>

          <div className="space-y-2">
            <HelpLabel helpKey="senderWindowMinutes" className="text-sm">Janela de controle</HelpLabel>
            <Select
              value={String(wallSettings.selection_policy.sender_window_minutes)}
              onValueChange={(value) => onSelectionPolicyChange('sender_window_minutes', Number(value))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Selecione a janela" />
              </SelectTrigger>
              <SelectContent>
                {WALL_WINDOW_MINUTE_OPTIONS.map((value) => (
                  <SelectItem key={value} value={String(value)}>{value} min</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex items-center justify-between gap-3">
            <div>
              <HelpLabel helpKey="fairnessSection">Evitar repetir o mesmo remetente</HelpLabel>
              <p className="text-[11px] text-muted-foreground">
                Mantem a alternancia entre convidados quando houver outra midia pronta.
              </p>
            </div>
            <Switch
              checked={wallSettings.selection_policy.avoid_same_sender_if_alternative_exists}
              onCheckedChange={(checked) => onSelectionPolicyChange('avoid_same_sender_if_alternative_exists', checked)}
            />
          </div>

          <div className="flex items-center justify-between gap-3">
            <div>
              <HelpLabel helpKey="antiDuplicateSequence">Anti-sequencia parecida</HelpLabel>
              <p className="text-[11px] text-muted-foreground">
                Evita puxar fotos muito parecidas do mesmo grupo quando houver alternativa.
              </p>
            </div>
            <Switch
              checked={wallSettings.selection_policy.avoid_same_duplicate_cluster_if_alternative_exists}
              onCheckedChange={(checked) => onSelectionPolicyChange('avoid_same_duplicate_cluster_if_alternative_exists', checked)}
            />
          </div>
        </div>
      </WallManagerSection>
    </>
  );
}
