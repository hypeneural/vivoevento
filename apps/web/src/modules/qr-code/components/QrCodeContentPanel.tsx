import { ExternalLink, QrCode, RotateCcw, Wand2 } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { ApiEventPublicLink } from '@/lib/api-types';
import { QrCodeMiniPreview } from '@/modules/events/qr/QrCodeMiniPreview';
import { QrHelpTooltip } from '@/modules/qr-code/components/QrCodeHelp';
import { buildQrCodeStylingOptions } from '@/modules/qr-code/support/qrOptionsBuilder';
import { buildQrCascadeDefaults } from '@/modules/qr-code/support/qrPresetCascade';
import { QR_USAGE_PRESET_METADATA } from '@/modules/qr-code/support/qrPresets';
import type { QrCascadeExplanation } from '@/modules/qr-code/support/qrCascadeExplanation';
import { QR_USAGE_PRESETS } from '@/modules/qr-code/support/qrTypes';
import type { EventPublicLinkQrConfig } from '@/modules/qr-code/support/qrTypes';
import type { ApiEventEffectiveBranding } from '@/lib/api-types';

interface QrCodeContentPanelProps {
  link: ApiEventPublicLink;
  config: EventPublicLinkQrConfig;
  explanation: QrCascadeExplanation;
  onUsagePresetChange: (preset: EventPublicLinkQrConfig['usage_preset']) => void;
  availableStyles: Array<{ linkKey: ApiEventPublicLink['key']; link: ApiEventPublicLink }>;
  onCopyStyle: (linkKey: ApiEventPublicLink['key']) => void;
  effectiveBranding?: ApiEventEffectiveBranding | null;
  onResetSection: () => void;
}

function renderOriginBadge(origin: QrCascadeExplanation['usagePreset']) {
  switch (origin) {
    case 'event':
      return <Badge variant="outline">Veio do evento</Badge>;
    case 'custom':
      return <Badge variant="secondary">Personalizado aqui</Badge>;
    default:
      return <Badge variant="outline">Veio do modelo</Badge>;
  }
}

export function QrCodeContentPanel({
  link,
  config,
  explanation,
  onUsagePresetChange,
  availableStyles,
  onCopyStyle,
  effectiveBranding,
  onResetSection,
}: QrCodeContentPanelProps) {
  const [copyFrom, setCopyFrom] = useState<ApiEventPublicLink['key'] | ''>('');
  const availableOptions = useMemo(
    () => availableStyles.filter((item) => item.linkKey !== link.key),
    [availableStyles, link.key],
  );

  return (
    <Card>
      <CardContent className="space-y-3 p-4">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Badge variant="secondary">Modelo de uso</Badge>
            <QrHelpTooltip
              title="Modelo de uso"
              description="Escolha primeiro onde esse QR vai aparecer. O editor adapta contraste, robustez e formato de arquivo para esse contexto."
            />
            {renderOriginBadge(explanation.usagePreset)}
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            {QR_USAGE_PRESETS.map((preset) => {
              const meta = QR_USAGE_PRESET_METADATA[preset];
              const active = config.usage_preset === preset;
              const presetConfig = buildQrCascadeDefaults({
                linkKey: link.key,
                usagePreset: preset,
                skinPreset: config.skin_preset,
                branding: effectiveBranding ?? undefined,
              });
              const previewOptions = buildQrCodeStylingOptions({
                config: {
                  ...presetConfig,
                  render: {
                    ...presetConfig.render,
                    preview_size: 96,
                  },
                },
                data: link.qr_value ?? link.url ?? '',
              });

              return (
                <button
                  key={preset}
                  type="button"
                  onClick={() => onUsagePresetChange(preset)}
                  className={`rounded-2xl border px-3 py-3 text-left text-xs transition ${
                    active
                      ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
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
            <RotateCcw className="mr-2 h-4 w-4" />
            Restaurar esta secao
          </Button>
        </div>

        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Wand2 className="h-4 w-4 text-primary" />
            <p className="text-sm font-medium">Copiar visual de outro link</p>
            <QrHelpTooltip
              title="Copiar visual de outro link"
              description="Reaproveita apenas as cores, o modelo e a logo. O endereco que o QR abre continua sendo o deste link."
            />
          </div>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Select
              value={copyFrom}
              onValueChange={(value) => setCopyFrom(value as ApiEventPublicLink['key'])}
            >
              <SelectTrigger className="sm:w-[240px]">
                <SelectValue placeholder="Escolha de qual link copiar" />
              </SelectTrigger>
              <SelectContent>
                {availableOptions.length === 0 ? (
                  <SelectItem value="none" disabled>
                    Nenhum link disponivel
                  </SelectItem>
                ) : (
                  availableOptions.map((item) => (
                    <SelectItem key={item.linkKey} value={item.linkKey}>
                      {item.link.label}
                    </SelectItem>
                  ))
                )}
              </SelectContent>
            </Select>
            <Button
              type="button"
              variant="outline"
              disabled={!copyFrom}
              onClick={() => {
                if (!copyFrom) {
                  return;
                }
                onCopyStyle(copyFrom);
              }}
            >
              Usar este visual
            </Button>
          </div>
          <p className="text-xs text-muted-foreground">
            Copia apenas o visual salvo do link escolhido. O endereco aberto pelo QR continua sendo o deste link.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <QrCode className="h-4 w-4 text-primary" />
          <p className="text-sm font-medium">Endereco que o QR abre</p>
          <QrHelpTooltip
            title="Endereco que o QR abre"
            description="O visual fica salvo por link, mas o destino lido pelo QR sempre acompanha o link publico atual do evento."
          />
        </div>
        <p className="break-all rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-muted-foreground">
          {link.qr_value ?? link.url ?? 'Link indisponivel'}
        </p>
        <p className="text-xs text-muted-foreground">
          Por enquanto, voce edita so a aparencia. O destino do QR continua sendo o link publico real deste item.
        </p>
        {link.url ? (
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => window.open(link.url!, '_blank', 'noopener,noreferrer')}
            >
              <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
              Abrir link
            </Button>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
