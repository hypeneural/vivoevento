import { useMemo, useState } from 'react';
import { Sparkles, Wand2 } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

import {
  buildJourneyTemplatePreview,
  JOURNEY_TEMPLATE_DEFINITIONS,
  type JourneyTemplateId,
  type JourneyTemplatePreview,
} from './buildJourneyTemplatePreview';
import { humanizeJourneyText } from './journeyCopy';
import type { EventJourneyProjection } from './types';

interface JourneyTemplateRailProps {
  projection: EventJourneyProjection;
  activeTemplatePreview: JourneyTemplatePreview | null;
  isPending?: boolean;
  onApplyTemplate: (templateId: JourneyTemplateId) => void;
  onDiscardTemplate: () => void;
  onSaveTemplate: () => void;
}

export function JourneyTemplateRail({
  projection,
  activeTemplatePreview,
  isPending = false,
  onApplyTemplate,
  onDiscardTemplate,
  onSaveTemplate,
}: JourneyTemplateRailProps) {
  const [pendingTemplateId, setPendingTemplateId] = useState<JourneyTemplateId | null>(null);

  const pendingTemplatePreview = useMemo(
    () => (pendingTemplateId ? buildJourneyTemplatePreview(projection, pendingTemplateId) : null),
    [pendingTemplateId, projection],
  );

  return (
    <>
      <Card className="border-white/70 bg-white/90 shadow-sm">
        <CardHeader className="space-y-3 pb-3">
          <div className="flex items-center gap-2">
            <Wand2 className="h-4 w-4 text-primary" />
            <CardTitle className="text-base">Modelos prontos</CardTitle>
          </div>
          <CardDescription>
            Compare modelos prontos de operacao. O modelo altera esta tela primeiro e so salva quando voce confirmar.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap gap-2">
            {JOURNEY_TEMPLATE_DEFINITIONS.map((template) => {
              const isActive = activeTemplatePreview?.template.id === template.id;

              return (
                <Button
                  key={template.id}
                  type="button"
                  size="sm"
                  variant={isActive ? 'default' : 'outline'}
                  disabled={isPending}
                  onClick={() => setPendingTemplateId(template.id)}
                >
                  {template.label}
                </Button>
              );
            })}
          </div>

          {activeTemplatePreview ? (
            <Alert className="border-primary/20 bg-primary/5">
              <Sparkles className="h-4 w-4" />
              <AlertTitle>Rascunho local ativo</AlertTitle>
              <AlertDescription>
                O modelo <strong>{activeTemplatePreview.template.label}</strong> ja mudou o resumo e o mapa visual nesta tela.
                Salve ou descarte antes de voltar para edicoes manuais no painel lateral.
              </AlertDescription>
            </Alert>
          ) : (
            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
              Nenhum modelo em rascunho. Escolha um dos modelos acima para comparar com a configuracao atual do evento.
            </div>
          )}

          {activeTemplatePreview ? (
            <div className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">Rascunho local</Badge>
                <p className="text-sm font-medium text-foreground">{activeTemplatePreview.template.label}</p>
              </div>

              <ul className="space-y-2 text-sm text-foreground/90">
                {activeTemplatePreview.diff.length > 0 ? activeTemplatePreview.diff.map((item) => (
                  <li key={item.id} className="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <span className="font-medium">{humanizeJourneyText(item.label)}: </span>
                    <span>{humanizeJourneyText(item.description)}</span>
                    {item.kind === 'skipped' ? (
                      <Badge variant="outline" className="ml-2">Nao aplicado</Badge>
                    ) : null}
                  </li>
                )) : (
                  <li className="rounded-xl border border-slate-200 bg-white px-3 py-2">
                    O modelo coincide com a configuracao atual e nao gerou diferenca nesta tela.
                  </li>
                )}
              </ul>

              <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" disabled={isPending} onClick={onDiscardTemplate}>
                  Descartar rascunho
                </Button>
                <Button type="button" disabled={isPending} onClick={onSaveTemplate}>
                  {isPending ? 'Salvando modelo...' : 'Salvar modelo'}
                </Button>
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>

      <AlertDialog open={pendingTemplatePreview !== null} onOpenChange={(open) => !open && setPendingTemplateId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{pendingTemplatePreview?.template.label ?? 'Comparar modelo'}</AlertDialogTitle>
            <AlertDialogDescription>
              {humanizeJourneyText(pendingTemplatePreview?.template.description ?? 'Compare o modelo antes de aplicar ao rascunho local.')}
            </AlertDialogDescription>
          </AlertDialogHeader>

          {pendingTemplatePreview ? (
            <div className="space-y-3">
              <p className="text-sm font-medium text-foreground">O que vai mudar nesta tela</p>
              <ul className="space-y-2 text-sm text-foreground/90">
                {pendingTemplatePreview.diff.length > 0 ? pendingTemplatePreview.diff.map((item) => (
                  <li key={item.id} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <span className="font-medium">{humanizeJourneyText(item.label)}: </span>
                    <span>{humanizeJourneyText(item.description)}</span>
                    {item.kind === 'skipped' ? (
                      <Badge variant="outline" className="ml-2">Nao aplicado</Badge>
                    ) : null}
                  </li>
                )) : (
                  <li className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    O modelo coincide com a configuracao atual do evento.
                  </li>
                )}
              </ul>
            </div>
          ) : null}

          <AlertDialogFooter>
            <AlertDialogCancel disabled={isPending}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              disabled={isPending || pendingTemplatePreview === null}
              onClick={(event) => {
                event.preventDefault();

                if (!pendingTemplatePreview) {
                  return;
                }

                onApplyTemplate(pendingTemplatePreview.template.id);
                setPendingTemplateId(null);
              }}
            >
              Aplicar nesta tela
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
