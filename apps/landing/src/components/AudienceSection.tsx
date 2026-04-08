import { motion, useReducedMotion } from "motion/react";
import { ShieldCheck, Heart, Building2 } from "lucide-react";
import styles from "./AudienceSection.module.scss";
import { audienceContent } from "@/data/landing";

const iconMap = {
  "shield-check": ShieldCheck,
  "heart": Heart,
  "building-2": Building2,
};

export default function AudienceSection() {
  const reduceMotion = useReducedMotion();

  return (
    <section className={`section-shell ${styles.section}`} id="para-quem-e" aria-labelledby="audience-title">
      <div className="container">
        <header className={styles.header}>
          <span className="eyebrow">{audienceContent.eyebrow}</span>
          <h2 id="audience-title" className="section-title">{audienceContent.title}</h2>
          <p className={styles.subtitle}>{audienceContent.subtitle}</p>
        </header>

        <div className={styles.cardsGrid}>
          {audienceContent.profiles.map((profile, index) => {
            const Icon = iconMap[profile.icon as keyof typeof iconMap];
            
            return (
              <motion.article
                key={profile.id}
                className={styles.card}
                initial={{ opacity: 0, y: reduceMotion ? 0 : 24 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: "-100px" }}
                transition={{ duration: 0.5, delay: reduceMotion ? 0 : index * 0.1 }}
              >
                <div className={styles.cardHeader}>
                  <div className={styles.iconWrapper} aria-hidden="true">
                    <Icon className={styles.icon} />
                  </div>
                  <h3 className={styles.cardTitle}>{profile.name}</h3>
                </div>

                <div className={styles.cardContent}>
                  <div className={styles.promiseBlock}>
                    <span className={styles.label}>Promessa principal</span>
                    <p className={styles.promise}>{profile.promise}</p>
                  </div>

                  <div className={styles.modulesBlock}>
                    <span className={styles.label}>Módulos prioritários</span>
                    <ul className={styles.modulesList}>
                      {profile.priorityModules.map((module, idx) => (
                        <li key={idx}>{module}</li>
                      ))}
                    </ul>
                  </div>

                  <div className={styles.objectionsBlock}>
                    <span className={styles.label}>Objeções principais</span>
                    <dl className={styles.objectionsList}>
                      {profile.objections.map((objection, idx) => (
                        <div key={idx} className={styles.objectionItem}>
                          <dt className={styles.question}>{objection.question}</dt>
                          <dd className={styles.answer}>{objection.answer}</dd>
                        </div>
                      ))}
                    </dl>
                  </div>
                </div>

                <div className={styles.cardFooter}>
                  <a 
                    href={profile.cta.url} 
                    className={styles.ctaButton}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    {profile.cta.text}
                  </a>
                </div>
              </motion.article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
