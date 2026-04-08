/**
 * Tests for usePersonaCTAs hook
 * Validates persona-specific CTA behavior and UTM propagation
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook } from '@testing-library/react';
import { usePersonaCTAs, useWhatsAppCTA, useSchedulingCTA } from './usePersonaCTAs';
import { PersonaProvider } from '../contexts/PersonaContext';
import { AttributionProvider } from '../contexts/AttributionContext';
import { ReactNode } from 'react';

// Mock window.location
const mockLocation = {
  href: 'https://eventovivo.com/',
  search: '',
};

Object.defineProperty(window, 'location', {
  value: mockLocation,
  writable: true,
});

// Mock localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {};
  return {
    getItem: (key: string) => store[key] || null,
    setItem: (key: string, value: string) => {
      store[key] = value;
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

// Wrapper with providers
function createWrapper(search: string = '') {
  mockLocation.search = search;
  
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <PersonaProvider>
      <AttributionProvider>
        {children}
      </AttributionProvider>
    </PersonaProvider>
  );
  
  return Wrapper;
}

describe('usePersonaCTAs', () => {
  beforeEach(() => {
    localStorageMock.clear();
    mockLocation.search = '';
  });
  
  describe('Default behavior (no persona)', () => {
    it('should return default CTAs when no persona is selected', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper(),
      });
      
      expect(result.current.primary.text).toBe('Agendar demonstração');
      expect(result.current.primary.url).toContain('https://eventovivo.com/agendar');
      expect(result.current.secondary.text).toBe('Falar no WhatsApp');
      expect(result.current.secondary.url).toContain('https://wa.me/');
    });
  });
  
  describe('Assessora persona', () => {
    it('should prioritize "Agendar demonstração" as primary CTA', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=assessora'),
      });
      
      expect(result.current.primary.text).toBe('Agendar demonstração');
      expect(result.current.primary.url).toContain('tipo=assessora');
      expect(result.current.secondary.text).toBe('Falar no WhatsApp');
    });
    
    it('should include assessora-specific WhatsApp message', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=assessora'),
      });
      
      expect(result.current.secondary.url).toContain('assessora');
      expect(result.current.secondary.url).toContain('modera%C3%A7%C3%A3o'); // "moderação" encoded
    });
  });
  
  describe('Social persona', () => {
    it('should prioritize "Falar no WhatsApp" as primary CTA', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=social'),
      });
      
      expect(result.current.primary.text).toBe('Falar no WhatsApp');
      expect(result.current.primary.url).toContain('https://wa.me/');
      expect(result.current.secondary.text).toBe('Agendar demonstração');
    });
    
    it('should include social-specific WhatsApp message', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=social'),
      });
      
      expect(result.current.primary.url).toContain('galeria');
      expect(result.current.primary.url).toContain('jogos');
    });
  });
  
  describe('Corporativo persona', () => {
    it('should prioritize "Agendar demonstração" as primary CTA', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=corporativo'),
      });
      
      expect(result.current.primary.text).toBe('Agendar demonstração');
      expect(result.current.primary.url).toContain('tipo=corporativo');
      expect(result.current.secondary.text).toBe('Falar no WhatsApp');
    });
    
    it('should include corporativo-specific WhatsApp message', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=corporativo'),
      });
      
      expect(result.current.secondary.url).toContain('produtor');
      expect(result.current.secondary.url).toContain('alto%20volume'); // "alto volume" encoded
    });
  });
  
  describe('UTM parameter propagation', () => {
    it('should propagate UTM params to scheduling URL', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=assessora&utm_source=google&utm_medium=cpc&utm_campaign=casamentos'),
      });
      
      expect(result.current.primary.url).toContain('utm_source=google');
      expect(result.current.primary.url).toContain('utm_medium=cpc');
      expect(result.current.primary.url).toContain('utm_campaign=casamentos');
    });
    
    it('should propagate UTM params to WhatsApp URL as fragment', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=social&utm_source=facebook&utm_campaign=noivas'),
      });
      
      expect(result.current.primary.url).toContain('#utm_source=facebook');
      expect(result.current.primary.url).toContain('utm_campaign=noivas');
    });
    
    it('should handle empty UTM params gracefully', () => {
      const { result } = renderHook(() => usePersonaCTAs(), {
        wrapper: createWrapper('?persona=assessora'),
      });
      
      expect(result.current.primary.url).toBeTruthy();
      expect(result.current.secondary.url).toBeTruthy();
    });
  });
});

describe('useWhatsAppCTA', () => {
  beforeEach(() => {
    localStorageMock.clear();
    mockLocation.search = '';
  });
  
  it('should return WhatsApp URL with persona-specific message', () => {
    const { result } = renderHook(() => useWhatsAppCTA(), {
      wrapper: createWrapper('?persona=assessora'),
    });
    
    expect(result.current).toContain('https://wa.me/');
    expect(result.current).toContain('assessora');
  });
  
  it('should accept custom message override', () => {
    const customMessage = 'Mensagem personalizada para teste';
    const { result } = renderHook(() => useWhatsAppCTA(customMessage), {
      wrapper: createWrapper('?persona=social'),
    });
    
    expect(result.current).toContain(encodeURIComponent(customMessage));
  });
  
  it('should include UTM params as fragment', () => {
    const { result } = renderHook(() => useWhatsAppCTA(), {
      wrapper: createWrapper('?persona=corporativo&utm_source=linkedin&utm_campaign=b2b'),
    });
    
    expect(result.current).toContain('#utm_source=linkedin');
    expect(result.current).toContain('utm_campaign=b2b');
  });
});

describe('useSchedulingCTA', () => {
  beforeEach(() => {
    localStorageMock.clear();
    mockLocation.search = '';
  });
  
  it('should return scheduling URL with persona type', () => {
    const { result } = renderHook(() => useSchedulingCTA(), {
      wrapper: createWrapper('?persona=assessora'),
    });
    
    expect(result.current).toContain('https://eventovivo.com/agendar');
    expect(result.current).toContain('tipo=assessora');
  });
  
  it('should return base URL when no persona', () => {
    const { result } = renderHook(() => useSchedulingCTA(), {
      wrapper: createWrapper(),
    });
    
    expect(result.current).toBe('https://eventovivo.com/agendar');
  });
  
  it('should propagate UTM params', () => {
    const { result } = renderHook(() => useSchedulingCTA(), {
      wrapper: createWrapper('?persona=corporativo&utm_source=google&utm_medium=cpc'),
    });
    
    expect(result.current).toContain('utm_source=google');
    expect(result.current).toContain('utm_medium=cpc');
  });
});
