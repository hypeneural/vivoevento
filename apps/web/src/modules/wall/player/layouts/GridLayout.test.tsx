import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { GridLayout } from './GridLayout';
import type { WallRuntimeItem } from '../types';

function makeItem(id: string): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.jpg`,
    type: 'image',
    sender_name: `Sender ${id}`,
    sender_key: `sender-${id}`,
    senderKey: `sender-${id}`,
    source_type: 'public_upload',
    caption: null,
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    assetStatus: 'ready',
    playCount: 0,
    width: 1920,
    height: 1080,
    orientation: 'horizontal',
  };
}

describe('GridLayout', () => {
  it('limits strong animations to the configured runtime budget', () => {
    render(
      <GridLayout
        items={[makeItem('a'), makeItem('b'), makeItem('c')]}
        activeSlotIndexes={[0, 1, 2]}
        maxStrongAnimations={1}
      />,
    );

    const strongCells = screen.getAllByTestId(/grid-cell-/)
      .filter((cell) => cell.getAttribute('data-strong-animation') === 'true');

    expect(strongCells).toHaveLength(1);
    expect(strongCells[0]).toHaveAttribute('data-strong-animation', 'true');
  });
});
