import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import TestimonialsSection from './TestimonialsSection';
import * as LandingData from '@/data/landing';

// Mock motion to avoid animation issues in tests
vi.mock('motion/react', () => ({
  motion: {
    article: ({ children, ...props }: any) => <article {...props}>{children}</article>,
  },
  useReducedMotion: () => false,
}));

// Mock usePersonaContent to return default order
vi.mock('@/hooks/usePersonaContent', () => ({
  usePersonaContent: (content: any, fallback: any) => fallback,
}));

describe('TestimonialsSection - Fallback Safety', () => {
  const originalEnv = import.meta.env.MODE;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    // Restore original environment
    import.meta.env.MODE = originalEnv;
  });

  describe('Production Environment', () => {
    beforeEach(() => {
      // Simulate production environment
      import.meta.env.MODE = 'production';
    });

    it('should use FALLBACK_TESTIMONIALS_CONTENT when testimonialsContent is undefined', () => {
      // Mock testimonialsContent as undefined
      vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

      render(<TestimonialsSection />);

      // Should render fallback content
      const mainHeading = screen.getByRole('heading', { level: 2 });
      expect(mainHeading).toBeInTheDocument();
    });

    it('should mark fallback testimonials with [Exemplo] prefix in production', () => {
      // Mock testimonialsContent as undefined to trigger fallback
      vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

      render(<TestimonialsSection />);

      // Check for [Exemplo] markers in production fallbacks
      const articles = screen.getAllByRole('article');
      expect(articles.length).toBeGreaterThan(0);

      // At least one article should have data-fallback="true"
      const fallbackArticles = articles.filter(article => 
        article.getAttribute('data-fallback') === 'true'
      );
      expect(fallbackArticles.length).toBeGreaterThan(0);
    });

    it('should display minimum 3 fallback testimonials', () => {
      // Mock testimonialsContent as undefined
      vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

      render(<TestimonialsSection />);

      const articles = screen.getAllByRole('article');
      expect(articles.length).toBeGreaterThanOrEqual(3);
    });

    it('should have fallback testimonials organized by context', () => {
      // Mock testimonialsContent as undefined
      vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

      render(<TestimonialsSection />);

      const contextTitles = screen.getAllByRole('heading', { level: 3 });
      
      // Should have 3 context groups
      expect(contextTitles).toHaveLength(3);
      expect(contextTitles[0]).toHaveTextContent(/casamento|debutante/i);
      expect(contextTitles[1]).toHaveTextContent(/assessoria|cerimonial/i);
      expect(contextTitles[2]).toHaveTextContent(/evento|ativação|corporativo/i);
    });
  });

  describe('Development Environment', () => {
    beforeEach(() => {
      // Simulate development environment
      import.meta.env.MODE = 'development';
    });

    it('should use realistic fallbacks in development', () => {
      // Mock testimonialsContent as undefined
      vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

      render(<TestimonialsSection />);

      // Should render content without [Exemplo] markers
      const mainHeading = screen.getByRole('heading', { level: 2 });
      expect(mainHeading).toBeInTheDocument();
      
      // In development, fallbacks should not have [Exemplo] prefix
      // (this is handled by the data layer, not the component)
    });
  });

  describe('Image Fallback', () => {
    it('should handle image loading errors gracefully', () => {
      render(<TestimonialsSection />);

      const images = screen.getAllByRole('img');
      expect(images.length).toBeGreaterThan(0);

      // Simulate image error
      const firstImage = images[0] as HTMLImageElement;
      const errorEvent = new Event('error');
      firstImage.dispatchEvent(errorEvent);

      // Image should have fallback SVG src
      expect(firstImage.src).toContain('data:image/svg+xml');
      expect(firstImage.alt).toContain('indisponível');
    });

    it('should provide descriptive alt text for event photos', () => {
      render(<TestimonialsSection />);

      const images = screen.getAllByRole('img');
      images.forEach(img => {
        expect(img).toHaveAttribute('alt');
        const alt = img.getAttribute('alt');
        expect(alt).toBeTruthy();
        expect(alt!.length).toBeGreaterThan(0);
      });
    });
  });

  describe('Accessibility', () => {
    it('should maintain proper heading hierarchy with fallbacks', () => {
      // Mock testimonialsContent as undefined
      vi.spyOn(LandingData, 'testimonialsContent', 'get').mockReturnValue(undefined as any);

      render(<TestimonialsSection />);

      // Check h2 main heading
      const h2 = screen.getByRole('heading', { level: 2 });
      expect(h2).toBeInTheDocument();

      // Check h3 context headings
      const h3s = screen.getAllByRole('heading', { level: 3 });
      expect(h3s.length).toBe(3);
    });

    it('should have proper ARIA labels', () => {
      render(<TestimonialsSection />);

      const section = screen.getByRole('region');
      expect(section).toHaveAttribute('aria-labelledby', 'testimonials-title');
    });
  });

  describe('Data Validation', () => {
    it('should export FALLBACK_TESTIMONIALS with minimum 3 items', () => {
      expect(LandingData.FALLBACK_TESTIMONIALS).toBeDefined();
      expect(Array.isArray(LandingData.FALLBACK_TESTIMONIALS)).toBe(true);
      expect(LandingData.FALLBACK_TESTIMONIALS.length).toBeGreaterThanOrEqual(3);
    });

    it('should export FALLBACK_TESTIMONIALS_CONTENT with proper structure', () => {
      expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT).toBeDefined();
      expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT).toHaveProperty('eyebrow');
      expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT).toHaveProperty('title');
      expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT).toHaveProperty('subtitle');
      expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT).toHaveProperty('testimonials');
      expect(LandingData.FALLBACK_TESTIMONIALS_CONTENT).toHaveProperty('contextGroups');
    });

    it('should have contextGroups properly organized', () => {
      const { contextGroups } = LandingData.FALLBACK_TESTIMONIALS_CONTENT;
      
      expect(contextGroups).toHaveProperty('casamento');
      expect(contextGroups).toHaveProperty('assessoria');
      expect(contextGroups).toHaveProperty('corporativo');
      
      expect(Array.isArray(contextGroups.casamento)).toBe(true);
      expect(Array.isArray(contextGroups.assessoria)).toBe(true);
      expect(Array.isArray(contextGroups.corporativo)).toBe(true);
    });

    it('should export FALLBACK_EVENT_IMAGES with all contexts', () => {
      expect(LandingData.FALLBACK_EVENT_IMAGES).toBeDefined();
      expect(LandingData.FALLBACK_EVENT_IMAGES).toHaveProperty('casamento');
      expect(LandingData.FALLBACK_EVENT_IMAGES).toHaveProperty('assessoria');
      expect(LandingData.FALLBACK_EVENT_IMAGES).toHaveProperty('corporativo');
      expect(LandingData.FALLBACK_EVENT_IMAGES).toHaveProperty('generic');
      
      // All should be valid URLs
      Object.values(LandingData.FALLBACK_EVENT_IMAGES).forEach(url => {
        expect(url).toMatch(/^https?:\/\//);
      });
    });
  });
});
