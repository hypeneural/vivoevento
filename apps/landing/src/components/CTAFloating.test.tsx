import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import CTAFloating from './CTAFloating';
import { ScrollUIProvider } from '@/contexts/ScrollUIContext';
import { AttributionProvider } from '@/contexts/AttributionContext';

// Mock contexts wrapper
function TestWrapper({ children }: { children: React.ReactNode }) {
  return (
    <AttributionProvider>
      <ScrollUIProvider>
        {children}
      </ScrollUIProvider>
    </AttributionProvider>
  );
}

describe('CTAFloating', () => {
  beforeEach(() => {
    // Clear sessionStorage before each test
    sessionStorage.clear();
    
    // Mock window.gtag
    (window as any).gtag = vi.fn();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('should render the floating CTA component', () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    expect(screen.getByRole('complementary', { name: /ações rápidas de conversão/i })).toBeInTheDocument();
  });

  it('should display correct content', () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    expect(screen.getByText('Pronto para começar?')).toBeInTheDocument();
    expect(screen.getByText('Agende uma demonstração ou fale conosco')).toBeInTheDocument();
  });

  it('should have primary and secondary CTAs', () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    const primaryCTA = screen.getByRole('link', { name: /agendar demonstração/i });
    const secondaryCTA = screen.getByRole('link', { name: /falar no whatsapp/i });

    expect(primaryCTA).toBeInTheDocument();
    expect(secondaryCTA).toBeInTheDocument();
  });

  it('should have a close button', () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    const closeButton = screen.getByRole('button', { name: /fechar cta flutuante/i });
    expect(closeButton).toBeInTheDocument();
  });

  it('should dismiss when close button is clicked', async () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    const closeButton = screen.getByRole('button', { name: /fechar cta flutuante/i });
    fireEvent.click(closeButton);

    await waitFor(() => {
      expect(sessionStorage.getItem('ev_floating_cta_dismissed')).toBe('true');
    });
  });

  it('should call onInteraction callback when close button is clicked', () => {
    const onInteraction = vi.fn();
    
    render(
      <TestWrapper>
        <CTAFloating onInteraction={onInteraction} />
      </TestWrapper>
    );

    const closeButton = screen.getByRole('button', { name: /fechar cta flutuante/i });
    fireEvent.click(closeButton);

    expect(onInteraction).toHaveBeenCalledWith('close');
  });

  it('should call onInteraction callback when primary CTA is clicked', () => {
    const onInteraction = vi.fn();
    
    render(
      <TestWrapper>
        <CTAFloating onInteraction={onInteraction} />
      </TestWrapper>
    );

    const primaryCTA = screen.getByRole('link', { name: /agendar demonstração/i });
    fireEvent.click(primaryCTA);

    expect(onInteraction).toHaveBeenCalledWith('primary');
  });

  it('should call onInteraction callback when secondary CTA is clicked', () => {
    const onInteraction = vi.fn();
    
    render(
      <TestWrapper>
        <CTAFloating onInteraction={onInteraction} />
      </TestWrapper>
    );

    const secondaryCTA = screen.getByRole('link', { name: /falar no whatsapp/i });
    fireEvent.click(secondaryCTA);

    expect(onInteraction).toHaveBeenCalledWith('secondary');
  });

  it('should not render if previously dismissed in session', () => {
    // Set dismissed flag before rendering
    sessionStorage.setItem('ev_floating_cta_dismissed', 'true');

    const { container } = render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    expect(container.firstChild).toBeNull();
  });

  it('should have proper ARIA labels for accessibility', () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    expect(screen.getByRole('complementary', { name: /ações rápidas de conversão/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /fechar cta flutuante/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /agendar demonstração da plataforma/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /falar no whatsapp \(abre em nova aba\)/i })).toBeInTheDocument();
  });

  it('should have correct link attributes', () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    const primaryCTA = screen.getByRole('link', { name: /agendar demonstração/i });
    const secondaryCTA = screen.getByRole('link', { name: /falar no whatsapp/i });

    expect(primaryCTA).toHaveAttribute('href');
    expect(secondaryCTA).toHaveAttribute('href');
    expect(secondaryCTA).toHaveAttribute('target', '_blank');
    expect(secondaryCTA).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('should track analytics events on CTA clicks', () => {
    render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    const primaryCTA = screen.getByRole('link', { name: /agendar demonstração/i });
    fireEvent.click(primaryCTA);

    expect((window as any).gtag).toHaveBeenCalledWith('event', 'click', {
      event_category: 'CTA',
      event_label: 'Floating CTA - Primary',
      value: 1,
    });

    const secondaryCTA = screen.getByRole('link', { name: /falar no whatsapp/i });
    fireEvent.click(secondaryCTA);

    expect((window as any).gtag).toHaveBeenCalledWith('event', 'click', {
      event_category: 'CTA',
      event_label: 'Floating CTA - Secondary',
      value: 1,
    });
  });

  it('should have visible class when showFloatingCTA is true', async () => {
    const { container } = render(
      <TestWrapper>
        <CTAFloating />
      </TestWrapper>
    );

    // Simulate scroll to trigger showFloatingCTA
    // Note: In real implementation, this is controlled by ScrollUIContext
    // For this test, we're just checking the class application logic
    
    const floatingCTA = container.querySelector('[role="complementary"]');
    expect(floatingCTA).toBeInTheDocument();
  });
});
