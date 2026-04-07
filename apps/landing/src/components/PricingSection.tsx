import { ArrowUpRight, Check } from "lucide-react";
import styles from "./PricingSection.module.scss";
import { pricingPlans } from "@/data/landing";
import { siteConfig } from "@/config/site";

const pricingSignals = [
  ["evento unico", "implantacao guiada", "jogos e galeria"],
  ["recorrencia", "padronizacao comercial", "suporte premium"],
  ["alto volume", "rollout dedicado", "arquitetura pronta"],
];

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
              <div className={styles.cardHeader}>
                <span className={styles.badge}>{plan.badge}</span>
                <div className={styles.signalRow}>
                  {pricingSignals[index].map((signal) => (
                    <span key={signal}>{signal}</span>
                  ))}
                </div>
              </div>

              <div className={styles.identity}>
                <h3>{plan.name}</h3>
                <p className={styles.audience}>{plan.audience}</p>
                <strong className={styles.price}>{plan.price}</strong>
              </div>

              <div className={styles.storyPanel}>
                <span>Como entra no comercial</span>
                <strong>{index === 0 ? "Entrega premium por evento" : index === 1 ? "Receita recorrente para parceiros" : "Projeto critico com operacao robusta"}</strong>
              </div>

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
