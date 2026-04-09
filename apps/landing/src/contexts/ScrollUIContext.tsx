import { createContext, useContext, useState, useEffect, ReactNode } from 'react';

type ScrollUIContextValue = {
  activeSection: string;
  setActiveSection: (section: string) => void;
  showFloatingCTA: boolean;
  setShowFloatingCTA: (show: boolean) => void;
};

const ScrollUIContext = createContext<ScrollUIContextValue | undefined>(undefined);

const FLOATING_CTA_THRESHOLD = 45; // Aparece após 45% de scroll (mais pro meio da página)

export function ScrollUIProvider({ children }: { children: ReactNode }) {
  const [activeSection, setActiveSection] = useState<string>('hero');
  const [showFloatingCTA, setShowFloatingCTA] = useState<boolean>(false);
  
  // Tracking de scroll para CTA flutuante
  useEffect(() => {
    const handleScroll = () => {
      const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
      const scrolled = window.scrollY;
      const scrollPercent = scrollHeight > 0 ? (scrolled / scrollHeight) * 100 : 0;
      
      setShowFloatingCTA(scrollPercent > FLOATING_CTA_THRESHOLD);
    };
    
    // Executar imediatamente para estado inicial
    handleScroll();
    
    // Listener com passive para melhor performance
    window.addEventListener('scroll', handleScroll, { passive: true });
    
    return () => {
      window.removeEventListener('scroll', handleScroll);
    };
  }, []);
  
  const value: ScrollUIContextValue = {
    activeSection,
    setActiveSection,
    showFloatingCTA,
    setShowFloatingCTA,
  };
  
  return (
    <ScrollUIContext.Provider value={value}>
      {children}
    </ScrollUIContext.Provider>
  );
}

export function useScrollUI() {
  const context = useContext(ScrollUIContext);
  if (!context) {
    throw new Error('useScrollUI must be used within ScrollUIProvider');
  }
  return context;
}
