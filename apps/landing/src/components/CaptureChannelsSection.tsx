import { captureChannelsContent } from '../data/landing';
import * as LucideIcons from 'lucide-react';
import styles from './CaptureChannelsSection.module.scss';

export function CaptureChannelsSection() {
  return (
    <section id="canais" className={styles.section} aria-labelledby="channels-title">
      <div className={styles.container}>
        <header className={styles.header}>
          <p className={styles.eyebrow}>{captureChannelsContent.eyebrow}</p>
          <h2 id="channels-title" className={styles.title}>
            {captureChannelsContent.title}
          </h2>
          <p className={styles.subtitle}>{captureChannelsContent.subtitle}</p>
        </header>

        <div className={styles.channels}>
          {captureChannelsContent.channels.map((channel) => {
            const IconComponent = LucideIcons[channel.icon as keyof typeof LucideIcons] as React.ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
            
            return (
              <article key={channel.id} className={styles.channel}>
                <div className={styles.channelIcon} aria-hidden="true">
                  {IconComponent && <IconComponent className={styles.icon} aria-hidden="true" />}
                </div>

                <div className={styles.channelContent}>
                  <h3 className={styles.channelName}>{channel.name}</h3>
                  <p className={styles.channelDescription}>{channel.description}</p>

                  <div className={styles.channelFeatures}>
                    <h4 className={styles.featuresTitle}>Recursos:</h4>
                    <ul className={styles.featuresList}>
                      {channel.features.map((feature, index) => (
                        <li key={index} className={styles.feature}>
                          <LucideIcons.Check className={styles.checkIcon} aria-hidden="true" />
                          <span>{feature}</span>
                        </li>
                      ))}
                    </ul>
                  </div>

                  <div className={styles.channelUseCases}>
                    <h4 className={styles.useCasesTitle}>Ideal para:</h4>
                    <div className={styles.useCasesList}>
                      {channel.useCases.map((useCase, index) => (
                        <span key={index} className={styles.useCase}>
                          {useCase}
                        </span>
                      ))}
                    </div>
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
