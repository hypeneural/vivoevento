import { motion, useReducedMotion } from "motion/react";
import { Quote } from "lucide-react";
import styles from "./TestimonialsSection.module.scss";
import { eventImages, testimonials } from "@/data/landing";

const testimonialVisuals = [
  eventImages.galleryFeatured,
  eventImages.wallHero,
  eventImages.faceMatchThree,
];

export default function TestimonialsSection() {
  const reduceMotion = useReducedMotion();

  return (
    <section id="depoimentos" className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <span className="eyebrow">Prova social</span>
          <h2 className="section-title">Quem usa percebe que nao e um telao bonito. E outra categoria de produto.</h2>
        </div>

        <div className={styles.grid}>
          {testimonials.map((testimonial, index) => {
            const visual = testimonialVisuals[index] ?? testimonialVisuals[0];

            return (
              <motion.article
                key={testimonial.author}
                className={styles.card}
                initial={{ opacity: 0, y: reduceMotion ? 0 : 18 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.45, delay: index * 0.06 }}
                viewport={{ once: true, amount: 0.24 }}
              >
                <div className={styles.visual}>
                  <img src={visual.src} alt={visual.alt} loading="lazy" decoding="async" />
                  <div className={styles.visualOverlay}>
                    <span>{testimonial.eventType}</span>
                    <strong>{testimonial.volume}</strong>
                  </div>
                </div>

                <div className={styles.content}>
                  <div className={styles.quoteMark}>
                    <Quote size={18} />
                  </div>

                  <p className={styles.quote}>{testimonial.quote}</p>

                  <div className={styles.meta}>
                    <strong>{testimonial.author}</strong>
                    <span>{testimonial.role}</span>
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
          })}
        </div>
      </div>
    </section>
  );
}
