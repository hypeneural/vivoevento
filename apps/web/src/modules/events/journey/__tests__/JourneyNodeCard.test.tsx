import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { JourneyNodeCard } from '../JourneyNodeCard';

describe('JourneyNodeCard', () => {
  it('renders an operational card with stage, status and optional behavior chips', () => {
    render(
      <JourneyNodeCard
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
    expect(screen.getByText('WhatsApp privado')).toBeInTheDocument();
    expect(screen.getByText('Ativo')).toBeInTheDocument();
    expect(screen.getByText('Opcional')).toBeInTheDocument();
    expect(screen.getByText('1 alerta')).toBeInTheDocument();
  });

  it('renders the decision variation with branch chips and humanized locked status', () => {
    render(
      <JourneyNodeCard
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
    expect(screen.getByText('Bloqueado pelo pacote')).toBeInTheDocument();
    expect(screen.getByText('Automatico')).toBeInTheDocument();
    expect(screen.getByText('Caminhos da decisao')).toBeInTheDocument();
    expect(screen.getByText('IA')).toBeInTheDocument();
    expect(screen.getByText('Manual')).toBeInTheDocument();
    expect(screen.getByText('Review')).toBeInTheDocument();
    expect(screen.getByText('+1')).toBeInTheDocument();
  });
});
