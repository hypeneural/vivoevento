import { Settings } from 'lucide-react';
import { useFormContext } from 'react-hook-form';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
} from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import type { EventPublicLinkQrConfig } from '@/modules/qr-code/support/qrTypes';

const ERROR_CORRECTION_LEVELS = [
  { value: 'L', label: 'L · 7%' },
  { value: 'M', label: 'M · 15%' },
  { value: 'Q', label: 'Q · 25%' },
  { value: 'H', label: 'H · 30%' },
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
            <p className="text-sm font-medium">Controles avancados</p>
          </div>
          <p className="text-xs text-muted-foreground">
            Quiet zone e limites de logo continuam blindados pela camada semantica e pelos guardrails.
          </p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="advanced.error_correction_level"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Correcao de erro</FormLabel>
                <Select value={field.value} onValueChange={field.onChange}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Escolha o nivel ECC" />
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
                <FormLabel>Shape</FormLabel>
                <Select value={field.value} onValueChange={field.onChange}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Escolha o shape" />
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
              <FormLabel>Tamanho do preview</FormLabel>
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
              <FormLabel>Quiet zone</FormLabel>
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
                Valor atual: {field.value} modulos. O schema nao permite ficar abaixo de `4`.
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
                <FormLabel>Arredondar tamanho dos dots</FormLabel>
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
