import { useState, useEffect } from 'react';

/**
 * Hook para tracking de progresso de scroll
 * 
 * @returns Progresso de scroll em porcentagem (0-100)
 * 
 * @example
 * const progress = useScrollProgress();
 * // progress = 45.2 (45.2% da página foi rolada)
 */
export function useScrollProgress(): number {
  const [progress, setProgress] = useState<number>(0);
  
  useEffect(() => {
    const handleScroll = () => {
      const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
      const scrolled = window.scrollY;
      
      if (scrollHeight > 0) {
        const scrollPercent = (scrolled / scrollHeight) * 100;
        setProgress(Math.min(100, Math.max(0, scrollPercent)));
      } else {
        setProgress(0);
      }
    };
    
    // Executar imediatamente para estado inicial
    handleScroll();
    
    // Listener com passive para melhor performance
    window.addEventListener('scroll', handleScroll, { passive: true });
    
    return () => {
      window.removeEventListener('scroll', handleScroll);
    };
  }, []);
  
  return progress;
}
