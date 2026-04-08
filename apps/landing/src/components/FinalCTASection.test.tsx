import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import FinalCTASection from './FinalCTASection';

describe('FinalCTASection', () => {
  it('renders the section with correct title', () => {
    render(<FinalCTASection />);
    
    expect(screen.getByRole('heading', { name: /pronto para transformar seu evento/i })).toBeInTheDocument();
  });

  it('renders the subtitle', () => {
    render(<FinalCTASection />);
    
    expect(screen.getByText(/agende uma demonstração e veja como funciona na prática/i)).toBeInTheDocument();
  });

  it('renders exactly 2 CTA buttons', () => {
    render(<FinalCTASection />);
    
    const links = screen.getAllByRole('link');
    expect(links).toHaveLength(2);
  });

  it('renders primary CTA with correct text', () => {
    render(<FinalCTASection />);
    
    const primaryCTA = screen.getByRole('link', { name: /agendar demonstração/i });
    expect(primaryCTA).toBeInTheDocument();
    expect(primaryCTA).toHaveAttribute('data-variant', 'primary');
  });

  it('renders secondary CTA with correct text', () => {
    render(<FinalCTASection />);
    
    const secondaryCTA = screen.getByRole('link', { name: /falar no whatsapp/i });
    expect(secondaryCTA).toBeInTheDocument();
    expect(secondaryCTA).toHaveAttribute('data-variant', 'secondary');
  });

  it('has proper accessibility attributes', () => {
    render(<FinalCTASection />);
    
    const section = screen.getByRole('region', { name: /pronto para transformar seu evento/i });
    expect(section).toBeInTheDocument();
    expect(section).toHaveAttribute('aria-labelledby', 'final-cta-title');
  });

  it('opens CTAs in new tab with proper security attributes', () => {
    render(<FinalCTASection />);
    
    const links = screen.getAllByRole('link');
    
    links.forEach(link => {
      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noreferrer');
    });
  });

  it('has accessible labels for screen readers', () => {
    render(<FinalCTASection />);
    
    const primaryCTA = screen.getByRole('link', { name: /agendar demonstração - abre em nova aba/i });
    const secondaryCTA = screen.getByRole('link', { name: /falar no whatsapp - abre em nova aba/i });
    
    expect(primaryCTA).toBeInTheDocument();
    expect(secondaryCTA).toBeInTheDocument();
  });

  it('has icons marked as decorative with aria-hidden', () => {
    const { container } = render(<FinalCTASection />);
    
    const icons = container.querySelectorAll('svg[aria-hidden="true"]');
    expect(icons.length).toBeGreaterThan(0);
  });
});
