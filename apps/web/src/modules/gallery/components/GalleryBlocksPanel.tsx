import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  formatGalleryDensity,
  galleryContractCatalog,
  type GalleryBuilderSettings,
} from '../gallery-builder';
import type { GalleryDensity, GalleryVideoMode } from '@eventovivo/shared-types';

interface GalleryBlocksPanelProps {
  draft: GalleryBuilderSettings;
  onLayoutKeyChange: (layoutKey: GalleryBuilderSettings['layout_key']) => void;
  onGridLayoutChange: (layout: GalleryBuilderSettings['media_behavior']['grid']['layout']) => void;
  onDensityChange: (density: GalleryDensity) => void;
  onVideoModeChange: (mode: GalleryVideoMode) => void;
  onBlockToggle: (blockKey: 'hero' | 'banner_strip' | 'quote' | 'footer_brand', enabled: boolean) => void;
}

const GRID_LAYOUTS: Array<GalleryBuilderSettings['media_behavior']['grid']['layout']> = ['masonry', 'rows', 'columns', 'justified'];

export function GalleryBlocksPanel({
  draft,
  onLayoutKeyChange,
  onGridLayoutChange,
  onDensityChange,
  onVideoModeChange,
  onBlockToggle,
}: GalleryBlocksPanelProps) {
  const heroBlock = draft.page_schema.blocks.hero as { enabled?: boolean } | undefined;
  const bannerBlock = draft.page_schema.blocks.banner_strip as { enabled?: boolean } | undefined;
  const quoteBlock = draft.page_schema.blocks.quote as { enabled?: boolean } | undefined;
  const footerBlock = draft.page_schema.blocks.footer_brand as { enabled?: boolean } | undefined;

  return (
    <Card className="rounded-[28px] border-border/60">
      <CardHeader>
        <CardTitle>Blocos e comportamento</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <p className="text-sm font-medium">Layout do builder</p>
          <div className="flex flex-wrap gap-2">
            {galleryContractCatalog.layoutKeys.map((layoutKey) => (
              <Button
                key={layoutKey}
                type="button"
                variant={draft.layout_key === layoutKey ? 'default' : 'outline'}
                className="rounded-full"
                onClick={() => onLayoutKeyChange(layoutKey)}
              >
                {layoutKey}
              </Button>
            ))}
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">Renderer publico</p>
          <div className="flex flex-wrap gap-2">
            {GRID_LAYOUTS.map((layout) => (
              <Button
                key={layout}
                type="button"
                variant={draft.media_behavior.grid.layout === layout ? 'default' : 'outline'}
                onClick={() => onGridLayoutChange(layout)}
              >
                {layout}
              </Button>
            ))}
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">Densidade</p>
          <div className="flex flex-wrap gap-2">
            {galleryContractCatalog.densities.map((density) => (
              <Button
                key={density}
                type="button"
                variant={draft.media_behavior.grid.density === density ? 'default' : 'outline'}
                onClick={() => onDensityChange(density)}
              >
                {formatGalleryDensity(density)}
              </Button>
            ))}
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">Video</p>
          <div className="flex flex-wrap gap-2">
            {galleryContractCatalog.videoModes.map((mode) => (
              <Button
                key={mode}
                type="button"
                variant={draft.media_behavior.video.mode === mode ? 'default' : 'outline'}
                onClick={() => onVideoModeChange(mode)}
              >
                {mode}
              </Button>
            ))}
          </div>
        </div>

        <div className="grid gap-3 rounded-2xl border border-border/60 bg-muted/40 p-4 sm:grid-cols-2">
          <Button type="button" variant={heroBlock?.enabled ? 'default' : 'outline'} onClick={() => onBlockToggle('hero', !heroBlock?.enabled)}>
            Hero {heroBlock?.enabled ? 'ativo' : 'oculto'}
          </Button>
          <Button type="button" variant={bannerBlock?.enabled ? 'default' : 'outline'} onClick={() => onBlockToggle('banner_strip', !bannerBlock?.enabled)}>
            Banner {bannerBlock?.enabled ? 'ativo' : 'oculto'}
          </Button>
          <Button type="button" variant={quoteBlock?.enabled ? 'default' : 'outline'} onClick={() => onBlockToggle('quote', !quoteBlock?.enabled)}>
            Quote {quoteBlock?.enabled ? 'ativo' : 'oculto'}
          </Button>
          <Button type="button" variant={footerBlock?.enabled ? 'default' : 'outline'} onClick={() => onBlockToggle('footer_brand', !footerBlock?.enabled)}>
            Footer {footerBlock?.enabled ? 'ativo' : 'oculto'}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
