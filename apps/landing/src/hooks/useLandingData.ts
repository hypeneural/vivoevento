/**
 * Hook para usar dados com fallback seguro
 * 
 * @param data - Dados principais (podem ser undefined)
 * @param fallback - Dados de fallback
 * @returns Dados principais ou fallback
 * 
 * @example
 * const testimonials = useLandingData(
 *   landingData.testimonials,
 *   FALLBACK_TESTIMONIALS
 * );
 * 
 * // Retorna landingData.testimonials se disponível, senão FALLBACK_TESTIMONIALS
 */
export function useLandingData<T>(
  data: T | undefined,
  fallback: T
): T {
  return data ?? fallback;
}
