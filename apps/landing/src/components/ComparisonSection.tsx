import { motion, useReducedMotion } from "motion/react";
import { Check, Sparkles, X } from "lucide-react";
import styles from "./ComparisonSection.module.scss";

const comparisonRows = [
  ["Exibe fotos", true, true],
  ["Cria experiências a partir das fotos", false, true],
  ["Jogos com fotos do próprio evento", false, true],
  ["Moderação por IA", false, true],
  ["Busca facial", false, true],
  ["Múltiplos layouts premium", false, true],
  ["Operação robusta em tempo real", false, true],
  ["Automação real do fluxo", false, true],
  ["Percepção de software proprietário", false, true],
];

export default function ComparisonSection() {
  const reduceMotion = useReducedMotion();

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <span className="eyebrow">Comparativo direto</span>
          <h2 className="section-title">Solucoes comuns mostram fotos. Evento Vivo cria experiencia.</h2>
        </div>

        <div className={styles.table}>
          <div className={`${styles.column} ${styles.common}`}>
            <div className={styles.columnHeader}>
              <strong>Soluções comuns</strong>
              <span>telão básico, fluxo manual e pouca diferenciação</span>
            </div>

            <div className={styles.rows}>
              {comparisonRows.map(([label, common]) => (
                <motion.div
                  key={label}
                  className={styles.row}
                  initial={{ opacity: 0, x: reduceMotion ? 0 : -18 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  transition={{ duration: 0.4 }}
                  viewport={{ once: true, amount: 0.28 }}
                >
                  <span>{label}</span>
                  <span>{common ? <Check size={18} /> : <X size={18} />}</span>
                </motion.div>
              ))}
            </div>
          </div>

          <div className={`${styles.column} ${styles.premium}`}>
            <div className={styles.columnHeader}>
              <strong>
                <Sparkles size={18} />
                Evento Vivo
              </strong>
              <span>galeria, jogos, telão, IA e busca facial no mesmo ecossistema</span>
            </div>

            <div className={styles.rows}>
              {comparisonRows.map(([label, , premium]) => (
                <motion.div
                  key={label}
                  className={styles.row}
                  initial={{ opacity: 0, x: reduceMotion ? 0 : 18 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  transition={{ duration: 0.4 }}
                  viewport={{ once: true, amount: 0.28 }}
                >
                  <span>{label}</span>
                  <span>{premium ? <Check size={18} /> : <X size={18} />}</span>
                </motion.div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
