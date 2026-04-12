import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import {
  createGalleryBuilderOperationalFeedbackFixture,
  createGalleryBuilderRevisionFixture,
  createGalleryBuilderSettingsFixture,
} from './gallery-builder';
import { GalleryRevisionPanel } from './components/GalleryRevisionPanel';

describe('GalleryRevisionPanel', () => {
  it('lists revisions, version numbers and allows restoring a previous version', async () => {
    const user = userEvent.setup();
    const onRestore = vi.fn();
    const onGeneratePreviewLink = vi.fn();

    render(
      <GalleryRevisionPanel
        revisions={[
          createGalleryBuilderRevisionFixture(),
          createGalleryBuilderRevisionFixture({
            id: 202,
            version_number: 6,
            kind: 'publish',
            change_summary: { reason: 'Publicacao anterior', source: 'builder', layers: ['theme_tokens'] },
          }),
        ]}
        settings={createGalleryBuilderSettingsFixture()}
        operationalFeedback={createGalleryBuilderOperationalFeedbackFixture()}
        onRestore={onRestore}
        onGeneratePreviewLink={onGeneratePreviewLink}
        isGeneratingPreview={false}
      />,
    );

    expect(screen.getByText('Draft v7')).toBeInTheDocument();
    expect(screen.getByText('Publicado v5')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /restaurar revisao 6/i }));

    expect(onRestore).toHaveBeenCalledWith(202);
  });
});
