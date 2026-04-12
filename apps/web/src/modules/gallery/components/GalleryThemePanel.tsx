import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { GalleryThemeTokens } from '@eventovivo/shared-types';
import {
  galleryContractCatalog,
  type GalleryBuilderSettings,
} from '../gallery-builder';

interface GalleryThemePanelProps {
  draft: GalleryBuilderSettings;
  onThemeKeyChange: (themeKey: GalleryBuilderSettings['theme_key']) => void;
  onPaletteChange: (key: keyof GalleryThemeTokens['palette'], value: string) => void;
  onMotionPreferenceChange: (value: boolean) => void;
}

const PALETTE_FIELDS: Array<{ key: keyof GalleryThemeTokens['palette']; label: string }> = [
  { key: 'page_background', label: 'Fundo da pagina' },
  { key: 'surface_background', label: 'Fundo de cards' },
  { key: 'text_primary', label: 'Texto principal' },
  { key: 'accent', label: 'Accent' },
];

export function GalleryThemePanel({
  draft,
  onThemeKeyChange,
  onPaletteChange,
  onMotionPreferenceChange,
}: GalleryThemePanelProps) {
  return (
    <Card className="rounded-[28px] border-border/60">
      <CardHeader>
        <CardTitle>Tema e paleta</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="gallery-theme-key">Tema base</Label>
          <div className="flex flex-wrap gap-2">
            {galleryContractCatalog.themeKeys.map((themeKey) => (
              <Button
                key={themeKey}
                type="button"
                variant={draft.theme_key === themeKey ? 'default' : 'outline'}
                className="rounded-full"
                onClick={() => onThemeKeyChange(themeKey)}
              >
                {themeKey}
              </Button>
            ))}
          </div>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          {PALETTE_FIELDS.map((field) => (
            <div key={field.key} className="space-y-2">
              <Label htmlFor={`palette-${field.key}`}>{field.label}</Label>
              <Input
                id={`palette-${field.key}`}
                value={draft.theme_tokens.palette[field.key]}
                onChange={(event) => onPaletteChange(field.key, event.target.value)}
              />
            </div>
          ))}
        </div>

        <div className="rounded-2xl border border-border/60 bg-muted/40 p-4">
          <p className="text-sm font-medium">Reduced motion</p>
          <p className="text-xs text-muted-foreground">
            O preview precisa respeitar o contrato de reduced motion desde a configuracao.
          </p>
          <div className="mt-3 flex gap-2">
            <Button
              type="button"
              variant={draft.theme_tokens.motion.respect_user_preference ? 'default' : 'outline'}
              onClick={() => onMotionPreferenceChange(true)}
            >
              Respeitar preferencia
            </Button>
            <Button
              type="button"
              variant={!draft.theme_tokens.motion.respect_user_preference ? 'default' : 'outline'}
              onClick={() => onMotionPreferenceChange(false)}
            >
              Ignorar
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
