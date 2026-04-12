import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it } from 'vitest';

import GalleryBuilderPage from './GalleryBuilderPage';

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/events/42/gallery/builder']}>
      <Routes>
        <Route path="/events/:id/gallery/builder" element={<GalleryBuilderPage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('GalleryBuilderPage', () => {
  it('renders the sprint zero builder shell and contract summary', () => {
    renderPage();

    expect(screen.getByRole('heading', { name: 'Gallery Builder' })).toBeInTheDocument();
    expect(screen.getByText('Evento atual: 42')).toBeInTheDocument();
    expect(screen.getByText('Contrato congelado')).toBeInTheDocument();
    expect(screen.getByText(/wedding \/ romantic \/ story/i)).toBeInTheDocument();
    expect(screen.getByText(/P75 mobile\/desktop/i)).toBeInTheDocument();
  });
});
