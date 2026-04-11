import { Image as ImageIcon, Loader2, RotateCcw, Trash2, UploadCloud } from 'lucide-react';
import { useRef } from 'react';
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
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import type { ApiEventEffectiveBranding } from '@/lib/api-types';
import type { QrCascadeExplanation, QrFieldOrigin } from '@/modules/qr-code/support/qrCascadeExplanation';
import type { EventPublicLinkQrConfig, QrLogoMode } from '@/modules/qr-code/support/qrTypes';

interface QrCodeLogoPanelProps {
  effectiveBranding?: ApiEventEffectiveBranding | null;
  explanation: QrCascadeExplanation;
  onResetSection: () => void;
  onUploadCustomLogo?: (
    file: File,
    previousPath?: string | null,
  ) => Promise<{ path: string; url: string }> | PromiseLike<{ path: string; url: string }> | { path: string; url: string };
  isUploadingLogo?: boolean;
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

function resolveLogoModeLabel(mode: QrLogoMode) {
  switch (mode) {
    case 'event_logo':
      return 'Usando a logo do evento';
    case 'organization_logo':
      return 'Usando a logo da organizacao';
    case 'custom':
      return 'Usando um arquivo enviado';
    default:
      return 'Sem logo no centro';
  }
}

export function QrCodeLogoPanel({
  effectiveBranding,
  explanation,
  onResetSection,
  onUploadCustomLogo,
  isUploadingLogo = false,
}: QrCodeLogoPanelProps) {
  const form = useFormContext<EventPublicLinkQrConfig>();
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const logoMode = useWatch({
    control: form.control,
    name: 'logo.mode',
  });
  const logoUrl = useWatch({
    control: form.control,
    name: 'logo.asset_url',
  });
  const logoPath = useWatch({
    control: form.control,
    name: 'logo.asset_path',
  });
  const brandingLogoUrl = effectiveBranding?.logo_url ?? null;
  const effectiveLogoUrl = logoUrl ?? brandingLogoUrl;
  const logoEnabled = logoMode !== 'none' && Boolean(effectiveLogoUrl);

  const handleLogoModeChange = (nextValue: QrLogoMode) => {
    form.setValue('logo.mode', nextValue, { shouldDirty: true });

    if ((nextValue === 'event_logo' || nextValue === 'organization_logo') && brandingLogoUrl) {
      form.setValue('logo.asset_url', brandingLogoUrl, { shouldDirty: true });
      form.setValue('logo.asset_path', null, { shouldDirty: true });
    }
  };

  const handleUpload = async (file: File | null | undefined) => {
    if (!file || !onUploadCustomLogo) {
      return;
    }

    const payload = await onUploadCustomLogo(file, logoPath);

    form.setValue('logo.mode', 'custom', { shouldDirty: true });
    form.setValue('logo.asset_path', payload.path, { shouldDirty: true });
    form.setValue('logo.asset_url', payload.url, { shouldDirty: true });
  };

  const clearLogo = () => {
    form.setValue('logo.mode', 'none', { shouldDirty: true });
    form.setValue('logo.asset_path', null, { shouldDirty: true });
    form.setValue('logo.asset_url', null, { shouldDirty: true });
  };

  return (
    <Card>
      <CardContent className="space-y-5 p-4">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <ImageIcon className="h-4 w-4 text-primary" />
            <p className="text-sm font-medium">Logo central</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">Origem</Badge>
            {renderOriginBadge(explanation.logo)}
          </div>
          <p className="text-xs text-muted-foreground">
            O caminho mais simples e seguro e usar a logo do evento. Se precisar variar so neste QR, envie um arquivo proprio.
          </p>
        </div>

        <div className="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-medium">Logo ativa no momento</p>
              <p className="text-xs text-muted-foreground">{resolveLogoModeLabel(logoMode)}</p>
            </div>
            <Badge variant="outline">{logoEnabled ? 'Leitura protegida' : 'Sem logo'}</Badge>
          </div>

          <div className="mt-4 flex min-h-[180px] items-center justify-center rounded-3xl border border-dashed border-slate-200 bg-white p-6">
            {effectiveLogoUrl ? (
              <img src={effectiveLogoUrl} alt="Preview da logo do QR" className="max-h-28 w-auto max-w-full object-contain" />
            ) : (
              <div className="text-center text-muted-foreground">
                <ImageIcon className="mx-auto h-10 w-10" />
                <p className="mt-3 text-sm">Sem logo no centro do QR</p>
                <p className="mt-1 text-xs">O QR fica mais simples e com leitura mais folgada.</p>
              </div>
            )}
          </div>

          <input
            ref={fileInputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="hidden"
            onChange={(event) => {
              void handleUpload(event.target.files?.[0]).catch(() => undefined);
              event.target.value = '';
            }}
          />

          <div className="mt-4 grid gap-2 sm:grid-cols-3">
            <Button
              type="button"
              variant="outline"
              onClick={() => handleLogoModeChange('event_logo')}
              disabled={!brandingLogoUrl}
              className="justify-start"
            >
              Usar logo do evento
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => fileInputRef.current?.click()}
              disabled={!onUploadCustomLogo || isUploadingLogo}
              className="justify-start"
            >
              {isUploadingLogo ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <UploadCloud className="mr-2 h-4 w-4" />
              )}
              Enviar arquivo
            </Button>
            <Button
              type="button"
              variant="ghost"
              onClick={clearLogo}
              disabled={logoMode === 'none' && !logoUrl}
              className="justify-start text-slate-600"
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Remover logo
            </Button>
          </div>

          <div className="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
            <Badge variant="secondary">PNG, JPG ou WebP</Badge>
            <Badge variant="outline">Ate 10MB</Badge>
            <Badge variant="outline">O sistema reforca a leitura automaticamente</Badge>
          </div>
        </div>

        <FormField
          control={form.control}
          name="logo.image_size"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Tamanho da logo</FormLabel>
              <FormControl>
                <Slider
                  disabled={!logoEnabled}
                  min={0.12}
                  max={0.5}
                  step={0.01}
                  value={[field.value]}
                  onValueChange={([nextValue]) => field.onChange(nextValue)}
                />
              </FormControl>
              <FormDescription className="text-xs">
                Valor atual: {field.value.toFixed(2)}. O editor trava no limite recomendado pela doc oficial da lib.
              </FormDescription>
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="logo.hide_background_dots"
          render={({ field }) => (
            <FormItem className="flex flex-row items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
              <div className="space-y-1">
                <FormLabel>Ocultar dots sob a logo</FormLabel>
                <FormDescription className="text-xs">
                  Mantem a leitura mais segura quando a logo esta ativa.
                </FormDescription>
              </div>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} disabled={!logoEnabled} />
              </FormControl>
            </FormItem>
          )}
        />

        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="ghost" size="sm" onClick={onResetSection} className="justify-start">
            <RotateCcw className="mr-2 h-4 w-4" />
            Restaurar esta secao
          </Button>
          <FormDescription className="self-center text-xs">
            Isso volta apenas a configuracao de logo para o default do evento e do preset atual.
          </FormDescription>
        </div>
      </CardContent>
    </Card>
  );
}
