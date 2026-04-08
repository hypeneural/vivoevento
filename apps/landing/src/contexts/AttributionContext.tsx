import { createContext, useContext, useState, ReactNode } from 'react';

export type UTMParams = {
  utm_source: string;
  utm_medium: string;
  utm_campaign: string;
  utm_content: string;
  utm_term: string;
};

type AttributionContextValue = {
  utmParams: UTMParams;
};

const AttributionContext = createContext<AttributionContextValue | undefined>(undefined);

function captureUTMParams(): UTMParams {
  const params = new URLSearchParams(window.location.search);
  
  return {
    utm_source: params.get('utm_source') || '',
    utm_medium: params.get('utm_medium') || '',
    utm_campaign: params.get('utm_campaign') || '',
    utm_content: params.get('utm_content') || '',
    utm_term: params.get('utm_term') || '',
  };
}

export function AttributionProvider({ children }: { children: ReactNode }) {
  // Capturar parâmetros UTM na inicialização (estático após load)
  const [utmParams] = useState<UTMParams>(() => captureUTMParams());
  
  const value: AttributionContextValue = {
    utmParams,
  };
  
  return (
    <AttributionContext.Provider value={value}>
      {children}
    </AttributionContext.Provider>
  );
}

export function useAttribution() {
  const context = useContext(AttributionContext);
  if (!context) {
    throw new Error('useAttribution must be used within AttributionProvider');
  }
  return context;
}
