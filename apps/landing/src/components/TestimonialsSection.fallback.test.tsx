import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import TestimonialsSection from './TestimonialsSection';
import * as LandingData from '@/data/landing';

vi.mock('motion/react', () => ({
  motion: {
    article: ({ children, ...props }: any) => <article {...props}>{children}</article>,
  },
  useReducedMotion: () => false,
}));

describe('TestimonialsSection fallback safety', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('uses FALLBACK_TESTIMONIALS_CONTENT when the primary payload is unavailable', () => {
    vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

    render(<TestimonialsSection />);

    expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent(LandingData.FALLBACK_TESTIMONIALS_CONTENT.title);
    expect(screen.getAllByRole('article')).toHaveLength(LandingData.FALLBACK_TESTIMONIALS_CONTENT.testimonials.length * 2);
  });

  it('marks fallback testimonials in rendered cards', () => {
    vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

    render(<TestimonialsSection />);

    const fallbackCards = screen
      .getAllByRole('article')
      .filter((card) => card.getAttribute('data-fallback') === 'true');

    expect(fallbackCards.length).toBeGreaterThan(0);
  });

  it('handles image loading errors gracefully', () => {
    render(<TestimonialsSection />);

    const firstImage = screen.getAllByRole('img')[0] as HTMLImageElement;
    fireEvent.error(firstImage);

    expect(firstImage.src).toContain('data:image/svg+xml');
    expect(firstImage.alt).toContain('indispon');
  });

  it('keeps the carousel accessible with fallback data', () => {
    vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

    render(<TestimonialsSection />);

    expect(screen.getByRole('region', { name: /carrossel de depoimentos/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /depoimento anterior/i })).toBeInTheDocument();
    expect(
      screen.getByRole('button', {
        name: (name) => name.toLowerCase().includes('ximo depoimento'),
      }),
    ).toBeInTheDocument();
  });

  it('exports grouped fallback testimonials for all contexts', () => {
    expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT.contextGroups.casamento.length).toBeGreaterThan(0);
    expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT.contextGroups.assessoria.length).toBeGreaterThan(0);
    expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT.contextGroups.corporativo.length).toBeGreaterThan(0);
  });
});
