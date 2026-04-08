import { useState, useEffect, useRef, ImgHTMLAttributes } from 'react';
import {
  generateSrcSet,
  generateSizes,
  getOptimizedSrc,
  decodeImage,
  ImagePriority,
} from '@/utils/imageOptimization';
import styles from './OptimizedImage.module.scss';

export type OptimizedImageProps = Omit<ImgHTMLAttributes<HTMLImageElement>, 'loading'> & {
  src: string;
  alt: string;
  width?: number;
  height?: number;
  priority?: boolean;
  sizes?: string;
  objectFit?: 'cover' | 'contain' | 'fill' | 'none' | 'scale-down';
  aspectRatio?: string;
  fallbackSrc?: string;
  onLoad?: () => void;
  onError?: () => void;
};

/**
 * OptimizedImage Component
 * 
 * Provides automatic image optimization:
 * - WebP format with fallback
 * - Responsive srcset
 * - Lazy loading for below-the-fold images
 * - Preload for critical images
 * - Aspect ratio preservation to prevent CLS
 * - Decode before display to prevent jank
 */
export default function OptimizedImage({
  src,
  alt,
  width,
  height,
  priority = false,
  sizes,
  objectFit = 'cover',
  aspectRatio,
  fallbackSrc,
  className,
  onLoad,
  onError,
  ...props
}: OptimizedImageProps) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [currentSrc, setCurrentSrc] = useState(src);
  const imgRef = useRef<HTMLImageElement>(null);
  
  // Calculate aspect ratio for CLS prevention
  const calculatedAspectRatio = aspectRatio || (width && height ? `${width} / ${height}` : undefined);
  
  useEffect(() => {
    setCurrentSrc(src);
    setHasError(false);
    setIsLoaded(false);
  }, [src]);
  
  const handleLoad = async () => {
    if (imgRef.current) {
      await decodeImage(imgRef.current);
    }
    setIsLoaded(true);
    onLoad?.();
  };
  
  const handleError = () => {
    if (fallbackSrc && currentSrc !== fallbackSrc) {
      setCurrentSrc(fallbackSrc);
    } else {
      setHasError(true);
    }
    onError?.();
  };
  
  // Generate optimized sources
  const webpSrc = getOptimizedSrc(currentSrc, true);
  const responsiveSizes = sizes || generateSizes();
  
  return (
    <div 
      className={`${styles.container} ${className || ''}`}
      style={{
        aspectRatio: calculatedAspectRatio,
      }}
    >
      <picture>
        {/* WebP source */}
        <source
          srcSet={webpSrc}
          type="image/webp"
          sizes={responsiveSizes}
        />
        
        {/* Fallback source */}
        <img
          ref={imgRef}
          src={currentSrc}
          alt={alt}
          width={width}
          height={height}
          loading={priority ? 'eager' : 'lazy'}
          decoding={priority ? 'sync' : 'async'}
          fetchPriority={priority ? ImagePriority.HIGH : ImagePriority.AUTO}
          onLoad={handleLoad}
          onError={handleError}
          className={`${styles.image} ${isLoaded ? styles.loaded : ''} ${hasError ? styles.error : ''}`}
          style={{
            objectFit,
          }}
          {...props}
        />
      </picture>
      
      {/* Loading placeholder */}
      {!isLoaded && !hasError && (
        <div className={styles.placeholder} aria-hidden="true" />
      )}
      
      {/* Error state */}
      {hasError && (
        <div className={styles.errorState} role="img" aria-label={alt}>
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <polyline points="21 15 16 10 5 21" />
          </svg>
        </div>
      )}
    </div>
  );
}
