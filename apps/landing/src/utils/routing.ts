/**
 * Routing and URL utilities for landing page
 * Handles persona detection, UTM params, and CTA URL building
 */

export type PersonaId = 'assessora' | 'social' | 'corporativo';

/**
 * Extract persona from URL query parameters
 * @returns PersonaId if valid persona found, null otherwise
 */
export function getPersonaFromURL(): PersonaId | null {
  const params = new URLSearchParams(window.location.search);
  const persona = params.get('persona');
  
  if (persona === 'assessora' || persona === 'social' || persona === 'corporativo') {
    return persona;
  }
  
  return null;
}

/**
 * Set persona in URL without page reload
 * Updates browser history with new persona parameter
 */
export function setPersonaInURL(persona: PersonaId): void {
  const url = new URL(window.location.href);
  url.searchParams.set('persona', persona);
  window.history.replaceState({}, '', url.toString());
}

/**
 * Extract all UTM parameters from current URL
 * @returns Object with UTM parameters (empty strings if not present)
 */
export function getUTMParams(): Record<string, string> {
  const params = new URLSearchParams(window.location.search);
  
  return {
    utm_source: params.get('utm_source') || '',
    utm_medium: params.get('utm_medium') || '',
    utm_campaign: params.get('utm_campaign') || '',
    utm_content: params.get('utm_content') || '',
    utm_term: params.get('utm_term') || '',
  };
}

/**
 * Build CTA URL with UTM parameters propagated
 * Preserves attribution tracking across conversion flow
 * 
 * @param baseUrl - Target URL for the CTA
 * @param utmParams - UTM parameters to append
 * @returns Complete URL with UTM params
 */
export function buildCTAUrl(
  baseUrl: string,
  utmParams: Record<string, string>
): string {
  try {
    const url = new URL(baseUrl);
    
    // Add UTM params only if they have values
    Object.entries(utmParams).forEach(([key, value]) => {
      if (value) {
        url.searchParams.set(key, value);
      }
    });
    
    return url.toString();
  } catch (error) {
    // If URL parsing fails, return original baseUrl
    console.error('Failed to build CTA URL:', error);
    return baseUrl;
  }
}

/**
 * Build WhatsApp URL with pre-filled message
 * Includes UTM tracking and persona context
 * 
 * @param phoneNumber - WhatsApp number (format: 5511999999999)
 * @param message - Pre-filled message text
 * @param utmParams - UTM parameters for tracking
 * @returns WhatsApp web URL
 */
export function buildWhatsAppUrl(
  phoneNumber: string,
  message: string,
  utmParams?: Record<string, string>
): string {
  const encodedMessage = encodeURIComponent(message);
  let url = `https://wa.me/${phoneNumber}?text=${encodedMessage}`;
  
  // Add UTM tracking as URL fragment for analytics
  if (utmParams) {
    const utmString = Object.entries(utmParams)
      .filter(([_, value]) => value)
      .map(([key, value]) => `${key}=${encodeURIComponent(value)}`)
      .join('&');
    
    if (utmString) {
      url += `#${utmString}`;
    }
  }
  
  return url;
}

/**
 * Get current page URL with optional persona parameter
 * Useful for canonical tags and social sharing
 */
export function getCanonicalUrl(includePersona: boolean = false): string {
  const url = new URL(window.location.href);
  
  // Remove all query params
  url.search = '';
  
  // Add persona if requested and available
  if (includePersona) {
    const persona = getPersonaFromURL();
    if (persona) {
      url.searchParams.set('persona', persona);
    }
  }
  
  return url.toString();
}
