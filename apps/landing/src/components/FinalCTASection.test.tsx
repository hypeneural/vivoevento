import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import FinalCTASection from './FinalCTASection';

vi.mock('@/hooks/useSmoothScroll', () => ({
  useSmoothScroll: () => ({
    scrollToId: vi.fn(),
  }),
}));

describe('FinalCTASection', () => {
  it('renders the section with the main title', () => {
    render(<FinalCTASection />);

    expect(screen.getByRole('heading', { name: /pronto para transformar seu evento/i })).toBeInTheDocument();
  });

  it('renders the supporting subtitle', () => {
    render(<FinalCTASection />);

    expect(screen.getByText(/veja como funciona/i)).toBeInTheDocument();
  });

  it('renders exactly two macro CTA links', () => {
    render(<FinalCTASection />);

    expect(screen.getAllByRole('link')).toHaveLength(2);
  });

  it('marks the primary and secondary CTA variants', () => {
    render(<FinalCTASection />);

    const primaryCTA = screen.getByRole('link', { name: /agendar demonstra/i });
    const secondaryCTA = screen.getByRole('link', { name: /falar no whatsapp/i });

    expect(primaryCTA).toHaveAttribute('data-variant', 'primary');
    expect(secondaryCTA).toHaveAttribute('data-variant', 'secondary');
  });

  it('keeps the section accessible through aria-labelledby', () => {
    render(<FinalCTASection />);

    const section = screen.getByRole('region', { name: /pronto para transformar seu evento/i });

    expect(section).toHaveAttribute('aria-labelledby', 'final-cta-title');
  });

  it('opens the CTA links in a new tab with safe rel attributes', () => {
    render(<FinalCTASection />);

    screen.getAllByRole('link').forEach((link) => {
      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noreferrer');
      expect(link).toHaveAttribute('aria-label', expect.stringMatching(/abre em nova aba/i));
    });
  });

  it('renders decorative icons as aria-hidden', () => {
    const { container } = render(<FinalCTASection />);

    expect(container.querySelectorAll('svg[aria-hidden="true"]').length).toBeGreaterThan(0);
  });
});
