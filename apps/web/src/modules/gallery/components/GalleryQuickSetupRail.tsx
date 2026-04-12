import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  formatGalleryModelMatrix,
  galleryExperienceFixtures,
  type GalleryBuilderSettings,
} from '../gallery-builder';

type GalleryFixtureKey = keyof typeof galleryExperienceFixtures;

interface GalleryQuickSetupRailProps {
  draft: GalleryBuilderSettings;
  mobileBudget: {
    lcp_ms: number;
    inp_ms: number;
    cls: number;
    percentile: number;
  };
  responsiveSizes: string;
  lastAppliedPresetName: string | null;
  onApplyShortcut: (fixtureKey: GalleryFixtureKey) => void;
}

export function GalleryQuickSetupRail({
  draft,
  mobileBudget,
  responsiveSizes,
  lastAppliedPresetName,
  onApplyShortcut,
}: GalleryQuickSetupRailProps) {
  return (
    <Card className="rounded-[28px] border-border/60">
      <CardHeader>
        <CardTitle>Resumo rapido</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap gap-2">
          <Badge variant="secondary">{formatGalleryModelMatrix(draft)}</Badge>
          <Badge variant="outline">{draft.layout_key}</Badge>
          <Badge variant="outline">{draft.media_behavior.video.mode}</Badge>
        </div>

        <div className="grid gap-3 rounded-2xl border border-border/60 bg-muted/40 p-4 text-sm sm:grid-cols-2">
          <div>
            <p className="font-medium">Budget mobile</p>
            <p className="text-muted-foreground">LCP &lt;= {mobileBudget.lcp_ms}ms</p>
            <p className="text-muted-foreground">INP &lt;= {mobileBudget.inp_ms}ms</p>
            <p className="text-muted-foreground">CLS &lt;= {mobileBudget.cls}</p>
          </div>
          <div>
            <p className="font-medium">Contrato responsivo</p>
            <p className="text-muted-foreground">P{mobileBudget.percentile} mobile/desktop</p>
            <p className="text-muted-foreground">{responsiveSizes}</p>
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">Atalhos de vibe</p>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={() => onApplyShortcut('weddingRomanticStory')}>
              Romantico story
            </Button>
            <Button type="button" variant="outline" onClick={() => onApplyShortcut('weddingPremiumLight')}>
              Premium light
            </Button>
            <Button type="button" variant="outline" onClick={() => onApplyShortcut('quinceModernLive')}>
              Quince live
            </Button>
            <Button type="button" variant="outline" onClick={() => onApplyShortcut('corporateCleanSponsors')}>
              Corporate sponsors
            </Button>
          </div>
        </div>

        <div className="rounded-2xl border border-border/60 bg-background/80 p-4 text-sm">
          <p className="font-medium">Base atual</p>
          <p className="text-muted-foreground">
            {lastAppliedPresetName ? `Ultimo preset aplicado: ${lastAppliedPresetName}` : 'Ainda sem preset aplicado manualmente.'}
          </p>
        </div>
      </CardContent>
    </Card>
  );
}
