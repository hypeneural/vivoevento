/**
 * Tests for video fallback utilities
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { 
  generateVideoPlaceholder, 
  handleVideoError,
  VideoWithFallback 
} from './videoFallback';

describe('videoFallback', () => {
  describe('generateVideoPlaceholder', () => {
    it('generates SVG placeholder with default dimensions', () => {
      const placeholder = generateVideoPlaceholder();
      
      expect(placeholder).toContain('data:image/svg+xml');
      // Decode to check actual content
      const decoded = decodeURIComponent(placeholder.replace('data:image/svg+xml,', ''));
      expect(decoded).toContain('width="1280"');
      expect(decoded).toContain('height="720"');
      expect(decoded).toContain('Vídeo indisponível');
    });

    it('generates SVG placeholder with custom dimensions', () => {
      const placeholder = generateVideoPlaceholder(1920, 1080, 'Custom text');
      
      // Decode to check actual content
      const decoded = decodeURIComponent(placeholder.replace('data:image/svg+xml,', ''));
      expect(decoded).toContain('width="1920"');
      expect(decoded).toContain('height="1080"');
      expect(decoded).toContain('Custom text');
    });

    it('encodes SVG properly for data URI', () => {
      const placeholder = generateVideoPlaceholder();
      
      // Should be a valid data URI
      expect(placeholder).toMatch(/^data:image\/svg\+xml,/);
      
      // Should not contain unencoded special characters
      expect(placeholder).not.toContain('<svg');
      expect(placeholder).toContain('%3Csvg');
    });
  });

  describe('handleVideoError', () => {
    it('does nothing if video has poster', () => {
      const video = document.createElement('video');
      video.poster = 'https://example.com/poster.jpg';
      const consoleSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      
      const event = { currentTarget: video } as React.SyntheticEvent<HTMLVideoElement, Event>;
      handleVideoError(event);
      
      expect(video.style.display).not.toBe('none');
      expect(consoleSpy).toHaveBeenCalledWith(
        'Video failed to load, showing poster:',
        video.src
      );
      
      consoleSpy.mockRestore();
    });

    it('creates fallback element if video has no poster', () => {
      const container = document.createElement('div');
      const video = document.createElement('video');
      container.appendChild(video);
      
      const event = { currentTarget: video } as React.SyntheticEvent<HTMLVideoElement, Event>;
      handleVideoError(event);
      
      expect(video.style.display).toBe('none');
      expect(container.querySelector('.video-fallback')).toBeTruthy();
      expect(container.textContent).toContain('Vídeo indisponível');
    });
  });

  describe('VideoWithFallback', () => {
    it('renders video element with correct props', () => {
      render(
        <VideoWithFallback 
          src="https://example.com/video.mp4"
          poster="https://example.com/poster.jpg"
          data-testid="test-video"
        />
      );
      
      const video = screen.getByTestId('test-video');
      expect(video).toBeInTheDocument();
      expect(video).toHaveAttribute('src', 'https://example.com/video.mp4');
      expect(video).toHaveAttribute('poster', 'https://example.com/poster.jpg');
    });

    it('shows poster when video fails to load', () => {
      render(
        <VideoWithFallback 
          src="https://example.com/invalid.mp4"
          poster="https://example.com/poster.jpg"
          fallbackText="Test fallback"
          data-testid="test-video"
        />
      );
      
      const video = screen.getByTestId('test-video');
      
      // Simulate error
      fireEvent.error(video);
      
      // Should show poster as fallback
      const img = screen.getByAltText('Test fallback');
      expect(img).toBeInTheDocument();
      expect(img).toHaveAttribute('src', 'https://example.com/poster.jpg');
    });

    it('shows fallback text when video fails and no poster', () => {
      render(
        <VideoWithFallback 
          src="https://example.com/invalid.mp4"
          fallbackText="Custom fallback message"
          data-testid="test-video"
        />
      );
      
      const video = screen.getByTestId('test-video');
      
      // Simulate error
      fireEvent.error(video);
      
      // Should show fallback text
      expect(screen.getByText('Custom fallback message')).toBeInTheDocument();
    });

    it('calls custom onError handler', () => {
      const onError = vi.fn();
      
      render(
        <VideoWithFallback 
          src="https://example.com/invalid.mp4"
          onError={onError}
          data-testid="test-video"
        />
      );
      
      const video = screen.getByTestId('test-video');
      fireEvent.error(video);
      
      expect(onError).toHaveBeenCalled();
    });
  });
});
