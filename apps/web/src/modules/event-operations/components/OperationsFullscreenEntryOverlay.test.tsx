import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { OperationsFullscreenEntryOverlay } from './OperationsFullscreenEntryOverlay';

describe('OperationsFullscreenEntryOverlay', () => {
  it('explains how to enter and leave the room mode', () => {
    render(<OperationsFullscreenEntryOverlay onEnterRoom={vi.fn()} fullscreenError={null} />);

    expect(screen.getByRole('button', { name: 'Entrar em modo sala' })).toBeInTheDocument();
    expect(screen.getByText('Leia a saude global primeiro.')).toBeInTheDocument();
    expect(screen.getByText('Procure a estacao dominante quando houver gargalo.')).toBeInTheDocument();
    expect(screen.getByText('Pressione Esc para sair do fullscreen.')).toBeInTheDocument();
  });

  it('shows a graceful fallback when fullscreen is denied', () => {
    render(
      <OperationsFullscreenEntryOverlay
        onEnterRoom={vi.fn()}
        fullscreenError="O navegador recusou o fullscreen."
      />,
    );

    expect(screen.getByText('O navegador recusou o fullscreen.')).toBeInTheDocument();
    expect(screen.getByText('A sala continua disponivel em modo janela.')).toBeInTheDocument();
  });
});
