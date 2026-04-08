import { useState, useEffect, RefObject } from 'react';

/**
 * Hook para detectar quando elemento entra no viewport
 * 
 * @param ref - Ref do elemento a observar
 * @param options - Opções do IntersectionObserver
 * @returns true se elemento está visível no viewport
 * 
 * @example
 * const sectionRef = useRef<HTMLElement>(null);
 * const isVisible = useIntersectionObserver(sectionRef, { threshold: 0.5 });
 * 
 * // isVisible = true quando 50% da seção está visível
 */
export function useIntersectionObserver(
  ref: RefObject<Element>,
  options?: IntersectionObserverInit
): boolean {
  const [isIntersecting, setIsIntersecting] = useState<boolean>(false);
  
  useEffect(() => {
    const element = ref.current;
    if (!element) return;
    
    const observer = new IntersectionObserver(
      ([entry]) => {
        setIsIntersecting(entry.isIntersecting);
      },
      {
        threshold: 0.1,
        ...options,
      }
    );
    
    observer.observe(element);
    
    return () => {
      observer.disconnect();
    };
  }, [ref, options]);
  
  return isIntersecting;
}
