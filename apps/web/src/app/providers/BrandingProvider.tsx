import { useAuth } from '@/app/providers/AuthProvider';

/**
 * BrandingProvider reads organization branding from the session
 * and injects CSS custom properties.
 */
import React, { createContext, useContext, useEffect, useMemo } from 'react';
import type { MeOrgBranding } from '@/lib/api-types';

const DEFAULT_BRANDING: MeOrgBranding = {
  primary_color: '#7c3aed',
  secondary_color: '#3b82f6',
  subdomain: null,
  custom_domain: null,
};

interface BrandingContextType {
  branding: MeOrgBranding;
  orgName: string;
  logoUrl: string | null;
  isCustomBranding: boolean;
}

const BrandingContext = createContext<BrandingContextType | undefined>(undefined);

function hexToHSL(hex: string): string {
  const r = parseInt(hex.slice(1, 3), 16) / 255;
  const g = parseInt(hex.slice(3, 5), 16) / 255;
  const b = parseInt(hex.slice(5, 7), 16) / 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h = 0, s = 0;
  const l = (max + min) / 2;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
      case g: h = ((b - r) / d + 2) / 6; break;
      case b: h = ((r - g) / d + 4) / 6; break;
    }
  }
  return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`;
}

function applyBrandingVars(branding: MeOrgBranding) {
  const root = document.documentElement;
  if (branding.primary_color) {
    root.style.setProperty('--brand-primary', hexToHSL(branding.primary_color));
  }
  if (branding.secondary_color) {
    root.style.setProperty('--brand-secondary', hexToHSL(branding.secondary_color));
  }
}

function clearBrandingVars() {
  const root = document.documentElement;
  ['--brand-primary', '--brand-secondary'].forEach(v => root.style.removeProperty(v));
}

export function BrandingProvider({ children }: { children: React.ReactNode }) {
  const { meOrganization } = useAuth();

  const branding = meOrganization?.branding ?? DEFAULT_BRANDING;
  const isCustom = !!(branding.primary_color && branding.primary_color !== DEFAULT_BRANDING.primary_color);

  useEffect(() => {
    if (isCustom) {
      applyBrandingVars(branding);
    } else {
      clearBrandingVars();
    }
    return () => clearBrandingVars();
  }, [branding, isCustom]);

  const value = useMemo<BrandingContextType>(() => ({
    branding,
    orgName: meOrganization?.name || 'Evento Vivo',
    logoUrl: meOrganization?.logo_url || null,
    isCustomBranding: isCustom,
  }), [branding, meOrganization, isCustom]);

  return (
    <BrandingContext.Provider value={value}>
      {children}
    </BrandingContext.Provider>
  );
}

export function useBranding() {
  const context = useContext(BrandingContext);
  if (!context) throw new Error('useBranding must be used within BrandingProvider');
  return context;
}
