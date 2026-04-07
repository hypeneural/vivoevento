/**
 * Tests for FeaturedBadge component.
 */
import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { FeaturedBadge } from '../components/FeaturedBadge';

describe('FeaturedBadge', () => {
  it('renders badge when is_featured is true', () => {
    render(<FeaturedBadge isFeatured={true} />);
    expect(screen.getByText(/Destaque/)).toBeInTheDocument();
  });

  it('does not render when is_featured is false', () => {
    const { container } = render(<FeaturedBadge isFeatured={false} />);
    expect(container.innerHTML).toBe('');
  });

  it('renders without shimmer animation when reducedMotion is true', () => {
    render(<FeaturedBadge isFeatured={true} reducedMotion={true} />);
    const badge = screen.getByText(/Destaque/);
    expect(badge).toBeInTheDocument();
    // When reduced motion, no inline animation style
    expect(badge.style.animation).toBeFalsy();
  });

  it('renders with shimmer animation when reducedMotion is false', () => {
    render(<FeaturedBadge isFeatured={true} reducedMotion={false} />);
    const badge = screen.getByText(/Destaque/);
    expect(badge.style.animation).toContain('featured-shimmer');
  });
});
