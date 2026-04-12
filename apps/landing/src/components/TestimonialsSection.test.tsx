import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import TestimonialsSection from './TestimonialsSection';
import * as LandingDataHook from '@/hooks/useLandingData';
import { FALLBACK_TESTIMONIALS_CONTENT, testimonialsContent } from '@/data/landing';

vi.mock('motion/react', () => ({
  motion: {
    article: ({ children, ...props }: any) => <article {...props}>{children}</article>,
  },
  useReducedMotion: () => false,
}));

describe('TestimonialsSection', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('renders the section heading and carousel region', () => {
    render(<TestimonialsSection />);

    expect(
      screen.getByRole('heading', {
        level: 2,
        name: (name) => name.toLowerCase().includes('categoria'),
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('region', {
        name: (name) => name.toLowerCase().includes('categoria'),
      }),
    ).toHaveAttribute('aria-labelledby', 'testimonials-title');
    expect(screen.getByRole('region', { name: /carrossel de depoimentos/i })).toBeInTheDocument();
  });

  it('renders navigation buttons for the carousel', () => {
    render(<TestimonialsSection />);

    expect(screen.getByRole('button', { name: /depoimento anterior/i })).toBeInTheDocument();
    expect(
      screen.getByRole('button', {
        name: (name) => name.toLowerCase().includes('ximo depoimento'),
      }),
    ).toBeInTheDocument();
  });

  it('duplicates the testimonials list for the infinite carousel effect', () => {
    render(<TestimonialsSection />);

    expect(screen.getAllByRole('article')).toHaveLength(testimonialsContent.testimonials.length * 2);
  });

  it('keeps the interleaved event order before duplicating the list', () => {
    render(<TestimonialsSection />);

    const cards = screen.getAllByRole('article').slice(0, 5);

    expect(cards[0]).toHaveTextContent('Casamento');
    expect(cards[1]).toHaveTextContent('Debutante');
    expect(cards[2]).toHaveTextContent('Formatura');
    expect(cards[3]).toHaveTextContent('Casamento premium');
    expect(cards[4]).toHaveTextContent('Evento corporativo');
  });

  it('reads testimonials through useLandingData with the fallback contract', () => {
    const spy = vi.spyOn(LandingDataHook, 'useLandingData');

    render(<TestimonialsSection />);

    expect(spy).toHaveBeenCalledWith(testimonialsContent, FALLBACK_TESTIMONIALS_CONTENT);
  });
});
