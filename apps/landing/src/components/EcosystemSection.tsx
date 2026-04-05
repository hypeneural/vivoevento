import { motion, useReducedMotion } from "motion/react";
import {
  Gamepad2,
  Images,
  MonitorPlay,
  ScanFace,
  ShieldCheck,
} from "lucide-react";
import styles from "./EcosystemSection.module.scss";
import { ecosystemModules, eventImages } from "@/data/landing";

const iconMap = {
  gallery: Images,
  games: Gamepad2,
  wall: MonitorPlay,
  moderation: ShieldCheck,
  face: ScanFace,
};

function ModulePreview({ id }: { id: string }) {
  if (id === "gallery") {
    return (
      <div className={styles.galleryPreview}>
        <img src={eventImages.galleryFeatured.src} alt={eventImages.galleryFeatured.alt} />
        <img src={eventImages.gallerySecondary.src} alt={eventImages.gallerySecondary.alt} />
        <img src={eventImages.galleryThird.src} alt={eventImages.galleryThird.alt} />
        <span>recém-chegada</span>
      </div>
    );
  }

  if (id === "games") {
    return (
      <div className={styles.gamesPreview}>
        <div className={styles.gamesBoard}>
          {Array.from({ length: 6 }).map((_, index) => (
            <span key={index} />
          ))}
        </div>
        <div className={styles.previewMetric}>
          <strong>Mesa 7</strong>
          <span>ranking ao vivo</span>
        </div>
      </div>
    );
  }

  if (id === "wall") {
    return (
      <div className={styles.wallPreview}>
        <img src={eventImages.wallHero.src} alt={eventImages.wallHero.alt} />
        <div className={styles.wallTags}>
          <span>fullscreen</span>
          <span>split</span>
          <span>cinematic</span>
        </div>
      </div>
    );
  }

  if (id === "moderation") {
    return (
      <div className={styles.moderationPreview}>
        <span className={styles.approved}>Aprovado</span>
        <span className={styles.blocked}>Bloqueado</span>
        <span className={styles.review}>Revisão manual</span>
      </div>
    );
  }

  return (
    <div className={styles.facePreview}>
      <img src={eventImages.faceSelfie.src} alt={eventImages.faceSelfie.alt} />
      <div className={styles.faceMatches}>
        <img src={eventImages.faceMatchOne.src} alt={eventImages.faceMatchOne.alt} />
        <img src={eventImages.faceMatchTwo.src} alt={eventImages.faceMatchTwo.alt} />
        <span>98.4% match</span>
      </div>
    </div>
  );
}

export default function EcosystemSection() {
  const reduceMotion = useReducedMotion();

  return (
    <section id="produto" className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <div>
            <span className="eyebrow">O ecossistema de experiências</span>
            <h2 className="section-title">A plataforma nao termina no telao. Ela orquestra tudo ao mesmo tempo.</h2>
          </div>
          <p className="section-lead">
            Cada card abaixo já parece um módulo real do produto, porque o visitante precisa enxergar
            galeria, jogos, telão, IA e busca facial como partes de um mesmo sistema premium.
          </p>
        </div>

        <div className={styles.grid}>
          {ecosystemModules.map((module, index) => {
            const Icon = iconMap[module.id as keyof typeof iconMap];

            return (
              <motion.article
                key={module.id}
                className={`${styles.card} ${styles[module.accent]} ${index < 2 ? styles.large : ""}`}
                initial={{ opacity: 0, y: reduceMotion ? 0 : 24 }}
                whileInView={{ opacity: 1, y: 0 }}
                whileHover={reduceMotion ? undefined : { y: -6 }}
                transition={{ duration: 0.55, delay: index * 0.05 }}
                viewport={{ once: true, amount: 0.28 }}
              >
                <div className={styles.cardTop}>
                  <div className={styles.cardTag}>
                    <Icon size={16} />
                    {module.eyebrow}
                  </div>
                  <ModulePreview id={module.id} />
                </div>

                <div className={styles.cardBody}>
                  <h3>{module.title}</h3>
                  <p>{module.benefit}</p>
                  <small>{module.detail}</small>
                </div>
              </motion.article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
