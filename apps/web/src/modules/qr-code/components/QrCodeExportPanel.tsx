import { Download, Sparkles } from 'lucide-react';
import { useFormContext } from 'react-hook-form';

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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { QrFieldLabel, QrHelpTooltip } from '@/modules/qr-code/components/QrCodeHelp';
import type { QrCascadeExplanation, QrFieldOrigin } from '@/modules/qr-code/support/qrCascadeExplanation';
import type { QrReadabilityReport } from '@/modules/qr-code/support/qrReadability';
import type { EventPublicLinkQrConfig, QrExportExtension } from '@/modules/qr-code/support/qrTypes';

const EXPORT_EXTENSIONS: Array<{ value: QrExportExtension; label: string }> = [
  { value: 'svg', label: 'SVG' },
  { value: 'png', label: 'PNG' },
  { value: 'jpeg', label: 'JPEG' },
  { value: 'webp', label: 'WEBP' },
];

interface QrCodeExportPanelProps {
  explanation: QrCascadeExplanation;
  readability: QrReadabilityReport;
  onDownload: () => void;
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

export function QrCodeExportPanel({ explanation, readability, onDownload, onResetSection }: QrCodeExportPanelProps) {
  const form = useFormContext<EventPublicLinkQrConfig>();

  return (
    <Card>
      <CardContent className="space-y-5 p-4">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Sparkles className="h-4 w-4 text-primary" />
            <p className="text-sm font-medium">Configuracao do arquivo</p>
            <QrHelpTooltip
              title="Configuracao do arquivo"
              description="Aqui voce escolhe como o arquivo final vai sair quando clicar em baixar."
            />
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">Origem</Badge>
            {renderOriginBadge(explanation.exportDefaults)}
            <Badge variant={readability.status === 'great' ? 'secondary' : 'outline'}>{readability.label}</Badge>
          </div>
          <p className="text-xs text-muted-foreground">
            Escolha o tipo de arquivo e o tamanho. Se a leitura estiver arriscada demais, o botao fica bloqueado.
          </p>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="space-y-1">
              <p className="text-sm font-medium">Baixar QR</p>
              <p className="text-xs text-muted-foreground">
                Use as configuracoes abaixo para gerar o arquivo final.
              </p>
            </div>
            <Button type="button" onClick={onDownload} disabled={readability.blocksExport}>
              <Download className="mr-2 h-4 w-4" />
              Baixar arquivo
            </Button>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="export_defaults.extension"
            render={({ field }) => (
              <FormItem>
                <QrFieldLabel
                  label="Tipo de arquivo"
                  description="SVG e melhor para imprimir e editar. PNG funciona bem quase sempre. JPEG e WebP costumam gerar arquivos menores."
                />
                <Select value={field.value} onValueChange={field.onChange}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Escolha o formato" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {EXPORT_EXTENSIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>
                        {option.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="export_defaults.size"
            render={({ field }) => (
              <FormItem>
                <QrFieldLabel
                  label="Tamanho do arquivo"
                  description="Quanto maior o numero, maior a qualidade e tambem o peso do arquivo."
                />
                <FormControl>
                  <Input
                    type="number"
                    min={256}
                    step={256}
                    value={field.value ?? ''}
                    onChange={(event) => field.onChange(event.target.value === '' ? 1024 : Number(event.target.value))}
                  />
                </FormControl>
                <FormDescription className="text-xs">
                  Tamanho atual: {(field.value ?? 1024)}px.
                </FormDescription>
              </FormItem>
            )}
          />
        </div>

        <Button type="button" variant="ghost" size="sm" onClick={onResetSection} className="w-full justify-start">
          Restaurar esta secao
        </Button>
      </CardContent>
    </Card>
  );
}
