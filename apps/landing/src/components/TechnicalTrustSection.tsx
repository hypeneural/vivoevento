import { motion, useReducedMotion } from "motion/react";
import { Boxes, Gauge, Layers3, ShieldCheck, Webhook } from "lucide-react";
import styles from "./TechnicalTrustSection.module.scss";

const trustItems = [
  {
    icon: Gauge,
    title: "Projetado para eventos reais com alto volume",
    body: "Captura, moderação, indexação facial e publicação não disputam o mesmo gargalo. A operação continua fluida mesmo com pico na pista.",
  },
  {
    icon: Layers3,
    title: "Aprovação, busca e publicação sem travar a experiência",
    body: "O sistema distribui responsabilidades do fluxo para que o convidado veja velocidade, não bastidor congestionado.",
  },
  {
    icon: Webhook,
    title: "Tudo em tempo real, sem atualização manual",
    body: "Galeria, telão, jogos e feedback visual reagem ao vivo, o que aumenta a sensação de produto proprietário.",
  },
  {
    icon: ShieldCheck,
    title: "Confiabilidade operacional e controle de risco",
    body: "Idempotência, filas dedicadas e modos de moderação ajudam a manter a experiência estável e previsível.",
  },
  {
    icon: Boxes,
    title: "Provider-agnostic e preparado para crescer",
    body: "A base foi pensada para evoluir com parceiros, novos canais de captura e diferentes cenários de evento.",
  },
];

export default function TechnicalTrustSection() {
  const reduceMotion = useReducedMotion();

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <div>
            <span className="eyebrow">Por que somos únicos</span>
            <h2 className="section-title">Tecnologia traduzida em tranquilidade operacional.</h2>
          </div>
          <p className="section-lead">
            Em vez de despejar jargão técnico, a landing precisa mostrar o que essa arquitetura entrega
            comercialmente: segurança, rapidez, confiança e escala em eventos de verdade.
          </p>
        </div>

        <div className={styles.grid}>
          {trustItems.map((item, index) => {
            const Icon = item.icon;

            return (
              <motion.article
                key={item.title}
                className={styles.card}
                initial={{ opacity: 0, y: reduceMotion ? 0 : 24 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.05 }}
                viewport={{ once: true, amount: 0.25 }}
              >
                <div className={styles.iconWrap}>
                  <Icon size={18} />
                </div>
                <h3>{item.title}</h3>
                <p>{item.body}</p>
              </motion.article>
            );
          })}
        </div>

        <div className={styles.banner}>
          <strong>Pronto para picos de uso em pista, casamento, formatura e evento corporativo.</strong>
          <span>Menos improviso operacional. Mais percepção de premium logo no primeiro contato.</span>
        </div>
      </div>
    </section>
  );
}
