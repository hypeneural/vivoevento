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
  FormLabel,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { QrCodeMiniPreview } from '@/modules/events/qr/QrCodeMiniPreview';
import { buildQrCodeStylingOptions } from '@/modules/qr-code/support/qrOptionsBuilder';
import { buildQrCascadeDefaults } from '@/modules/qr-code/support/qrPresetCascade';
import { QR_SKIN_PRESETS } from '@/modules/qr-code/support/qrTypes';
import { QR_SKIN_PRESET_METADATA } from '@/modules/qr-code/support/qrPresets';
import type { EventPublicLinkQrConfig, QrSkinPreset } from '@/modules/qr-code/support/qrTypes';
import type { QrCascadeExplanation, QrFieldOrigin } from '@/modules/qr-code/support/qrCascadeExplanation';

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
      return <Badge variant="outline">Veio do preset</Badge>;
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
            <p className="text-sm font-medium">Cores base do QR</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{usagePreset}</Badge>
            <Badge variant="secondary">{skinPreset}</Badge>
          </div>
          <p className="text-xs text-muted-foreground">
            Esta fase libera o ajuste fino das cores principais sem desmontar o fluxo guiado por preset.
          </p>
        </div>

        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Badge variant="secondary">Preset visual</Badge>
            {renderOriginBadge(explanation.skinPreset)}
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            {QR_SKIN_PRESETS.map((preset) => {
              const meta = QR_SKIN_PRESET_METADATA[preset];
              const active = config.skin_preset === preset;
              const swatch = preset === 'classico'
                ? 'bg-slate-50 border border-slate-200'
                : preset === 'premium'
                  ? 'bg-amber-500'
                  : preset === 'minimalista'
                    ? 'bg-white border border-slate-200'
                    : 'bg-slate-900';
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
                    <span className={`h-6 w-6 rounded-lg ${swatch}`} />
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
                  <FormLabel>Cor principal</FormLabel>
                  {renderOriginBadge(explanation.primaryColor)}
                </div>
                <FormControl>
                  <Input type="color" value={field.value} onChange={field.onChange} className="h-11 p-1" />
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
                  <FormLabel>Cor de fundo</FormLabel>
                  {renderOriginBadge(explanation.backgroundColor)}
                </div>
                <FormControl>
                  <Input type="color" value={field.value} onChange={field.onChange} className="h-11 p-1" />
                </FormControl>
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="style.corners_square.color"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Olhos externos</FormLabel>
                <FormControl>
                  <Input type="color" value={field.value} onChange={field.onChange} className="h-11 p-1" />
                </FormControl>
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="style.corners_dot.color"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Olhos internos</FormLabel>
                <FormControl>
                  <Input type="color" value={field.value} onChange={field.onChange} className="h-11 p-1" />
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
                <FormLabel>Fundo transparente</FormLabel>
                <FormDescription className="text-xs">
                  Mantem o QR mais flexivel para composicoes, mas ainda com guardrails do schema.
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
