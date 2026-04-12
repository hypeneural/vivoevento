import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
  galleryModelMatrixLabels,
  galleryModelMatrixOptions,
  type GalleryBuilderSettings,
} from '../gallery-builder';
import type { GalleryModelMatrixSelection } from '@eventovivo/shared-types';

interface GalleryQuickStartWizardProps {
  draft: GalleryBuilderSettings;
  onApplySelection: (selection: GalleryModelMatrixSelection) => void;
}

export function GalleryQuickStartWizard({
  draft,
  onApplySelection,
}: GalleryQuickStartWizardProps) {
  const [selection, setSelection] = useState<GalleryModelMatrixSelection>({
    event_type_family: draft.event_type_family,
    style_skin: draft.style_skin,
    behavior_profile: draft.behavior_profile,
  });

  useEffect(() => {
    setSelection({
      event_type_family: draft.event_type_family,
      style_skin: draft.style_skin,
      behavior_profile: draft.behavior_profile,
    });
  }, [draft.behavior_profile, draft.event_type_family, draft.style_skin]);

  return (
    <Card className="rounded-[28px] border-border/60">
      <CardHeader>
        <CardTitle>Wizard guiado</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="space-y-2">
          <p className="text-sm font-medium">1. Tipo de evento</p>
          <div className="flex flex-wrap gap-2">
            {galleryModelMatrixOptions.eventTypeFamilies.map((eventTypeFamily) => (
              <Button
                key={eventTypeFamily}
                type="button"
                variant={selection.event_type_family === eventTypeFamily ? 'default' : 'outline'}
                className={cn('rounded-full')}
                onClick={() => setSelection((current) => ({
                  ...current,
                  event_type_family: eventTypeFamily,
                }))}
              >
                {galleryModelMatrixLabels.eventTypeFamily[eventTypeFamily]}
              </Button>
            ))}
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">2. Vibe principal</p>
          <div className="flex flex-wrap gap-2">
            {galleryModelMatrixOptions.styleSkins.map((styleSkin) => (
              <Button
                key={styleSkin}
                type="button"
                variant={selection.style_skin === styleSkin ? 'default' : 'outline'}
                className={cn('rounded-full')}
                onClick={() => setSelection((current) => ({
                  ...current,
                  style_skin: styleSkin,
                }))}
              >
                {galleryModelMatrixLabels.styleSkin[styleSkin]}
              </Button>
            ))}
          </div>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">3. Comportamento</p>
          <div className="flex flex-wrap gap-2">
            {galleryModelMatrixOptions.behaviorProfiles.map((behaviorProfile) => (
              <Button
                key={behaviorProfile}
                type="button"
                variant={selection.behavior_profile === behaviorProfile ? 'default' : 'outline'}
                className={cn('rounded-full')}
                onClick={() => setSelection((current) => ({
                  ...current,
                  behavior_profile: behaviorProfile,
                }))}
              >
                {galleryModelMatrixLabels.behaviorProfile[behaviorProfile]}
              </Button>
            ))}
          </div>
        </div>

        <div className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-medium">Aplicar base do evento</p>
            <p className="text-xs text-muted-foreground">
              Isso recalcula tema, layout e comportamento com base na matriz escolhida.
            </p>
          </div>
          <Button type="button" onClick={() => onApplySelection(selection)}>
            Aplicar base do evento
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
