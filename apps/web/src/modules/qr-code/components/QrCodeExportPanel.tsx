import { Sparkles } from 'lucide-react';
import { useFormContext } from 'react-hook-form';

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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { EventPublicLinkQrConfig, QrExportExtension } from '@/modules/qr-code/support/qrTypes';
import type { QrCascadeExplanation, QrFieldOrigin } from '@/modules/qr-code/support/qrCascadeExplanation';

const EXPORT_EXTENSIONS: Array<{ value: QrExportExtension; label: string }> = [
  { value: 'svg', label: 'SVG' },
  { value: 'png', label: 'PNG' },
  { value: 'jpeg', label: 'JPEG' },
  { value: 'webp', label: 'WEBP' },
];

interface QrCodeExportPanelProps {
  explanation: QrCascadeExplanation;
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

export function QrCodeExportPanel({ explanation, onResetSection }: QrCodeExportPanelProps) {
  const form = useFormContext<EventPublicLinkQrConfig>();

  return (
    <Card>
      <CardContent className="space-y-5 p-4">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Sparkles className="h-4 w-4 text-primary" />
            <p className="text-sm font-medium">Defaults de exportacao</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">Origem</Badge>
            {renderOriginBadge(explanation.exportDefaults)}
          </div>
          <p className="text-xs text-muted-foreground">
            Estes campos ainda nao disparam download. Eles so preparam o estado que sera usado quando a exportacao real entrar.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="export_defaults.extension"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Formato padrao</FormLabel>
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
                <FormLabel>Tamanho de exportacao</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    min={256}
                    step={256}
                    value={field.value}
                    onChange={(event) => field.onChange(Number(event.target.value))}
                  />
                </FormControl>
                <FormDescription className="text-xs">
                  Tamanho atual: {field.value}px.
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
