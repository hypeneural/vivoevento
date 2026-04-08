import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { PersonaSelector } from './PersonaSelector';
import { PersonaProvider } from '@/contexts/PersonaContext';

// Mock localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {};

  return {
    getItem: (key: string) => store[key] || null,
    setItem: (key: string, value: string) => {
      store[key] = value.toString();
    },
    removeItem: (key: string) => {
      delete store[key];
    },
    clear: () => {
      store = {};
    },
  };
})();

Object.defineProperty(window, 'localStorage', {
  value: localStorageMock,
});

// Mock gtag
Object.defineProperty(window, 'gtag', {
  value: vi.fn(),
  writable: true,
});

describe('PersonaSelector', () => {
  beforeEach(() => {
    localStorageMock.clear();
    vi.clearAllMocks();
  });

  it('renders all three persona options', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    expect(screen.getByText(/Sou assessora\/cerimonialista/i)).toBeInTheDocument();
    expect(screen.getByText(/Sou noiva\/debutante\/família/i)).toBeInTheDocument();
    expect(screen.getByText(/Sou produtor\/promotor\/corporativo/i)).toBeInTheDocument();
  });

  it('renders section title and subtitle', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    expect(screen.getByText(/Qual é o seu perfil\?/i)).toBeInTheDocument();
    expect(screen.getByText(/Selecione para ver conteúdo mais relevante/i)).toBeInTheDocument();
  });

  it('selects a persona when clicked', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const assessoraOption = screen.getByRole('radio', { name: /Sou assessora\/cerimonialista/i });
    
    fireEvent.click(assessoraOption);

    expect(assessoraOption).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByText(/Conteúdo adaptado para sou assessora\/cerimonialista/i)).toBeInTheDocument();
  });

  it('persists selection to localStorage', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const socialOption = screen.getByRole('radio', { name: /Sou noiva\/debutante\/família/i });
    
    fireEvent.click(socialOption);

    expect(localStorageMock.getItem('ev_selected_persona')).toBe('social');
  });

  it('tracks analytics event when persona is selected', () => {
    const gtagMock = vi.fn();
    (window as any).gtag = gtagMock;

    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const corporativoOption = screen.getByRole('radio', { name: /Sou produtor\/promotor\/corporativo/i });
    
    fireEvent.click(corporativoOption);

    expect(gtagMock).toHaveBeenCalledWith('event', 'persona_selected', {
      event_category: 'engagement',
      event_label: 'corporativo',
      value: 1,
    });
  });

  it('supports keyboard navigation with arrow keys', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const firstOption = screen.getAllByRole('radio')[0];
    const secondOption = screen.getAllByRole('radio')[1];

    firstOption.focus();
    fireEvent.keyDown(firstOption, { key: 'ArrowRight' });

    expect(document.activeElement).toBe(secondOption);
  });

  it('supports keyboard selection with Enter key', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const assessoraOption = screen.getByRole('radio', { name: /Sou assessora\/cerimonialista/i });
    
    assessoraOption.focus();
    fireEvent.keyDown(assessoraOption, { key: 'Enter' });

    expect(assessoraOption).toHaveAttribute('aria-checked', 'true');
  });

  it('supports keyboard selection with Space key', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const socialOption = screen.getByRole('radio', { name: /Sou noiva\/debutante\/família/i });
    
    socialOption.focus();
    fireEvent.keyDown(socialOption, { key: ' ' });

    expect(socialOption).toHaveAttribute('aria-checked', 'true');
  });

  it('has proper ARIA attributes', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const radioGroup = screen.getByRole('radiogroup');
    expect(radioGroup).toHaveAttribute('aria-labelledby', 'persona-selector-title');

    const options = screen.getAllByRole('radio');
    options.forEach(option => {
      expect(option).toHaveAttribute('aria-checked');
      expect(option).toHaveAttribute('aria-label');
    });
  });

  it('shows confirmation message when persona is selected', () => {
    render(
      <PersonaProvider>
        <PersonaSelector />
      </PersonaProvider>
    );

    const assessoraOption = screen.getByRole('radio', { name: /Sou assessora\/cerimonialista/i });
    
    fireEvent.click(assessoraOption);

    const confirmation = screen.getByRole('status');
    expect(confirmation).toBeInTheDocument();
    expect(confirmation).toHaveAttribute('aria-live', 'polite');
  });
});
