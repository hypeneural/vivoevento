import { useRef } from 'react';
import { ArrowUpRight, Check } from "lucide-react";
import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { getGSAPConfig, DURATION } from '@/utils/motion';
import styles from "./PricingSection.module.scss";
import { pricingPlans } from "@/data/landing";
import { siteConfig } from "@/config/site";

gsap.registerPlugin(useGSAP, ScrollTrigger);

export default function PricingSection() {
  const scope = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      // Header entrance animation
      gsap.from('[data-pricing-header]', getGSAPConfig({
        y: 24,
        opacity: 0,
        duration: DURATION.medium,
        scrollTrigger: {
          trigger: scope.current,
          start: 'top 72%',
        },
      }));

      // Staggered pricing cards
      gsap.from('[data-pricing-card]', getGSAPConfig({
        y: 32,
        opacity: 0,
        stagger: 0.12,
        duration: DURATION.slow,
        scrollTrigger: {
          trigger: scope.current,
          start: 'top 68%',
        },
      }));
    },
    { scope }
  );

  return (
    <section 
      id="precos" 
      className={`section-shell ${styles.section}`} 
      aria-labelledby="pricing-title"
      ref={scope}
    >
      <div className="container">
        <header className={styles.header} data-pricing-header>
          <span className="eyebrow">Planos</span>
          <h2 id="pricing-title" className="section-title">
            Escolha o formato ideal para seu evento
          </h2>
          <p className={styles.subtitle}>
            Evento único, operação recorrente ou projeto enterprise
          </p>
        </header>

        <div className={styles.grid} role="list">
          {pricingPlans.map((plan, index) => {
            const isFeatured = index === 1;
            
            return (
              <article 
                key={plan.name} 
                className={`${styles.card} ${isFeatured ? styles.featured : ""}`}
                role="listitem"
                aria-label={`Plano ${plan.name}`}
                data-pricing-card
              >
                {isFeatured && (
                  <div className={styles.featuredBadge} aria-label="Plano mais popular">
                    <span>{plan.badge}</span>
                  </div>
                )}

                <div className={styles.cardContent}>
                  <div className={styles.identity}>
                    <h3 className={styles.planName}>{plan.name}</h3>
                    <p className={styles.audience}>{plan.audience}</p>
                    <strong className={styles.price}>{plan.price}</strong>
                  </div>

                  <ul className={styles.features} aria-label="Recursos incluídos">
                    {plan.features.slice(0, 3).map((feature) => (
                      <li key={feature}>
                        <Check size={18} aria-hidden="true" />
                        <span>{feature}</span>
                      </li>
                    ))}
                  </ul>

                  <a 
                    className="button" 
                    data-variant={isFeatured ? "primary" : "secondary"} 
                    href={siteConfig.primaryCtaUrl} 
                    target="_blank" 
                    rel="noreferrer"
                    aria-label={`Falar sobre o plano ${plan.name}`}
                  >
                    Falar sobre este plano
                    <ArrowUpRight size={16} aria-hidden="true" />
                  </a>
                </div>
              </article>
            );
          })}
        </div>

        <p className={styles.note}>
          Todos os planos incluem galeria ao vivo, telão dinâmico e moderação configurável
        </p>
      </div>
    </section>
  );
}
