import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { Boxes, Gauge, Layers3, ShieldCheck, Webhook } from "lucide-react";
import styles from "./TechnicalTrustSection.module.scss";

type TrustItemId = "volume" | "orchestration" | "realtime" | "safety" | "scale";

type TrustItem = {
  id: TrustItemId;
  icon: typeof Gauge;
  title: string;
  metric: string;
  body: string;
  lanes: Array<{ label: string; state: "idle" | "live" | "ready" }>;
  chips: string[];
};

const trustItems: TrustItem[] = [
  {
    id: "volume",
    icon: Gauge,
    title: "Projetado para eventos reais com alto volume",
    metric: "picos de captura sem colapso",
    body: "Captura, moderacao, indexacao facial e publicacao nao disputam o mesmo gargalo. A operacao continua fluida mesmo quando a pista acelera.",
    lanes: [
      { label: "Captura entrando", state: "live" },
      { label: "Fila processando", state: "ready" },
      { label: "Publicacao mantendo ritmo", state: "ready" },
    ],
    chips: ["pista", "casamento", "formatura", "corporativo"],
  },
  {
    id: "orchestration",
    icon: Layers3,
    title: "Aprovacao, busca e publicacao sem travar a experiencia",
    metric: "modulos desacoplados",
    body: "O visitante percebe resposta rapida porque os bastidores nao tentam resolver tudo no mesmo bloco. Cada etapa anda no fluxo certo.",
    lanes: [
      { label: "Moderacao", state: "live" },
      { label: "Indexacao facial", state: "ready" },
      { label: "Distribuicao para modulos", state: "ready" },
    ],
    chips: ["filas dedicadas", "latencia previsivel", "mais estabilidade"],
  },
  {
    id: "realtime",
    icon: Webhook,
    title: "Tudo em tempo real, sem atualizacao manual",
    metric: "galeria, jogo e telao sincronizados",
    body: "Galeria, telao, jogos e feedback visual reagem juntos. O resultado e percepcao de software proprietario, nao painel travado.",
    lanes: [
      { label: "Evento publicado", state: "ready" },
      { label: "Broadcast ao vivo", state: "live" },
      { label: "Clientes recebendo", state: "ready" },
    ],
    chips: ["websockets", "feedback instantaneo", "sincronia visual"],
  },
  {
    id: "safety",
    icon: ShieldCheck,
    title: "Confiabilidade operacional e controle de risco",
    metric: "menos surpresa no momento critico",
    body: "Idempotencia, modos de moderacao e trilha previsivel ajudam a manter a experiencia estavel mesmo com repeticao, erro humano ou pico de uso.",
    lanes: [
      { label: "Entrada duplicada bloqueada", state: "ready" },
      { label: "Regras de moderacao", state: "live" },
      { label: "Exibicao segura", state: "ready" },
    ],
    chips: ["idempotencia", "controle de risco", "operacao previsivel"],
  },
  {
    id: "scale",
    icon: Boxes,
    title: "Provider-agnostic e preparado para crescer",
    metric: "base pronta para evoluir",
    body: "A arquitetura nao fica presa a um unico fornecedor. Isso abre espaco para parceiros, novos canais de captura e operacoes maiores.",
    lanes: [
      { label: "Canal atual em uso", state: "live" },
      { label: "Novo provedor plugavel", state: "idle" },
      { label: "Expansao sem reescrever tudo", state: "ready" },
    ],
    chips: ["parceiros", "novos canais", "escala recorrente"],
  },
];

function getNextTrustItem(current: TrustItemId) {
  const index = trustItems.findIndex((item) => item.id === current);
  return trustItems[(index + 1) % trustItems.length]?.id ?? trustItems[0].id;
}

export default function TechnicalTrustSection() {
  const reduceMotion = useReducedMotion();
  const [activeItemId, setActiveItemId] = useState<TrustItemId>("volume");

  useEffect(() => {
    if (reduceMotion) return undefined;

    const timer = window.setInterval(() => {
      setActiveItemId((current) => getNextTrustItem(current));
    }, 3600);

    return () => window.clearInterval(timer);
  }, [reduceMotion]);

  const activeItem = trustItems.find((item) => item.id === activeItemId) ?? trustItems[0];

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.layout}>
          <div className={styles.copyColumn}>
            <div className={styles.header}>
              <div>
                <span className="eyebrow">Por que somos unicos</span>
                <h2 className="section-title">Tecnologia traduzida em tranquilidade operacional.</h2>
              </div>
              <p className="section-lead">
                Em vez de despejar jargao tecnico, mostramos o que a arquitetura entrega comercialmente:
                seguranca, rapidez, confianca e escala em eventos de verdade.
              </p>
            </div>

            <div className={styles.selectorList}>
              {trustItems.map((item) => {
                const Icon = item.icon;
                const isActive = item.id === activeItemId;

                return (
                  <button
                    key={item.id}
                    type="button"
                    className={`${styles.selectorCard} ${isActive ? styles.activeSelector : ""}`}
                    onMouseEnter={() => setActiveItemId(item.id)}
                    onFocus={() => setActiveItemId(item.id)}
                    onClick={() => setActiveItemId(item.id)}
                  >
                    <span className={styles.selectorIcon}>
                      <Icon size={18} />
                    </span>
                    <span className={styles.selectorText}>
                      <strong>{item.title}</strong>
                      <small>{item.metric}</small>
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          <div className={styles.boardColumn}>
            <AnimatePresence mode="wait">
              <motion.div
                key={activeItem.id}
                className={styles.board}
                initial={{ opacity: 0, y: reduceMotion ? 0 : 16 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: reduceMotion ? 0 : -16 }}
                transition={{ duration: reduceMotion ? 0.01 : 0.28 }}
              >
                <div className={styles.boardHeader}>
                  <span>{activeItem.metric}</span>
                  <strong>{activeItem.title}</strong>
                  <p>{activeItem.body}</p>
                </div>

                <div className={styles.laneStack}>
                  {activeItem.lanes.map((lane) => (
                    <div key={lane.label} className={styles.lane} data-state={lane.state}>
                      <span>{lane.label}</span>
                      <strong>{lane.state === "live" ? "ao vivo" : lane.state === "ready" ? "ok" : "standby"}</strong>
                    </div>
                  ))}
                </div>

                <div className={styles.chipRow}>
                  {activeItem.chips.map((chip) => (
                    <span key={chip}>{chip}</span>
                  ))}
                </div>
              </motion.div>
            </AnimatePresence>
          </div>
        </div>

        <div className={styles.banner}>
          <strong>Pronto para picos de uso em pista, casamento, formatura e evento corporativo.</strong>
          <span>Menos improviso operacional. Mais percepcao de premium logo no primeiro contato.</span>
        </div>
      </div>
    </section>
  );
}
