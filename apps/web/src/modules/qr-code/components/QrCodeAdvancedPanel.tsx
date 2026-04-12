import { Settings } from 'lucide-react';
import { useFormContext } from 'react-hook-form';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
} from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { QrFieldLabel, QrHelpTooltip } from '@/modules/qr-code/components/QrCodeHelp';
import type { EventPublicLinkQrConfig } from '@/modules/qr-code/support/qrTypes';

const ERROR_CORRECTION_LEVELS = [
  { value: 'L', label: 'Baixa - L - 7%' },
  { value: 'M', label: 'Media - M - 15%' },
  { value: 'Q', label: 'Alta - Q - 25%' },
  { value: 'H', label: 'Maxima - H - 30%' },
] as const;

const SHAPE_OPTIONS = [
  { value: 'square', label: 'Quadrado' },
  { value: 'circle', label: 'Circular' },
] as const;

export function QrCodeAdvancedPanel({ onResetSection }: { onResetSection: () => void }) {
  const form = useFormContext<EventPublicLinkQrConfig>();

  return (
    <Card>
      <CardContent className="space-y-5 p-4">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Settings className="h-4 w-4 text-primary" />
            <p className="text-sm font-medium">Ajustes avancados</p>
            <QrHelpTooltip
              title="Ajustes avancados"
              description="Aqui ficam os controles mais tecnicos. O editor continua protegendo os limites minimos para nao sacrificar a leitura."
            />
          </div>
          <p className="text-xs text-muted-foreground">
            A margem de respiro e os limites da logo continuam protegidos pelo contrato do produto.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="advanced.error_correction_level"
            render={({ field }) => (
              <FormItem>
                <QrFieldLabel
                  label="Protecao de leitura"
                  description="E a reserva interna do QR para continuar funcionando mesmo se uma parte ficar coberta, suja ou com logo no meio. Quanto maior, mais seguro."
                />
                <Select value={field.value} onValueChange={field.onChange}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Escolha o nivel de protecao" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {ERROR_CORRECTION_LEVELS.map((option) => (
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
            name="advanced.shape"
            render={({ field }) => (
              <FormItem>
                <QrFieldLabel
                  label="Formato do QR"
                  description="Quadrado e o formato mais seguro. O formato circular muda o acabamento visual e costuma exigir mais cuidado com contraste."
                />
                <Select value={field.value} onValueChange={field.onChange}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Escolha o formato" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {SHAPE_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>
                        {option.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="render.preview_size"
          render={({ field }) => (
            <FormItem>
              <QrFieldLabel
                label="Tamanho na tela"
                description="Muda apenas o tamanho da pre-visualizacao dentro do editor. O arquivo final continua usando o tamanho escolhido na aba Baixar."
              />
              <FormControl>
                <Slider
                  min={240}
                  max={400}
                  step={20}
                  value={[field.value]}
                  onValueChange={([nextValue]) => field.onChange(nextValue)}
                />
              </FormControl>
              <FormDescription className="text-xs">
                Valor atual: {field.value}px.
              </FormDescription>
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="render.margin_modules"
          render={({ field }) => (
            <FormItem>
              <QrFieldLabel
                label="Margem de respiro"
                description="E a faixa limpa em volta do QR. Os celulares usam esse espaco em branco para reconhecer o codigo com mais facilidade."
              />
              <FormControl>
                <Slider
                  min={4}
                  max={8}
                  step={1}
                  value={[field.value]}
                  onValueChange={([nextValue]) => field.onChange(nextValue)}
                />
              </FormControl>
              <FormDescription className="text-xs">
                Valor atual: {field.value} modulos. O sistema nao deixa ficar abaixo de 4.
              </FormDescription>
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="advanced.round_size"
          render={({ field }) => (
            <FormItem className="flex flex-row items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
              <div className="space-y-1">
                <QrFieldLabel
                  label="Arredondar os pontos"
                  description="Deixa o desenho mais suave. Se desligar, os pontos ficam mais secos e precisos."
                />
                <FormDescription className="text-xs">
                  Desligar pode deixar o SVG mais seco e mais preciso em alguns cenarios.
                </FormDescription>
              </div>
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} />
              </FormControl>
            </FormItem>
          )}
        />

        <Button type="button" variant="ghost" size="sm" onClick={onResetSection} className="w-full justify-start">
          Restaurar esta secao
        </Button>
      </CardContent>
    </Card>
  );
}
