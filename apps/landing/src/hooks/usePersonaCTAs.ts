/**
 * Hook for persona-specific CTAs with UTM parameter propagation
 * Requirement 37: Adaptive conversion model
 */

import { useMemo } from 'react';
import { usePersona } from '../contexts/PersonaContext';
import { useAttribution } from '../contexts/AttributionContext';
import { personaCTAs, defaultCTAs, whatsAppMessages, WHATSAPP_PHONE, type CTAConfig } from '../data/landing';
import { buildCTAUrl, buildWhatsAppUrl } from '../utils/routing';

/**
 * Get persona-specific CTAs with UTM parameters propagated
 * 
 * Behavior by persona:
 * - Assessora: Primary = "Agendar demonstração" (qualified form)
 * - Social: Primary = "Falar no WhatsApp" (low friction)
 * - Corporativo: Primary = "Agendar demonstração" (multi-step form)
 * 
 * @returns CTAConfig with URLs including UTM params
 */
export function usePersonaCTAs(): CTAConfig {
  const { selectedPersona, entryVariation } = usePersona();
  const { utmParams } = useAttribution();
  
  const persona = selectedPersona || entryVariation;
  
  return useMemo(() => {
    // Get base CTA config for persona
    const baseCTAs = persona ? personaCTAs[persona] : defaultCTAs;
    
    // Build URLs with UTM params
    const primaryUrl = baseCTAs.primary.url.startsWith('https://wa.me')
      ? buildWhatsAppUrl(
          WHATSAPP_PHONE,
          persona ? whatsAppMessages[persona] : 'Olá! Quero conhecer a plataforma Evento Vivo.',
          utmParams
        )
      : buildCTAUrl(baseCTAs.primary.url, utmParams);
    
    const secondaryUrl = baseCTAs.secondary.url.startsWith('https://wa.me')
      ? buildWhatsAppUrl(
          WHATSAPP_PHONE,
          persona ? whatsAppMessages[persona] : 'Olá! Quero conhecer a plataforma Evento Vivo.',
          utmParams
        )
      : buildCTAUrl(baseCTAs.secondary.url, utmParams);
    
    return {
      primary: {
        ...baseCTAs.primary,
        url: primaryUrl,
      },
      secondary: {
        ...baseCTAs.secondary,
        url: secondaryUrl,
      },
    };
  }, [persona, utmParams]);
}

/**
 * Get WhatsApp URL with persona-specific message and UTM tracking
 * 
 * @param customMessage - Optional custom message (overrides persona default)
 * @returns WhatsApp URL with pre-filled message and UTM tracking
 */
export function useWhatsAppCTA(customMessage?: string): string {
  const { selectedPersona, entryVariation } = usePersona();
  const { utmParams } = useAttribution();
  
  const persona = selectedPersona || entryVariation;
  
  return useMemo(() => {
    const message = customMessage || (persona ? whatsAppMessages[persona] : 'Olá! Quero conhecer a plataforma Evento Vivo.');
    return buildWhatsAppUrl(WHATSAPP_PHONE, message, utmParams);
  }, [persona, customMessage, utmParams]);
}

/**
 * Get scheduling URL with persona type and UTM tracking
 * 
 * @returns Scheduling URL with persona parameter and UTM params
 */
export function useSchedulingCTA(): string {
  const { selectedPersona, entryVariation } = usePersona();
  const { utmParams } = useAttribution();
  
  const persona = selectedPersona || entryVariation;
  
  return useMemo(() => {
    const baseUrl = persona
      ? `https://eventovivo.com/agendar?tipo=${persona}`
      : 'https://eventovivo.com/agendar';
    
    return buildCTAUrl(baseUrl, utmParams);
  }, [persona, utmParams]);
}
