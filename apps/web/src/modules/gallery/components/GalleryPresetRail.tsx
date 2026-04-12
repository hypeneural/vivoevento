import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { GalleryBuilderPreset } from '../gallery-builder';

interface GalleryPresetRailProps {
  presets: GalleryBuilderPreset[];
  appliedPresetName: string | null;
  onApplyPreset: (preset: GalleryBuilderPreset) => void;
}

export function GalleryPresetRail({
  presets,
  appliedPresetName,
  onApplyPreset,
}: GalleryPresetRailProps) {
  return (
    <Card className="rounded-[28px] border-border/60">
      <CardHeader>
        <CardTitle>Presets da organizacao</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {presets.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            Nenhum preset salvo ainda. O builder continua funcional com a matriz e os atalhos guiados.
          </p>
        ) : (
          presets.map((preset) => (
            <div
              key={preset.id}
              className="space-y-3 rounded-2xl border border-border/60 bg-background/80 p-4"
            >
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-medium">{preset.name}</p>
                {appliedPresetName === preset.name ? <Badge variant="secondary">Atual</Badge> : null}
              </div>
              <p className="text-sm text-muted-foreground">{preset.description || 'Preset sem descricao adicional.'}</p>
              <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                <span>{preset.event_type_family}</span>
                <span>{preset.style_skin}</span>
                <span>{preset.behavior_profile}</span>
              </div>
              <Button type="button" variant="outline" onClick={() => onApplyPreset(preset)}>
                Aplicar preset
              </Button>
            </div>
          ))
        )}
      </CardContent>
    </Card>
  );
}
