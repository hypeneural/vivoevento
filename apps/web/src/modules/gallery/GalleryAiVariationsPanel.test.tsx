import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import {
  createGalleryAiProposalsFixture,
} from './gallery-builder';
import { GalleryAiVariationsPanel } from './components/GalleryAiVariationsPanel';

describe('GalleryAiVariationsPanel', () => {
  it('renders the ai prompt flow and variation apply actions', async () => {
    const user = userEvent.setup();
    const proposals = createGalleryAiProposalsFixture();
    const onGenerate = vi.fn();
    const onApplyVariation = vi.fn();

    render(
      <GalleryAiVariationsPanel
        promptText="quero uma galeria romantica em tons rose"
        targetLayer="mixed"
        run={proposals.run}
        variations={proposals.variations}
        isGenerating={false}
        isApplyingVariationId={null}
        previewRequired={false}
        onPromptTextChange={vi.fn()}
        onTargetLayerChange={vi.fn()}
        onGenerate={onGenerate}
        onApplyVariation={onApplyVariation}
      />,
    );

    expect(screen.getByRole('heading', { name: 'Assistente de IA' })).toBeInTheDocument();
    expect(screen.getByLabelText('Pedido da IA')).toHaveValue('quero uma galeria romantica em tons rose');
    expect(screen.getByText('Romantico suave')).toBeInTheDocument();
    expect(screen.getByText('local-guardrailed')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /Gerar 3 variacoes seguras/i }));
    expect(onGenerate).toHaveBeenCalledTimes(1);

    await user.click(screen.getAllByRole('button', { name: /So paleta/i })[0]);
    expect(onApplyVariation).toHaveBeenCalledWith(proposals.variations[0], 'theme_tokens');
  });
});
