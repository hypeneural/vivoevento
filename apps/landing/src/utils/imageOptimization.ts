/**
 * Image Optimization Utilities
 * 
 * Provides utilities for optimizing image loading performance:
 * - Preload critical images above the fold
 * - Generate responsive srcset
 * - Lazy loading for below-the-fold images
 * - WebP format support with fallbacks
 */

export type ImageFormat = 'webp' | 'jpg' | 'png';

export type ResponsiveImageConfig = {
  src: string;
  alt: string;
  width?: number;
  height?: number;
  sizes?: string;
  loading?: 'lazy' | 'eager';
  priority?: boolean;
  formats?: ImageFormat[];
};

/**
 * Generate srcset for responsive images
 * Creates multiple size variants for different viewport widths
 */
export function generateSrcSet(
  baseSrc: string,
  widths: number[] = [640, 750, 828, 1080, 1200, 1920, 2048, 3840]
): string {
  const extension = baseSrc.split('.').pop();
  const baseWithoutExt = baseSrc.replace(`.${extension}`, '');
  
  return widths
    .map(width => `${baseWithoutExt}-${width}w.${extension} ${width}w`)
    .join(', ');
}

/**
 * Generate sizes attribute for responsive images
 * Defines which image size to use at different viewport widths
 */
export function generateSizes(breakpoints?: Record<string, string>): string {
  if (breakpoints) {
    return Object.entries(breakpoints)
      .map(([bp, size]) => `(max-width: ${bp}) ${size}`)
      .join(', ');
  }
  
  // Default responsive sizes
  return [
    '(max-width: 640px) 100vw',
    '(max-width: 1024px) 90vw',
    '(max-width: 1536px) 80vw',
    '1200px'
  ].join(', ');
}

/**
 * Preload critical images above the fold
 * Should be called for hero images and other critical visuals
 */
export function preloadImage(src: string, options?: {
  as?: 'image';
  type?: string;
  fetchPriority?: 'high' | 'low' | 'auto';
}): void {
  if (typeof document === 'undefined') return;
  
  const link = document.createElement('link');
  link.rel = 'preload';
  link.as = options?.as || 'image';
  link.href = src;
  
  if (options?.type) {
    link.type = options.type;
  }
  
  if (options?.fetchPriority) {
    link.setAttribute('fetchpriority', options.fetchPriority);
  }
  
  document.head.appendChild(link);
}

/**
 * Preload multiple critical images
 */
export function preloadImages(images: Array<{ src: string; type?: string }>): void {
  images.forEach(({ src, type }) => {
    preloadImage(src, { type, fetchPriority: 'high' });
  });
}

/**
 * Check if WebP is supported
 */
export function supportsWebP(): Promise<boolean> {
  if (typeof document === 'undefined') {
    return Promise.resolve(false);
  }
  
  return new Promise((resolve) => {
    const webP = new Image();
    webP.onload = webP.onerror = () => {
      resolve(webP.height === 2);
    };
    webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
  });
}

/**
 * Get optimized image source with WebP fallback
 */
export function getOptimizedSrc(src: string, preferWebP: boolean = true): string {
  if (!preferWebP) return src;
  
  const extension = src.split('.').pop();
  if (extension === 'webp') return src;
  
  // Replace extension with .webp
  return src.replace(`.${extension}`, '.webp');
}

/**
 * Generate picture element sources for multiple formats
 */
export function generatePictureSources(
  src: string,
  formats: ImageFormat[] = ['webp', 'jpg']
): Array<{ srcSet: string; type: string }> {
  const extension = src.split('.').pop();
  const baseWithoutExt = src.replace(`.${extension}`, '');
  
  return formats.map(format => ({
    srcSet: `${baseWithoutExt}.${format}`,
    type: `image/${format}`
  }));
}

/**
 * Calculate aspect ratio padding for responsive images
 * Prevents layout shift by reserving space before image loads
 */
export function getAspectRatioPadding(width: number, height: number): string {
  return `${(height / width) * 100}%`;
}

/**
 * Lazy load image with Intersection Observer
 * More performant than native lazy loading for complex scenarios
 */
export function lazyLoadImage(
  img: HTMLImageElement,
  options?: IntersectionObserverInit
): () => void {
  if (typeof IntersectionObserver === 'undefined') {
    // Fallback: load immediately if IntersectionObserver not supported
    if (img.dataset.src) {
      img.src = img.dataset.src;
    }
    return () => {};
  }
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const target = entry.target as HTMLImageElement;
        if (target.dataset.src) {
          target.src = target.dataset.src;
          target.removeAttribute('data-src');
        }
        if (target.dataset.srcset) {
          target.srcset = target.dataset.srcset;
          target.removeAttribute('data-srcset');
        }
        observer.unobserve(target);
      }
    });
  }, {
    rootMargin: '50px 0px',
    threshold: 0.01,
    ...options
  });
  
  observer.observe(img);
  
  return () => observer.disconnect();
}

/**
 * Decode image before displaying to prevent jank
 */
export async function decodeImage(img: HTMLImageElement): Promise<void> {
  if ('decode' in img) {
    try {
      await img.decode();
    } catch (error) {
      // Ignore decode errors, image will still display
      console.warn('Image decode failed:', error);
    }
  }
}

/**
 * Priority hints for image loading
 */
export const ImagePriority = {
  HIGH: 'high' as const,
  LOW: 'low' as const,
  AUTO: 'auto' as const,
} as const;

/**
 * Common image sizes for landing page
 */
export const ImageSizes = {
  HERO: '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 1200px',
  SECTION_FULL: '(max-width: 640px) 100vw, (max-width: 1024px) 85vw, 1100px',
  SECTION_HALF: '(max-width: 640px) 100vw, (max-width: 1024px) 45vw, 550px',
  CARD: '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 400px',
  THUMBNAIL: '(max-width: 640px) 25vw, 150px',
} as const;

/**
 * Preload critical above-the-fold images
 * Call this in App.tsx or main component
 */
export function preloadCriticalImages(): void {
  // Hero poster + frame
  preloadImages([
    { src: '/assets/hero-phone/poster-phone.jpg', type: 'image/jpeg' },
    { src: '/assets/hero-phone/phone-frame.svg', type: 'image/svg+xml' },
  ]);
}
