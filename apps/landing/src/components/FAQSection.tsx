import { useState } from "react";
import { ChevronDown } from "lucide-react";
import styles from "./FAQSection.module.scss";
import { faqs } from "@/data/landing";

export default function FAQSection() {
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  return (
    <section id="faq" className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.header}>
          <span className="eyebrow">FAQ</span>
          <h2 className="section-title">Perguntas que normalmente travam a decisao comercial.</h2>
        </div>

        <div className={styles.list}>
          {faqs.map((faq, index) => {
            const isOpen = openIndex === index;

            return (
              <article key={faq.question} className={`${styles.item} ${isOpen ? styles.open : ""}`}>
                <button type="button" className={styles.trigger} onClick={() => setOpenIndex(isOpen ? null : index)}>
                  <span>{faq.question}</span>
                  <ChevronDown size={18} />
                </button>
                <div className={styles.content}>
                  <p>{faq.answer}</p>
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
