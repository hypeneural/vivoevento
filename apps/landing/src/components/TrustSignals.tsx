import { MessageSquare, ScanFace, ShieldCheck, Smartphone } from "lucide-react";
import type { TrustSignal } from "@/data/landing";
import styles from "./TrustSignals.module.scss";

const iconMap = {
  Smartphone,
  ShieldCheck,
  MessageSquare,
  ScanFace,
};

type TrustSignalsProps = {
  signals: TrustSignal[];
  variant?: "default" | "compact";
};

export default function TrustSignals({ signals, variant = "default" }: TrustSignalsProps) {
  return (
    <div className={styles.trustSignals} data-variant={variant}>
      {signals.map((signal) => {
        const Icon = iconMap[signal.icon as keyof typeof iconMap] || Smartphone;
        
        return (
          <div key={signal.id} className={styles.signal}>
            <Icon size={16} aria-hidden="true" />
            <div className={styles.signalContent}>
              <strong>{signal.text}</strong>
              {signal.detail && <span>{signal.detail}</span>}
            </div>
          </div>
        );
      })}
    </div>
  );
}
