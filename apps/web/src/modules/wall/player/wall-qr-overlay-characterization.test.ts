import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

describe('wall qr overlay architecture characterization', () => {
  it('wires the live player to the event upload url and the manager preview to a fixed preview qr url', () => {
    const playerRootSource = fs.readFileSync(
      path.resolve(__dirname, 'components/WallPlayerRoot.tsx'),
      'utf8',
    );
    const previewCanvasSource = fs.readFileSync(
      path.resolve(__dirname, '../components/manager/stage/WallPreviewCanvas.tsx'),
      'utf8',
    );

    expect(playerRootSource).toContain('qrUrl={state.event?.upload_url}');
    expect(previewCanvasSource).toContain("const PREVIEW_QR_URL = 'https://eventovivo.local/preview-upload'");
  });

  it('keeps the live qr overlay copy hardcoded instead of using instructions_text', () => {
    const brandingOverlaySource = fs.readFileSync(
      path.resolve(__dirname, 'components/BrandingOverlay.tsx'),
      'utf8',
    );
    const playerRootSource = fs.readFileSync(
      path.resolve(__dirname, 'components/WallPlayerRoot.tsx'),
      'utf8',
    );

    expect(brandingOverlaySource).toContain('Envie sua foto');
    expect(brandingOverlaySource).toContain('Aponte a camera para entrar no upload do evento.');
    expect(playerRootSource).not.toContain('instructions_text=');
  });

  it('keeps puzzle qr_prompt as a text anchor only and leaves qr flip/embed hooks outside the live player flow', () => {
    const puzzleLayoutSource = fs.readFileSync(
      path.resolve(__dirname, 'themes/puzzle/PuzzleLayout.tsx'),
      'utf8',
    );
    const puzzlePieceSource = fs.readFileSync(
      path.resolve(__dirname, 'themes/puzzle/PuzzlePiece.tsx'),
      'utf8',
    );
    const playerRootSource = fs.readFileSync(
      path.resolve(__dirname, 'components/WallPlayerRoot.tsx'),
      'utf8',
    );

    expect(puzzleLayoutSource).toContain("anchorMode === 'qr_prompt' ? 'Envie sua foto' : 'Evento Vivo'");
    expect(puzzlePieceSource).not.toContain('QRCodeSVG');
    expect(playerRootSource).not.toContain('QRFlipCard');
    expect(playerRootSource).not.toContain('useEmbedMode');
  });
});
