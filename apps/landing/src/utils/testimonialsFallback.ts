/**
 * Utility functions for testimonials fallback management
 * 
 * Este arquivo fornece funções auxiliares para gerenciar fallbacks
 * de depoimentos de forma segura e controlada.
 */

import type { TestimonialsContent, Testimonial } from '@/data/landing';

/**
 * Verifica se um depoimento é um fallback de exemplo
 * @param testimonial - Depoimento a verificar
 * @returns true se for um fallback de exemplo
 */
export function isFallbackTestimonial(testimonial: Testimonial): boolean {
  return testimonial.author.name.includes('[Exemplo]');
}

/**
 * Verifica se o conteúdo de depoimentos está usando fallbacks
 * @param content - Conteúdo de depoimentos
 * @returns true se estiver usando fallbacks
 */
export function isUsingFallbackContent(content: TestimonialsContent): boolean {
  return content.testimonials.some(isFallbackTestimonial);
}

/**
 * Conta quantos depoimentos reais existem por contexto
 * @param content - Conteúdo de depoimentos
 * @returns Objeto com contagem por contexto
 */
export function countRealTestimonialsByContext(content: TestimonialsContent): {
  casamento: number;
  assessoria: number;
  corporativo: number;
  total: number;
} {
  const real = content.testimonials.filter(t => !isFallbackTestimonial(t));
  
  return {
    casamento: real.filter(t => t.context === 'casamento').length,
    assessoria: real.filter(t => t.context === 'assessoria').length,
    corporativo: real.filter(t => t.context === 'corporativo').length,
    total: real.length,
  };
}

/**
 * Verifica se há depoimentos reais suficientes para publicação
 * @param content - Conteúdo de depoimentos
 * @param minPerContext - Mínimo de depoimentos por contexto (padrão: 1)
 * @returns true se houver depoimentos suficientes
 */
export function hasEnoughRealTestimonials(
  content: TestimonialsContent,
  minPerContext: number = 1
): boolean {
  const counts = countRealTestimonialsByContext(content);
  
  return (
    counts.casamento >= minPerContext &&
    counts.assessoria >= minPerContext &&
    counts.corporativo >= minPerContext
  );
}

/**
 * Gera relatório de status dos depoimentos
 * @param content - Conteúdo de depoimentos
 * @returns Relatório formatado
 */
export function generateTestimonialsReport(content: TestimonialsContent): string {
  const counts = countRealTestimonialsByContext(content);
  const usingFallback = isUsingFallbackContent(content);
  const ready = hasEnoughRealTestimonials(content);
  
  return `
Testimonials Status Report
==========================
Total testimonials: ${content.testimonials.length}
Real testimonials: ${counts.total}
Using fallbacks: ${usingFallback ? 'YES' : 'NO'}

By Context:
- Casamento: ${counts.casamento} real
- Assessoria: ${counts.assessoria} real
- Corporativo: ${counts.corporativo} real

Ready for production: ${ready ? 'YES ✅' : 'NO ❌'}
${!ready ? '\n⚠️  Need at least 1 real testimonial per context' : ''}
  `.trim();
}

/**
 * Filtra apenas depoimentos reais (remove fallbacks)
 * @param content - Conteúdo de depoimentos
 * @returns Conteúdo apenas com depoimentos reais
 */
export function filterRealTestimonials(content: TestimonialsContent): TestimonialsContent {
  const realTestimonials = content.testimonials.filter(t => !isFallbackTestimonial(t));
  
  return {
    ...content,
    testimonials: realTestimonials,
    contextGroups: {
      casamento: realTestimonials.filter(t => t.context === 'casamento'),
      assessoria: realTestimonials.filter(t => t.context === 'assessoria'),
      corporativo: realTestimonials.filter(t => t.context === 'corporativo'),
    },
  };
}
