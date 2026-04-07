import { useState } from "react";
import { motion, useReducedMotion } from "motion/react";
import { Check, Sparkles, X } from "lucide-react";
import styles from "./ComparisonSection.module.scss";

type ComparisonRow = {
  id: string;
  label: string;
  commonLabel: string;
  premiumLabel: string;
  spotlight: string;
};

const comparisonRows: ComparisonRow[] = [
  {
    id: "display",
    label: "Exibe fotos",
    commonLabel: "Sim, como telao simples",
    premiumLabel: "Sim, como parte do ecossistema",
    spotlight: "Evento Vivo nao para na exibicao. A foto entra em varios modulos no mesmo fluxo.",
  },
  {
    id: "experience",
    label: "Cria experiencias a partir das fotos",
    commonLabel: "Nao cria experiencia",
    premiumLabel: "Galeria, jogo, telao e memoria",
    spotlight: "Aqui a mesma captura deixa de ser arquivo parado e vira experiencia viva para o convidado.",
  },
  {
    id: "games",
    label: "Jogos com fotos do proprio evento",
    commonLabel: "Nao tem",
    premiumLabel: "Puzzle e memoria com foto real",
    spotlight: "O diferencial comercial fica tangivel porque a foto do evento realmente vira jogo no celular.",
  },
  {
    id: "moderation",
    label: "Moderacao por IA",
    commonLabel: "Geralmente nao tem",
    premiumLabel: "Antes da exibicao",
    spotlight: "A IA entra no caminho certo: antes do telao, reduzindo risco sem travar a operacao.",
  },
  {
    id: "face",
    label: "Busca facial",
    commonLabel: "Nao tem",
    premiumLabel: "Selfie para match em segundos",
    spotlight: "A memoria do evento vira entrega premium no pos-evento, nao uma pasta longa e esquecida.",
  },
  {
    id: "layouts",
    label: "Layouts premium",
    commonLabel: "Visual limitado",
    premiumLabel: "Fullscreen, split e cinematic",
    spotlight: "O produto parece software proprietario porque cada modulo tem linguagem visual propria e premium.",
  },
  {
    id: "operation",
    label: "Operacao robusta em tempo real",
    commonLabel: "Fluxo simples e mais fragil",
    premiumLabel: "Sincronia ao vivo entre modulos",
    spotlight: "Galeria, jogo, IA e telao reagem juntos, sem depender de refresh manual.",
  },
  {
    id: "automation",
    label: "Automacao real do fluxo",
    commonLabel: "Pouca ou nenhuma",
    premiumLabel: "Captura, decisao e distribuicao",
    spotlight: "A plataforma trata captura, aprovacao e distribuicao como operacao unica, nao como etapas soltas.",
  },
];

export default function ComparisonSection() {
  const reduceMotion = useReducedMotion();
  const [activeRowId, setActiveRowId] = useState(comparisonRows[1]?.id ?? comparisonRows[0].id);
  const activeRow = comparisonRows.find((row) => row.id === activeRowId) ?? comparisonRows[0];

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <div>
            <span className="eyebrow">Comparativo direto</span>
            <h2 className="section-title">Compare em linha o que o mercado comum entrega e o que o Evento Vivo faz de fato.</h2>
          </div>
          <div className={styles.spotlight}>
            <span>Diferenca ativa</span>
            <strong>{activeRow.label}</strong>
            <p>{activeRow.spotlight}</p>
          </div>
        </div>

        <div className={styles.matrixWrap}>
          <div className={styles.matrix}>
            <div className={`${styles.headCell} ${styles.featureHead}`}>Capacidade</div>
            <div className={`${styles.headCell} ${styles.commonHead}`}>
              <strong>Solucoes comuns</strong>
              <span>normalmente so exibem</span>
            </div>
            <div className={`${styles.headCell} ${styles.premiumHead}`}>
              <strong>
                <Sparkles size={16} />
                Evento Vivo
              </strong>
              <span>transforma a foto em experiencia</span>
            </div>

            {comparisonRows.map((row, index) => {
              const isActive = row.id === activeRowId;

              return (
                <motion.div
                  key={row.id}
                  className={`${styles.row} ${isActive ? styles.activeRow : ""}`}
                  initial={{ opacity: 0, y: reduceMotion ? 0 : 18 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.36, delay: index * 0.04 }}
                  viewport={{ once: true, amount: 0.25 }}
                  onMouseEnter={() => setActiveRowId(row.id)}
                  onFocusCapture={() => setActiveRowId(row.id)}
                >
                  <button
                    type="button"
                    className={styles.featureCell}
                    onClick={() => setActiveRowId(row.id)}
                  >
                    <span>{row.label}</span>
                  </button>

                  <div className={`${styles.valueCell} ${styles.commonCell}`}>
                    <span className={styles.stateIcon}>
                      {row.commonLabel.startsWith("Sim") ? <Check size={16} /> : <X size={16} />}
                    </span>
                    <strong>{row.commonLabel}</strong>
                  </div>

                  <div className={`${styles.valueCell} ${styles.premiumCell}`}>
                    <span className={styles.stateIcon}>
                      <Check size={16} />
                    </span>
                    <strong>{row.premiumLabel}</strong>
                  </div>
                </motion.div>
              );
            })}
          </div>
        </div>
      </div>
    </section>
  );
}
