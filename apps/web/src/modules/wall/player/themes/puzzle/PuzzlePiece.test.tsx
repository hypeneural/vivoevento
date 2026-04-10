import fs from 'node:fs';
import path from 'node:path';

import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { PuzzlePiece } from './PuzzlePiece';
import type { WallRuntimeItem } from '../../types';

function makeImage(id: string): WallRuntimeItem {
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
    width: 1200,
    height: 900,
    orientation: 'horizontal',
  };
}

describe('PuzzlePiece', () => {
  it('renders the anchor tile without media', () => {
    render(
      <PuzzlePiece
        pieceIndex={0}
        pieceVariant="puzzle-a"
        anchorLabel="Envie sua foto"
        isAnchor={true}
        reducedMotion={true}
      />,
    );

    expect(screen.getByText('Envie sua foto')).toBeInTheDocument();
  });

  it('uses animation-frame driven drift instead of setState loops per frame', () => {
    const source = fs.readFileSync(
      path.resolve(__dirname, 'PuzzlePiece.tsx'),
      'utf8',
    );

    expect(source).toContain('useAnimationFrame');
    expect(source).toContain('useMotionValue');
    expect(source).not.toContain('setState(');
  });

  it('marks strong animations explicitly on the piece shell', () => {
    render(
      <PuzzlePiece
        pieceIndex={1}
        pieceVariant="puzzle-b"
        media={makeImage('image-1')}
        isStrongAnimation={true}
        reducedMotion={true}
      />,
    );

    expect(screen.getByTestId('puzzle-piece-1')).toHaveAttribute('data-strong-animation', 'true');
  });
});
