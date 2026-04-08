import { motion, useReducedMotion } from "motion/react";
import { Quote } from "lucide-react";
import styles from "./TestimonialsSection.module.scss";
import { 
  testimonialsContent, 
  FALLBACK_TESTIMONIALS_CONTENT,
  type Testimonial 
} from "@/data/landing";
import { usePersonaContent } from "@/hooks/usePersonaContent";
import { useLandingData } from "@/hooks/useLandingData";

export default function TestimonialsSection() {
  const reduceMotion = useReducedMotion();

  // Use testimonials with safe fallback
  const testimonials = useLandingData(testimonialsContent, FALLBACK_TESTIMONIALS_CONTENT);

  // Group testimonials by context
  const { casamento, assessoria, corporativo } = testimonials.contextGroups;

  // Define priority order based on persona using usePersonaContent hook
  // Prioritize relevant testimonials for each persona while maintaining all 3 groups
  const orderedGroups = usePersonaContent(
    {
      social: [
        // Social persona: prioritize casamento (weddings/social events)
        { key: 'casamento', title: 'Casamentos e Debutantes', testimonials: casamento },
        { key: 'assessoria', title: 'Assessoria e Cerimonial', testimonials: assessoria },
        { key: 'corporativo', title: 'Eventos e Ativações', testimonials: corporativo },
      ],
      assessora: [
        // Assessora persona: prioritize assessoria (event planners)
        { key: 'assessoria', title: 'Assessoria e Cerimonial', testimonials: assessoria },
        { key: 'casamento', title: 'Casamentos e Debutantes', testimonials: casamento },
        { key: 'corporativo', title: 'Eventos e Ativações', testimonials: corporativo },
      ],
      corporativo: [
        // Corporativo persona: prioritize corporativo (corporate events)
        { key: 'corporativo', title: 'Eventos e Ativações', testimonials: corporativo },
        { key: 'assessoria', title: 'Assessoria e Cerimonial', testimonials: assessoria },
        { key: 'casamento', title: 'Casamentos e Debutantes', testimonials: casamento },
      ],
    },
    // Default order when no persona is selected
    [
      { key: 'casamento', title: 'Casamentos e Debutantes', testimonials: casamento },
      { key: 'assessoria', title: 'Assessoria e Cerimonial', testimonials: assessoria },
      { key: 'corporativo', title: 'Eventos e Ativações', testimonials: corporativo },
    ]
  );

  const renderTestimonialCard = (testimonial: Testimonial, index: number) => {
    // Determine if this is a fallback testimonial (marked with [Exemplo])
    const isFallbackExample = testimonial.author.name.includes('[Exemplo]');
    
    return (
      <motion.article
        key={testimonial.id}
        className={styles.card}
        initial={{ opacity: 0, y: reduceMotion ? 0 : 18 }}
        whileInView={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45, delay: index * 0.06 }}
        viewport={{ once: true, amount: 0.24 }}
        data-fallback={isFallbackExample ? 'true' : undefined}
      >
        <div className={styles.visual}>
          <img 
            src={testimonial.event.photo} 
            alt={`Foto do evento: ${testimonial.event.type}`} 
            loading="lazy" 
            decoding="async"
            onError={(e) => {
              // Fallback visual para imagens que falharem ao carregar
              const img = e.currentTarget;
              img.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="900" height="600" viewBox="0 0 900 600"%3E%3Crect fill="%23f3f4f6" width="900" height="600"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="system-ui" font-size="18" fill="%239ca3af"%3EImagem indisponível%3C/text%3E%3C/svg%3E';
              img.alt = 'Imagem de evento indisponível';
            }}
          />
          <div className={styles.visualOverlay}>
            <span>{testimonial.event.type}</span>
            <strong>{testimonial.event.volume}</strong>
          </div>
        </div>

        <div className={styles.content}>
          <div className={styles.quoteMark}>
            <Quote size={18} aria-hidden="true" />
          </div>

          <blockquote className={styles.quote}>{testimonial.quote}</blockquote>

          <div className={styles.meta}>
            <strong>{testimonial.author.name}</strong>
            <span>{testimonial.author.role}</span>
          </div>

          <div className={styles.detailGrid}>
            <div>
              <small>O que surpreendeu</small>
              <strong>{testimonial.highlight}</strong>
            </div>
            <div>
              <small>Resultado percebido</small>
              <strong>{testimonial.result}</strong>
            </div>
          </div>
        </div>
      </motion.article>
    );
  };

  return (
    <section 
      id="depoimentos" 
      className={`section-shell ${styles.section}`}
      aria-labelledby="testimonials-title"
    >
      <div className="container">
        <header className={styles.header}>
          <span className="eyebrow">{testimonials.eyebrow}</span>
          <h2 id="testimonials-title" className="section-title">
            {testimonials.title}
          </h2>
          <p className={styles.subtitle}>{testimonials.subtitle}</p>
        </header>

        {/* Render context groups in persona-prioritized order */}
        {orderedGroups.map((group) => (
          group.testimonials.length > 0 && (
            <div key={group.key} className={styles.contextGroup}>
              <h3 className={styles.contextTitle}>{group.title}</h3>
              <div className={styles.grid}>
                {group.testimonials.map((testimonial, index) => renderTestimonialCard(testimonial, index))}
              </div>
            </div>
          )
        ))}
      </div>
    </section>
  );
}
