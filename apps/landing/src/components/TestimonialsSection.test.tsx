import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import TestimonialsSection from './TestimonialsSection';
import * as PersonaContentHook from '@/hooks/usePersonaContent';
import type { PersonaId } from '@/contexts/PersonaContext';

// Mock motion to avoid animation issues in tests
vi.mock('motion/react', () => ({
  motion: {
    article: ({ children, ...props }: any) => <article {...props}>{children}</article>,
  },
  useReducedMotion: () => false,
}));

// Mock usePersonaContent hook
vi.mock('@/hooks/usePersonaContent');

describe('TestimonialsSection - Persona Prioritization', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render testimonials in default order when no persona is selected', () => {
    // Mock usePersonaContent to return default fallback
    vi.mocked(PersonaContentHook.usePersonaContent).mockImplementation((content, fallback) => fallback);

    render(<TestimonialsSection />);

    const contextTitles = screen.getAllByRole('heading', { level: 3 });
    
    // Default order: casamento, assessoria, corporativo
    expect(contextTitles[0]).toHaveTextContent('Casamentos e Debutantes');
    expect(contextTitles[1]).toHaveTextContent('Assessoria e Cerimonial');
    expect(contextTitles[2]).toHaveTextContent('Eventos e Ativações');
  });

  it('should prioritize casamento testimonials for social persona', () => {
    // Mock usePersonaContent to return social persona content
    vi.mocked(PersonaContentHook.usePersonaContent).mockImplementation((content) => content.social);

    render(<TestimonialsSection />);

    const contextTitles = screen.getAllByRole('heading', { level: 3 });
    
    // Social persona order: casamento first
    expect(contextTitles[0]).toHaveTextContent('Casamentos e Debutantes');
    expect(contextTitles[1]).toHaveTextContent('Assessoria e Cerimonial');
    expect(contextTitles[2]).toHaveTextContent('Eventos e Ativações');
  });

  it('should prioritize assessoria testimonials for assessora persona', () => {
    // Mock usePersonaContent to return assessora persona content
    vi.mocked(PersonaContentHook.usePersonaContent).mockImplementation((content) => content.assessora);

    render(<TestimonialsSection />);

    const contextTitles = screen.getAllByRole('heading', { level: 3 });
    
    // Assessora persona order: assessoria first
    expect(contextTitles[0]).toHaveTextContent('Assessoria e Cerimonial');
    expect(contextTitles[1]).toHaveTextContent('Casamentos e Debutantes');
    expect(contextTitles[2]).toHaveTextContent('Eventos e Ativações');
  });

  it('should prioritize corporativo testimonials for corporativo persona', () => {
    // Mock usePersonaContent to return corporativo persona content
    vi.mocked(PersonaContentHook.usePersonaContent).mockImplementation((content) => content.corporativo);

    render(<TestimonialsSection />);

    const contextTitles = screen.getAllByRole('heading', { level: 3 });
    
    // Corporativo persona order: corporativo first
    expect(contextTitles[0]).toHaveTextContent('Eventos e Ativações');
    expect(contextTitles[1]).toHaveTextContent('Assessoria e Cerimonial');
    expect(contextTitles[2]).toHaveTextContent('Casamentos e Debutantes');
  });

  it('should render all three context groups regardless of persona', () => {
    // Mock usePersonaContent to return assessora persona content
    vi.mocked(PersonaContentHook.usePersonaContent).mockImplementation((content) => content.assessora);

    render(<TestimonialsSection />);

    const contextTitles = screen.getAllByRole('heading', { level: 3 });
    
    // All 3 groups should be present
    expect(contextTitles).toHaveLength(3);
  });

  it('should maintain accessibility structure with proper headings', () => {
    // Mock usePersonaContent to return default fallback
    vi.mocked(PersonaContentHook.usePersonaContent).mockImplementation((content, fallback) => fallback);

    render(<TestimonialsSection />);

    // Check main section heading
    const mainHeading = screen.getByRole('heading', { level: 2 });
    expect(mainHeading).toHaveTextContent('Quem usa percebe que não é um telão bonito');

    // Check section has proper aria-labelledby
    const section = screen.getByRole('region', { name: /quem usa percebe/i });
    expect(section).toBeInTheDocument();
  });

  it('should call usePersonaContent hook with correct structure', () => {
    const mockUsePersonaContent = vi.mocked(PersonaContentHook.usePersonaContent);
    mockUsePersonaContent.mockImplementation((content, fallback) => fallback);

    render(<TestimonialsSection />);

    // Verify usePersonaContent was called
    expect(mockUsePersonaContent).toHaveBeenCalledTimes(1);

    // Verify it was called with persona-specific content and fallback
    const [personaContent, fallback] = mockUsePersonaContent.mock.calls[0];
    
    // Check that persona content has all three personas
    expect(personaContent).toHaveProperty('social');
    expect(personaContent).toHaveProperty('assessora');
    expect(personaContent).toHaveProperty('corporativo');
    
    // Check that fallback is an array with 3 groups
    expect(Array.isArray(fallback)).toBe(true);
    expect(fallback).toHaveLength(3);
  });
});
