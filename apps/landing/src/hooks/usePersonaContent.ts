import { usePersona, type PersonaId } from '../contexts/PersonaContext';

/**
 * Hook para retornar conteúdo adaptado para persona selecionada
 * 
 * @param content - Objeto com conteúdo por persona
 * @param fallback - Conteúdo padrão caso nenhuma persona esteja selecionada
 * @returns Conteúdo adaptado para a persona atual
 * 
 * @example
 * const heroContent = usePersonaContent(
 *   {
 *     assessora: { headline: 'Controle total...' },
 *     social: { headline: 'Emoção ao vivo...' },
 *     corporativo: { headline: 'Engajamento em escala...' }
 *   },
 *   { headline: 'Transforme fotos em experiência...' }
 * );
 */
export function usePersonaContent<T>(
  content: Record<PersonaId, T>,
  fallback: T
): T {
  const { selectedPersona, entryVariation } = usePersona();
  
  // Prioridade: selectedPersona > entryVariation > fallback
  const persona = selectedPersona || entryVariation;
  
  if (!persona) return fallback;
  
  return content[persona] || fallback;
}
