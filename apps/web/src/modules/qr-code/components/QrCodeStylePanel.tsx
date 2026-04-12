import { Palette } from 'lucide-react';
import { useFormContext, useWatch } from 'react-hook-form';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { QrCodeMiniPreview } from '@/modules/events/qr/QrCodeMiniPreview';
import { QrFieldLabel, QrHelpTooltip } from '@/modules/qr-code/components/QrCodeHelp';
import { buildQrCodeStylingOptions } from '@/modules/qr-code/support/qrOptionsBuilder';
import { buildQrCascadeDefaults } from '@/modules/qr-code/support/qrPresetCascade';
import { QR_SKIN_PRESET_METADATA, QR_USAGE_PRESET_METADATA } from '@/modules/qr-code/support/qrPresets';
import type { QrCascadeExplanation, QrFieldOrigin } from '@/modules/qr-code/support/qrCascadeExplanation';
import { QR_SKIN_PRESETS } from '@/modules/qr-code/support/qrTypes';
import type { EventPublicLinkQrConfig, QrSkinPreset } from '@/modules/qr-code/support/qrTypes';

interface QrCodeStylePanelProps {
  config: EventPublicLinkQrConfig;
  explanation: QrCascadeExplanation;
  onSkinPresetChange: (preset: QrSkinPreset) => void;
  onResetSection: () => void;
}

function renderOriginBadge(origin: QrFieldOrigin) {
  switch (origin) {
    case 'event':
      return <Badge variant="outline">Veio do evento</Badge>;
    case 'custom':
      return <Badge variant="secondary">Personalizado aqui</Badge>;
    default:
      return <Badge variant="outline">Veio do modelo</Badge>;
  }
}

export function QrCodeStylePanel({ config, explanation, onSkinPresetChange, onResetSection }: QrCodeStylePanelProps) {
  const form = useFormContext<EventPublicLinkQrConfig>();
  const usagePreset = useWatch({
    control: form.control,
    name: 'usage_preset',
  });
  const skinPreset = useWatch({
    control: form.control,
    name: 'skin_preset',
  });

  return (
    <Card>
      <CardContent className="space-y-5 p-4">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Palette className="h-4 w-4 text-primary" />
            <p className="text-sm font-medium">Visual do QR</p>
            <QrHelpTooltip
              title="Visual do QR"
              description="Escolha um modelo pronto e depois ajuste cores pontuais, se quiser. O objetivo e facilitar para quem nao quer montar tudo do zero."
            />
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{QR_USAGE_PRESET_METADATA[usagePreset ?? config.usage_preset].label}</Badge>
            <Badge variant="secondary">{QR_SKIN_PRESET_METADATA[skinPreset ?? config.skin_preset].label}</Badge>
          </div>
          <p className="text-xs text-muted-foreground">
            Escolha um modelo visual pronto e ajuste as cores principais sem sair do fluxo guiado.
          </p>
        </div>

        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Badge variant="secondary">Modelo visual</Badge>
            <QrHelpTooltip
              title="Modelo visual"
              description="Troca o acabamento do QR sem mudar o endereco que ele abre. E o jeito mais rapido de experimentar estilos diferentes."
            />
            {renderOriginBadge(explanation.skinPreset)}
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            {QR_SKIN_PRESETS.map((preset) => {
              const meta = QR_SKIN_PRESET_METADATA[preset];
              const active = config.skin_preset === preset;
              const presetConfig = buildQrCascadeDefaults({
                linkKey: 'gallery',
                usagePreset: config.usage_preset,
                skinPreset: preset,
              });
              const previewOptions = buildQrCodeStylingOptions({
                config: {
                  ...presetConfig,
                  render: {
                    ...presetConfig.render,
                    preview_size: 96,
                  },
                },
                data: 'https://eventovivo.com.br',
              });

              return (
                <button
                  key={preset}
                  type="button"
                  onClick={() => onSkinPresetChange(preset)}
                  className={`rounded-2xl border px-3 py-3 text-left text-xs transition ${
                    active
                      ? 'border-slate-900 bg-slate-900 text-white'
                      : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
                  }`}
                >
                  <div className="flex items-center gap-2">
                    <QrCodeMiniPreview options={previewOptions} size={56} className="h-14 w-14 border-solid" />
                    <span className={`h-6 w-6 rounded-lg ${meta.swatchClass}`} />
                    <p className="text-sm font-semibold">{meta.label}</p>
                  </div>
                  <p className="mt-1 text-[11px] text-muted-foreground">{meta.description}</p>
                </button>
              );
            })}
          </div>
          <Button type="button" variant="ghost" size="sm" onClick={onResetSection} className="w-full justify-start">
            Restaurar esta secao
          </Button>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="style.dots.color"
            render={({ field }) => (
              <FormItem>
                <div className="flex items-center justify-between gap-2">
                  <QrFieldLabel
                    label="Cor principal"
                    description="E a cor da maior parte dos pontos do QR. Prefira tons escuros sobre fundo claro para leitura mais segura."
                  />
                  {renderOriginBadge(explanation.primaryColor)}
                </div>
                <FormControl>
                  <Input type="color" value={field.value ?? '#0f172a'} onChange={field.onChange} className="h-11 p-1" />
                </FormControl>
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="style.background.color"
            render={({ field }) => (
              <FormItem>
                <div className="flex items-center justify-between gap-2">
                  <QrFieldLabel
                    label="Cor de fundo"
                    description="E a cor atras do QR. Fundo claro costuma funcionar melhor, principalmente em impressos e telas com reflexo."
                  />
                  {renderOriginBadge(explanation.backgroundColor)}
                </div>
                <FormControl>
                  <Input type="color" value={field.value ?? '#ffffff'} onChange={field.onChange} className="h-11 p-1" />
                </FormControl>
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="style.corners_square.color"
            render={({ field }) => (
              <FormItem>
                <QrFieldLabel
                  label="Olhos externos"
                  description="Sao os tres quadros maiores dos cantos. Eles ajudam a camera a localizar o QR."
                />
                <FormControl>
                  <Input type="color" value={field.value ?? '#0f172a'} onChange={field.onChange} className="h-11 p-1" />
                </FormControl>
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="style.corners_dot.color"
            render={({ field }) => (
              <FormItem>
                <QrFieldLabel
                  label="Olhos internos"
                  description="E o miolo dentro dos tres cantos. Pode ter outra cor para reforcar a identidade visual."
                />
                <FormControl>
                  <Input type="color" value={field.value ?? '#0f172a'} onChange={field.onChange} className="h-11 p-1" />
                </FormControl>
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="style.background.transparent"
          render={({ field }) => (
            <FormItem className="flex flex-row items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
              <div className="space-y-1">
                <QrFieldLabel
                  label="Fundo transparente"
                  description="Remove o fundo branco. Use so quando voce sabe que o QR vai ficar sobre uma superficie clara e limpa."
                />
                <FormDescription className="text-xs">
                  Deixa o QR mais flexivel para composicoes, mas exige cuidado maior com o fundo.
                </FormDescription>
              </div>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
            </FormItem>
          )}
        />
      </CardContent>
    </Card>
  );
}
