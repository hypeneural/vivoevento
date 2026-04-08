import { createContext, useContext, useState, useEffect, ReactNode } from 'react';

export type PersonaId = 'assessora' | 'social' | 'corporativo';

type PersonaContextValue = {
  selectedPersona: PersonaId | null;
  setSelectedPersona: (persona: PersonaId | null) => void;
  entryVariation: PersonaId | null;
};

const PersonaContext = createContext<PersonaContextValue | undefined>(undefined);

const STORAGE_KEY = 'ev_selected_persona';

function getPersonaFromURL(): PersonaId | null {
  const params = new URLSearchParams(window.location.search);
  const persona = params.get('persona');
  
  if (persona === 'assessora' || persona === 'social' || persona === 'corporativo') {
    return persona;
  }
  
  return null;
}

export function PersonaProvider({ children }: { children: ReactNode }) {
  // Detectar variação de entrada via URL params
  const [entryVariation] = useState<PersonaId | null>(() => getPersonaFromURL());
  
  // Persona selecionada (pode ser diferente da entrada)
  const [selectedPersona, setSelectedPersona] = useState<PersonaId | null>(() => {
    // Prioridade: URL > localStorage > null
    const urlPersona = getPersonaFromURL();
    if (urlPersona) return urlPersona;
    
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'assessora' || saved === 'social' || saved === 'corporativo') {
      return saved;
    }
    
    return null;
  });
  
  // Persistir escolha de persona em localStorage
  useEffect(() => {
    if (selectedPersona) {
      localStorage.setItem(STORAGE_KEY, selectedPersona);
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
  }, [selectedPersona]);
  
  const value: PersonaContextValue = {
    selectedPersona,
    setSelectedPersona,
    entryVariation,
  };
  
  return (
    <PersonaContext.Provider value={value}>
      {children}
    </PersonaContext.Provider>
  );
}

export function usePersona() {
  const context = useContext(PersonaContext);
  if (!context) {
    throw new Error('usePersona must be used within PersonaProvider');
  }
  return context;
}
