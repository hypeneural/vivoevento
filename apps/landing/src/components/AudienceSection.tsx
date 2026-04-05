import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import styles from "./AudienceSection.module.scss";
import { audienceProfiles } from "@/data/landing";

export default function AudienceSection() {
  const [activeId, setActiveId] = useState(audienceProfiles[0].id);
  const reduceMotion = useReducedMotion();
  const activeProfile = audienceProfiles.find((profile) => profile.id === activeId) || audienceProfiles[0];

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <span className="eyebrow">Perfis de cliente</span>
          <h2 className="section-title">Cada tipo de cliente enxerga valor em um modulo diferente.</h2>
        </div>

        <div className={styles.tabs}>
          {audienceProfiles.map((profile) => (
            <button key={profile.id} type="button" className={profile.id === activeId ? styles.activeTab : ""} onClick={() => setActiveId(profile.id)}>
              {profile.id === activeId && !reduceMotion ? <motion.span layoutId="audience-pill" className={styles.tabHighlight} /> : null}
              <span>{profile.label}</span>
            </button>
          ))}
        </div>

        <div className={styles.panelShell}>
          <AnimatePresence mode="wait">
            <motion.article
              key={activeProfile.id}
              className={styles.panel}
              initial={{ opacity: 0, y: reduceMotion ? 0 : 16 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: reduceMotion ? 0 : -16 }}
              transition={{ duration: 0.24 }}
            >
              <div>
                <small>Dor principal</small>
                <strong>{activeProfile.pain}</strong>
              </div>
              <div>
                <small>Benefício percebido</small>
                <strong>{activeProfile.value}</strong>
              </div>
              <div>
                <small>Módulo mais relevante</small>
                <strong>{activeProfile.module}</strong>
              </div>
            </motion.article>
          </AnimatePresence>
        </div>
      </div>
    </section>
  );
}
