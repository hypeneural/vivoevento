import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { QrHelpTooltip } from './QrCodeHelp';

describe('QrCodeHelp', () => {
  it('shows the explanatory tooltip for leigo-facing labels', async () => {
    const user = userEvent.setup({ delay: null });

    render(
      <QrHelpTooltip
        title="Protecao de leitura"
        description="Explica que o QR continua funcionando mesmo com uma parte coberta."
      />,
    );

    await user.hover(screen.getByRole('button', { name: /Ajuda sobre Protecao de leitura/i }));

    expect(await screen.findByRole('tooltip')).toBeInTheDocument();
    expect(screen.getAllByText(/continua funcionando mesmo com uma parte coberta/i).length).toBeGreaterThan(0);
  });
});
