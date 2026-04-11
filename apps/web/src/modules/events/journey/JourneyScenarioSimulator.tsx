import { GitBranch, XCircle } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

import type { EventJourneyBuiltScenario } from './types';

interface JourneyScenarioSimulatorProps {
  scenarios: EventJourneyBuiltScenario[];
  selectedScenario: EventJourneyBuiltScenario | null;
  onScenarioSelect: (scenario: EventJourneyBuiltScenario | null) => void;
}

function outcomeMeta(outcome: EventJourneyBuiltScenario['outcome']) {
  switch (outcome) {
    case 'approved':
      return {
        label: 'Aprovado',
        className: 'border-emerald-200 bg-emerald-100 text-emerald-800',
      };
    case 'review':
      return {
        label: 'Revisao',
        className: 'border-amber-200 bg-amber-100 text-amber-800',
      };
    case 'blocked':
      return {
        label: 'Bloqueado',
        className: 'border-rose-200 bg-rose-100 text-rose-800',
      };
    default:
      return {
        label: 'Inativo',
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };
  }
}

export function JourneyScenarioSimulator({
  scenarios,
  selectedScenario,
  onScenarioSelect,
}: JourneyScenarioSimulatorProps) {
  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2 text-sm font-medium text-foreground">
        <GitBranch className="h-4 w-4 text-primary" />
        Simulador de cenarios
      </div>

      <div className="flex flex-wrap gap-2">
        {scenarios.map((scenario) => {
          const isActive = selectedScenario?.id === scenario.id;

          return (
            <Button
              key={scenario.id}
              type="button"
              size="sm"
              variant={isActive ? 'default' : 'outline'}
              disabled={!scenario.available}
              title={scenario.available ? scenario.description : scenario.unavailableReason ?? undefined}
              onClick={() => onScenarioSelect(isActive ? null : scenario)}
            >
              {scenario.label}
            </Button>
          );
        })}
      </div>

      {selectedScenario ? (
        <Alert className="border-primary/20 bg-primary/5">
          <GitBranch className="h-4 w-4" />
          <AlertTitle className="flex flex-wrap items-center gap-2">
            <span>Simulacao ativa</span>
            <Badge className={outcomeMeta(selectedScenario.outcome).className}>
              {outcomeMeta(selectedScenario.outcome).label}
            </Badge>
          </AlertTitle>
          <AlertDescription className="space-y-3">
            <p className="font-medium text-foreground">{selectedScenario.label}</p>
            <p>{selectedScenario.humanText}</p>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-xs text-muted-foreground">
                O caminho destacado no canvas tambem aparece no inspector lateral.
              </p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onScenarioSelect(null)}
              >
                <XCircle className="mr-1.5 h-3.5 w-3.5" />
                Limpar simulacao
              </Button>
            </div>
          </AlertDescription>
        </Alert>
      ) : null}
    </div>
  );
}
