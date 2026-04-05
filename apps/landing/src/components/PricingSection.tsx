import { ArrowUpRight, Check } from "lucide-react";
import styles from "./PricingSection.module.scss";
import { pricingPlans } from "@/data/landing";
import { siteConfig } from "@/config/site";

export default function PricingSection() {
  return (
    <section id="precos" className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <span className="eyebrow">Planos</span>
          <h2 className="section-title">Formato comercial para evento unico, operacao recorrente ou projeto enterprise.</h2>
        </div>

        <div className={styles.grid}>
          {pricingPlans.map((plan, index) => (
            <article key={plan.name} className={`${styles.card} ${index === 1 ? styles.featured : ""}`}>
              <span className={styles.badge}>{plan.badge}</span>
              <h3>{plan.name}</h3>
              <p className={styles.audience}>{plan.audience}</p>
              <strong className={styles.price}>{plan.price}</strong>
              <ul className={styles.features}>
                {plan.features.map((feature) => (
                  <li key={feature}>
                    <Check size={16} />
                    {feature}
                  </li>
                ))}
              </ul>
              <a className="button" data-variant={index === 1 ? "primary" : "secondary"} href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
                Falar sobre este plano
                <ArrowUpRight size={16} />
              </a>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
