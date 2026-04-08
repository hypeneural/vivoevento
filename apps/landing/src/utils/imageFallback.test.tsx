import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import {
  generateImagePlaceholder,
  handleImageError,
  createImageErrorHandler,
  ImageWithFallback,
} from './imageFallback';

describe('imageFallback utilities', () => {
  describe('generateImagePlaceholder', () => {
    it('should generate SVG data URI with default dimensions', () => {
      const result = generateImagePlaceholder();
      const decoded = decodeURIComponent(result);

      expect(result).toContain('data:image/svg+xml');
      expect(decoded).toContain('width="900"');
      expect(decoded).toContain('height="600"');
      expect(decoded).toContain('Imagem indisponível');
    });

    it('should generate SVG with custom dimensions', () => {
      const result = generateImagePlaceholder(1200, 800);
      const decoded = decodeURIComponent(result);

      expect(decoded).toContain('width="1200"');
      expect(decoded).toContain('height="800"');
    });

    it('should generate SVG with custom text', () => {
      const result = generateImagePlaceholder(900, 600, 'Custom text');
      const decoded = decodeURIComponent(result);

      expect(decoded).toContain('Custom text');
    });

    it('should properly encode SVG for data URI', () => {
      const result = generateImagePlaceholder();

      // Should be a valid data URI
      expect(result).toMatch(/^data:image\/svg\+xml,/);
      
      // Should be URL encoded
      expect(result).not.toContain('<');
      expect(result).not.toContain('>');
    });
  });

  describe('handleImageError', () => {
    it('should replace image src with placeholder on error', () => {
      const img = document.createElement('img');
      img.src = 'https://example.com/image.jpg';
      img.alt = 'Original alt text';

      const event = {
        currentTarget: img,
      } as React.SyntheticEvent<HTMLImageElement, Event>;

      handleImageError(event);

      expect(img.src).toContain('data:image/svg+xml');
      expect(img.alt).toBe('Imagem de evento indisponível');
    });

    it('should not create infinite loop if placeholder fails', () => {
      const img = document.createElement('img');
      img.src = 'data:image/svg+xml,placeholder';
      const originalSrc = img.src;

      const event = {
        currentTarget: img,
      } as React.SyntheticEvent<HTMLImageElement, Event>;

      handleImageError(event);

      // Should not change src if already a data URI
      expect(img.src).toBe(originalSrc);
    });
  });

  describe('createImageErrorHandler', () => {
    it('should create handler with custom dimensions', () => {
      const handler = createImageErrorHandler(1200, 800);
      const img = document.createElement('img');
      img.src = 'https://example.com/image.jpg';

      const event = {
        currentTarget: img,
      } as React.SyntheticEvent<HTMLImageElement, Event>;

      handler(event);

      const decoded = decodeURIComponent(img.src);
      expect(decoded).toContain('width="1200"');
      expect(decoded).toContain('height="800"');
    });

    it('should create handler with custom text', () => {
      const handler = createImageErrorHandler(900, 600, 'Custom error message');
      const img = document.createElement('img');
      img.src = 'https://example.com/image.jpg';

      const event = {
        currentTarget: img,
      } as React.SyntheticEvent<HTMLImageElement, Event>;

      handler(event);

      const decoded = decodeURIComponent(img.src);
      expect(decoded).toContain('Custom error message');
      expect(img.alt).toBe('Custom error message');
    });

    it('should not create infinite loop', () => {
      const handler = createImageErrorHandler();
      const img = document.createElement('img');
      img.src = 'data:image/svg+xml,placeholder';
      const originalSrc = img.src;

      const event = {
        currentTarget: img,
      } as React.SyntheticEvent<HTMLImageElement, Event>;

      handler(event);

      expect(img.src).toBe(originalSrc);
    });
  });

  describe('ImageWithFallback component', () => {
    it('should render image with provided src and alt', () => {
      render(
        <ImageWithFallback 
          src="https://example.com/image.jpg" 
          alt="Test image" 
        />
      );

      const img = screen.getByRole('img');
      expect(img).toHaveAttribute('src', 'https://example.com/image.jpg');
      expect(img).toHaveAttribute('alt', 'Test image');
    });

    it('should apply fallback on error', () => {
      render(
        <ImageWithFallback 
          src="https://example.com/broken-image.jpg" 
          alt="Test image"
          fallbackText="Custom fallback"
        />
      );

      const img = screen.getByRole('img') as HTMLImageElement;
      
      // Simulate error
      fireEvent.error(img);

      expect(img.src).toContain('data:image/svg+xml');
      expect(img.alt).toBe('Custom fallback');
    });

    it('should call custom onError handler if provided', () => {
      const customOnError = vi.fn();

      render(
        <ImageWithFallback 
          src="https://example.com/broken-image.jpg" 
          alt="Test image"
          onError={customOnError}
        />
      );

      const img = screen.getByRole('img');
      
      // Simulate error
      fireEvent.error(img);

      expect(customOnError).toHaveBeenCalledTimes(1);
    });

    it('should pass through additional props', () => {
      render(
        <ImageWithFallback 
          src="https://example.com/image.jpg" 
          alt="Test image"
          className="custom-class"
          loading="lazy"
          decoding="async"
        />
      );

      const img = screen.getByRole('img');
      expect(img).toHaveClass('custom-class');
      expect(img).toHaveAttribute('loading', 'lazy');
      expect(img).toHaveAttribute('decoding', 'async');
    });

    it('should use custom fallback dimensions', () => {
      render(
        <ImageWithFallback 
          src="https://example.com/broken-image.jpg" 
          alt="Test image"
          fallbackWidth={1200}
          fallbackHeight={800}
        />
      );

      const img = screen.getByRole('img') as HTMLImageElement;
      
      // Simulate error
      fireEvent.error(img);

      const decoded = decodeURIComponent(img.src);
      expect(decoded).toContain('width="1200"');
      expect(decoded).toContain('height="800"');
    });
  });
});
