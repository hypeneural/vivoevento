import { useEffect, useRef, useState } from 'react';
import { VIEWPORT_OBSERVER_OPTIONS } from '@/utils/motion';

/**
 * Hook for triggering animations when element enters viewport
 * 
 * **Validates: Requirements 18**
 * - Viewport entrance animations
 * - Respects prefers-reduced-motion
 * 
 * @param options - IntersectionObserver options
 * @param triggerOnce - Whether to trigger animation only once (default: true)
 * @returns Tuple of [ref, isVisible]
 * 
 * @example
 * const [ref, isVisible] = useViewportAnimation();
 * 
 * return (
 *   <div ref={ref} className={isVisible ? 'animate-in' : ''}>
 *     Content
 *   </div>
 * );
 */
export function useViewportAnimation<T extends HTMLElement = HTMLElement>(
  options: IntersectionObserverInit = VIEWPORT_OBSERVER_OPTIONS,
  triggerOnce: boolean = true
): [React.RefObject<T>, boolean] {
  const ref = useRef<T>(null);
  const [isVisible, setIsVisible] = useState(false);
  const hasTriggered = useRef(false);

  useEffect(() => {
    const element = ref.current;
    if (!element) return;

    // If already triggered and triggerOnce is true, don't observe
    if (triggerOnce && hasTriggered.current) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
          hasTriggered.current = true;
          
          // Unobserve if triggerOnce
          if (triggerOnce) {
            observer.unobserve(element);
          }
        } else if (!triggerOnce) {
          setIsVisible(false);
        }
      },
      options
    );

    observer.observe(element);

    return () => {
      observer.disconnect();
    };
  }, [options, triggerOnce]);

  return [ref, isVisible];
}
