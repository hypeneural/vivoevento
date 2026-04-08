import { useRef } from 'react';
import { howItWorksContent } from '../data/landing';
import * as LucideIcons from 'lucide-react';
import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { getGSAPConfig, DURATION } from '@/utils/motion';
import styles from './HowItWorksSection.module.scss';

gsap.registerPlugin(useGSAP, ScrollTrigger);

export function HowItWorksSection() {
  const scope = useRef<HTMLElement>(null);

  useGSAP(
    () => {
      // Header entrance animation
      gsap.from('[data-hiw-header]', getGSAPConfig({
        y: 24,
        opacity: 0,
        duration: DURATION.medium,
        scrollTrigger: {
          trigger: scope.current,
          start: 'top 72%',
        },
      }));

      // Staggered step animations
      gsap.from('[data-hiw-step]', getGSAPConfig({
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
      id="como-funciona" 
      className={styles.section} 
      aria-labelledby="how-it-works-title"
      ref={scope}
    >
      <div className={styles.container}>
        <header className={styles.header} data-hiw-header>
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
              <article key={step.id} className={styles.step} data-hiw-step>
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
