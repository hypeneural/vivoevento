import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { OperationsStatusPill } from './OperationsStatusPill';

describe('OperationsStatusPill', () => {
  it('announces normal lifecycle updates as status', () => {
    render(<OperationsStatusPill label="Conexao" value="Conectado" tone="healthy" />);

    expect(screen.getByRole('status')).toHaveTextContent('Conexao');
    expect(screen.getByRole('status')).toHaveTextContent('Conectado');
  });

  it('announces urgent degradation as alert', () => {
    render(<OperationsStatusPill label="Live" value="Degradado" tone="critical" urgent />);

    expect(screen.getByRole('alert')).toHaveTextContent('Live');
    expect(screen.getByRole('alert')).toHaveTextContent('Degradado');
  });
});
