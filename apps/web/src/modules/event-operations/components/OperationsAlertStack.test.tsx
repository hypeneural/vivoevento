import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { eventOperationsDegradedSnapshotFixture } from '../__fixtures__/operations-room.fixture';
import { OperationsAlertStack } from './OperationsAlertStack';

describe('OperationsAlertStack', () => {
  it('renders the active operational alerts without mixing them into the normal status layer', () => {
    render(<OperationsAlertStack alerts={eventOperationsDegradedSnapshotFixture.alerts} />);

    expect(screen.getByText('Alertas vivos')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('Player do telao offline');
    expect(screen.getByRole('alert')).toHaveTextContent('Um player do wall parou de enviar heartbeat.');
  });

  it('shows an explicit empty state when there are no live alerts', () => {
    render(<OperationsAlertStack alerts={[]} />);

    expect(screen.getByText('Nenhum alerta vivo.')).toBeInTheDocument();
  });
});
