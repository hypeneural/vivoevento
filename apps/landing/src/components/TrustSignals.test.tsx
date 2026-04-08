import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import TrustSignals from './TrustSignals';
import type { TrustSignal } from '@/data/landing';

describe('TrustSignals', () => {
  const mockSignals: TrustSignal[] = [
    {
      id: 'no-app',
      icon: 'Smartphone',
      text: 'Sem app',
      detail: 'Convidados entram por QR Code e web',
    },
    {
      id: 'ai-moderation',
      icon: 'ShieldCheck',
      text: 'Moderação por IA',
    },
  ];

  it('renders all trust signals', () => {
    render(<TrustSignals signals={mockSignals} />);
    
    expect(screen.getByText('Sem app')).toBeInTheDocument();
    expect(screen.getByText('Moderação por IA')).toBeInTheDocument();
  });

  it('renders signal details when provided', () => {
    render(<TrustSignals signals={mockSignals} />);
    
    expect(screen.getByText('Convidados entram por QR Code e web')).toBeInTheDocument();
  });

  it('does not render detail when not provided', () => {
    render(<TrustSignals signals={mockSignals} />);
    
    const aiModerationSignal = screen.getByText('Moderação por IA').closest('div');
    expect(aiModerationSignal?.querySelector('span')).not.toBeInTheDocument();
  });

  it('applies compact variant when specified', () => {
    const { container } = render(<TrustSignals signals={mockSignals} variant="compact" />);
    
    const trustSignalsDiv = container.querySelector('[data-variant="compact"]');
    expect(trustSignalsDiv).toBeInTheDocument();
  });

  it('applies default variant when not specified', () => {
    const { container } = render(<TrustSignals signals={mockSignals} />);
    
    const trustSignalsDiv = container.querySelector('[data-variant="default"]');
    expect(trustSignalsDiv).toBeInTheDocument();
  });
});
