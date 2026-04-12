import { useMemo } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Hammer, LayoutTemplate, ShieldCheck, Smartphone } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { galleryContractCatalog, galleryExperienceFixtures } from './gallery-builder';

export default function GalleryBuilderPage() {
  const { id } = useParams<{ id: string }>();

  const fixtureSummary = useMemo(() => ([
    galleryExperienceFixtures.weddingRomanticStory.model_matrix,
    galleryExperienceFixtures.quinceModernLive.model_matrix,
    galleryExperienceFixtures.corporateCleanSponsors.model_matrix,
  ]), []);

  return (
    <div className="space-y-6">
      <div className="space-y-2">
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">Sprint 0</Badge>
          <Badge variant="outline">Gallery Builder</Badge>
        </div>
        <h1 className="text-3xl font-semibold tracking-tight">Gallery Builder</h1>
        <p className="text-sm text-muted-foreground">
          Base do builder por evento. Esta tela existe para travar contrato, permissao, rota e fixtures
          antes da implementacao completa do editor.
        </p>
        {id ? (
          <p className="text-xs text-muted-foreground">Evento atual: {id}</p>
        ) : null}
      </div>

      <Alert>
        <ShieldCheck className="h-4 w-4" />
        <AlertTitle>Contrato congelado</AlertTitle>
        <AlertDescription>
          O builder nasce em torno de `theme_tokens`, `page_schema` e `media_behavior`, com matriz
          de modelos e budget mobile-first documentados.
        </AlertDescription>
      </Alert>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <LayoutTemplate className="h-4 w-4" />
              Model matrix
            </CardTitle>
            <CardDescription>Entrada humana por tipo de evento, estilo e comportamento.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-muted-foreground">
            {fixtureSummary.map((fixture) => (
              <div key={`${fixture.event_type_family}-${fixture.style_skin}-${fixture.behavior_profile}`}>
                {fixture.event_type_family} / {fixture.style_skin} / {fixture.behavior_profile}
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Smartphone className="h-4 w-4" />
              Mobile contract
            </CardTitle>
            <CardDescription>Budgets travados para a V1.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-1 text-sm text-muted-foreground">
            <div>LCP: {galleryContractCatalog.mobileBudget.lcp_ms}ms</div>
            <div>INP: {galleryContractCatalog.mobileBudget.inp_ms}ms</div>
            <div>CLS: {galleryContractCatalog.mobileBudget.cls}</div>
            <div>P{galleryContractCatalog.mobileBudget.percentile} mobile/desktop</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Hammer className="h-4 w-4" />
              Renderer defaults
            </CardTitle>
            <CardDescription>Layout e video ja fixados para a fase inicial.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-1 text-sm text-muted-foreground">
            <div>Layouts: {galleryContractCatalog.layoutKeys.join(', ')}</div>
            <div>Video: {galleryContractCatalog.videoModes.join(', ')}</div>
            <div>Sizes: {galleryContractCatalog.publicResponsiveSizes}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Proximo passo</CardTitle>
            <CardDescription>Seguir a `Sprint 1` do plano de execucao.</CardDescription>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground">
            <Link to="/gallery" className="text-primary underline underline-offset-4">
              Abrir modulo atual da galeria
            </Link>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
