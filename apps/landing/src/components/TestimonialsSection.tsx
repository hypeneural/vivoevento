import { useRef, useEffect } from "react";
import { useReducedMotion } from "motion/react";
import { Quote, ChevronLeft, ChevronRight } from "lucide-react";
import styles from "./TestimonialsSection.module.scss";
import { 
  testimonialsContent, 
  FALLBACK_TESTIMONIALS_CONTENT,
  type Testimonial 
} from "@/data/landing";
import { useLandingData } from "@/hooks/useLandingData";

export default function TestimonialsSection() {
  const reduceMotion = useReducedMotion();
  const trackRef = useRef<HTMLDivElement>(null);
  const animRef = useRef<number | null>(null);
  const isPausedRef = useRef(false);

  // Use testimonials with safe fallback
  const testimonials = useLandingData(testimonialsContent, FALLBACK_TESTIMONIALS_CONTENT);

  // Flatten all testimonials, shuffled loosely so segments mix naturally
  const allTestimonials: Testimonial[] = (() => {
    const { casamento, assessoria, corporativo } = testimonials.contextGroups;
    const all = [...casamento, ...assessoria, ...corporativo];
    // Simple interleave: take one from each group in rotation
    const groups = [casamento, assessoria, corporativo].filter(g => g.length > 0);
    const interleaved: Testimonial[] = [];
    const maxLen = Math.max(...groups.map(g => g.length));
    for (let i = 0; i < maxLen; i++) {
      for (const group of groups) {
        if (i < group.length) {
          interleaved.push(group[i]);
        }
      }
    }
    return interleaved.length > 0 ? interleaved : all;
  })();

  // Auto-scroll carousel
  useEffect(() => {
    const track = trackRef.current;
    if (!track || reduceMotion) return;

    let scrollPos = 0;
    const speed = 0.4; // px per frame

    const animate = () => {
      if (!isPausedRef.current && track) {
        scrollPos += speed;
        // Reset when we've scrolled past half (since we duplicate items)
        const halfScroll = track.scrollWidth / 2;
        if (scrollPos >= halfScroll) {
          scrollPos = 0;
        }
        track.scrollLeft = scrollPos;
      }
      animRef.current = requestAnimationFrame(animate);
    };

    animRef.current = requestAnimationFrame(animate);

    return () => {
      if (animRef.current) {
        cancelAnimationFrame(animRef.current);
      }
    };
  }, [reduceMotion]);

  const handlePause = () => { isPausedRef.current = true; };
  const handleResume = () => { isPausedRef.current = false; };

  const scrollBy = (direction: 'left' | 'right') => {
    const track = trackRef.current;
    if (!track) return;
    const cardWidth = 380;
    const offset = direction === 'left' ? -cardWidth : cardWidth;
    track.scrollBy({ left: offset, behavior: 'smooth' });
  };

  // Duplicate testimonials for infinite scroll effect
  const displayTestimonials = [...allTestimonials, ...allTestimonials];

  const renderCard = (testimonial: Testimonial, index: number) => {
    const isFallbackExample = testimonial.author.name.includes('[Exemplo]');
    
    return (
      <article
        key={`${testimonial.id}-${index}`}
        className={styles.card}
        data-fallback={isFallbackExample ? 'true' : undefined}
      >
        {/* Event photo */}
        <div className={styles.visual}>
          <img 
            src={testimonial.event.photo} 
            alt={`Foto do evento: ${testimonial.event.type}`} 
            loading="lazy" 
            decoding="async"
            onError={(e) => {
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

        {/* Content */}
        <div className={styles.content}>
          <div className={styles.quoteMark}>
            <Quote size={16} aria-hidden="true" />
          </div>

          <blockquote className={styles.quote}>{testimonial.quote}</blockquote>

          <div className={styles.meta}>
            <strong>{testimonial.author.name}</strong>
            <span>{testimonial.author.role}</span>
          </div>

          <div className={styles.tags}>
            <span className={styles.tagHighlight}>{testimonial.highlight}</span>
            <span className={styles.tagResult}>{testimonial.result}</span>
          </div>
        </div>
      </article>
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
      </div>

      {/* Carousel track — full-width, no container constraint */}
      <div className={styles.carouselWrapper}>
        <div 
          className={styles.track}
          ref={trackRef}
          onMouseEnter={handlePause}
          onMouseLeave={handleResume}
          onTouchStart={handlePause}
          onTouchEnd={handleResume}
          role="region"
          aria-label="Carrossel de depoimentos"
        >
          {displayTestimonials.map((t, i) => renderCard(t, i))}
        </div>

        {/* Navigation arrows */}
        <div className={styles.carouselNav}>
          <button 
            type="button" 
            className={styles.navBtn} 
            onClick={() => scrollBy('left')}
            aria-label="Depoimento anterior"
          >
            <ChevronLeft size={20} />
          </button>
          <button 
            type="button" 
            className={styles.navBtn} 
            onClick={() => scrollBy('right')}
            aria-label="Próximo depoimento"
          >
            <ChevronRight size={20} />
          </button>
        </div>
      </div>
    </section>
  );
}
