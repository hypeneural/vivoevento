import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

describe('face search product ux characterization', () => {
  it('already keeps the event editor on the product wording for facial recognition activation', () => {
    const editorSource = fs.readFileSync(
      path.resolve(__dirname, '../events/components/EventEditorPage.tsx'),
      'utf8',
    );

    expect(editorSource).toContain('Ativar reconhecimento facial');
    expect(editorSource).not.toContain('Busca por selfie');
    expect(editorSource).toContain('face_search.enabled');
    expect(editorSource).toContain('face_search.allow_public_selfie_search');
    expect(editorSource).toContain('face_search.selfie_retention_hours');
  });

  it('already exposes a simple operational status and the internal search in the event detail surface', () => {
    const eventDetailSource = fs.readFileSync(
      path.resolve(__dirname, '../events/EventDetailPage.tsx'),
      'utf8',
    );

    expect(eventDetailSource).toContain('EventFaceSearchSettingsCard');
    expect(eventDetailSource).toContain('EventFaceSearchSearchCard');
    expect(eventDetailSource).toContain('Reconhecimento facial:');
    expect(eventDetailSource).toContain('Busca para convidados:');
    expect(eventDetailSource).toContain('faceSearchStatus.label');
    expect(eventDetailSource).toContain('faceSearchStatus.notes.map');
  });

  it('already exposes a direct find-me entry point in the public hub and public gallery', () => {
    const publicHubSource = fs.readFileSync(
      path.resolve(__dirname, '../hub/PublicHubPage.tsx'),
      'utf8',
    );
    const publicGallerySource = fs.readFileSync(
      path.resolve(__dirname, '../gallery/PublicGalleryPage.tsx'),
      'utf8',
    );
    const publicFaceSearchPageSource = fs.readFileSync(
      path.resolve(__dirname, 'PublicFaceSearchPage.tsx'),
      'utf8',
    );

    expect(publicHubSource).toContain('Encontrar minhas fotos');

    expect(publicGallerySource).toContain('Encontrar minhas fotos');

    expect(publicFaceSearchPageSource).toContain('Buscar minhas fotos');
    expect(publicFaceSearchPageSource).toContain('Abrir galeria publica');
  });
});
