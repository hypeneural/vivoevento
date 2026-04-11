import type { ReactElement } from 'react';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import { JourneyNodeCard } from '../JourneyNodeCard';

function renderCard(ui: ReactElement) {
  return render(
    <TooltipProvider delayDuration={0}>
      {ui}
    </TooltipProvider>,
  );
}

describe('JourneyNodeCard', () => {
  it('renders an operational card with stage, status and optional behavior chips', () => {
    renderCard(
      <JourneyNodeCard
        nodeId="entry_whatsapp_direct"
        stage="entry"
        kind="entry"
        status="active"
        label="WhatsApp privado"
        description="Recebe fotos por conversa privada."
        summary="Recebe midias por codigo privado."
        editable
        warningCount={1}
        branchLabels={['Padrao']}
        highlighted={false}
        selected={false}
      />,
    );

    expect(screen.getByText('Entrada')).toBeInTheDocument();
    expect(screen.getByText('WhatsApp particular')).toBeInTheDocument();
    expect(screen.getByText('Ligado')).toBeInTheDocument();
    expect(screen.getByText('Pode ajustar')).toBeInTheDocument();
    expect(screen.getByText('1 alerta')).toBeInTheDocument();
  });

  it('renders the decision variation with branch chips and humanized locked status', () => {
    renderCard(
      <JourneyNodeCard
        nodeId="decision_event_moderation_mode"
        stage="decision"
        kind="decision"
        status="locked"
        label="Modo de moderacao do evento"
        description="Define se aprova direto ou manda para revisao."
        summary="Moderacao por IA ativa."
        editable={false}
        warningCount={0}
        branchLabels={['IA', 'Manual', 'Review', 'Bloqueado']}
        highlighted
        selected
      />,
    );

    expect(screen.getByText('Decisao')).toBeInTheDocument();
    expect(screen.getByText('Disponivel em outro plano')).toBeInTheDocument();
    expect(screen.getByText('Feito pelo sistema')).toBeInTheDocument();
    expect(screen.getByText('Possiveis resultados')).toBeInTheDocument();
    expect(screen.getByText('IA')).toBeInTheDocument();
    expect(screen.getByText('Manual')).toBeInTheDocument();
    expect(screen.queryByText('Revisao manual')).not.toBeInTheDocument();
    expect(screen.getByText('+2')).toBeInTheDocument();
  });
});
