import { howItWorksContent } from '../data/landing';
import * as LucideIcons from 'lucide-react';
import styles from './HowItWorksSection.module.scss';

export function HowItWorksSection() {
  return (
    <section id="como-funciona" className={styles.section} aria-labelledby="how-it-works-title">
      <div className={styles.container}>
        <header className={styles.header}>
          <p className={styles.eyebrow}>{howItWorksContent.eyebrow}</p>
          <h2 id="how-it-works-title" className={styles.title}>
            {howItWorksContent.title}
          </h2>
          <p className={styles.subtitle}>{howItWorksContent.subtitle}</p>
        </header>

        <div className={styles.steps}>
          {howItWorksContent.steps.map((step, index) => {
            const IconComponent = LucideIcons[step.icon as keyof typeof LucideIcons] as React.ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
            
            return (
              <article key={step.id} className={styles.step}>
                <div className={styles.stepNumber} aria-label={`Passo ${step.number}`}>
                  {step.number}
                </div>
                
                <div className={styles.stepIcon} aria-hidden="true">
                  {IconComponent && <IconComponent className={styles.icon} aria-hidden="true" />}
                </div>

                <div className={styles.stepContent}>
                  <h3 className={styles.stepTitle}>{step.title}</h3>
                  <p className={styles.stepDescription}>{step.description}</p>
                </div>

                {index < howItWorksContent.steps.length - 1 && (
                  <div className={styles.stepConnector} aria-hidden="true" />
                )}
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
