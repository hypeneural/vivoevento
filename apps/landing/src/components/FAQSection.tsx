import { useState, useRef, useEffect, KeyboardEvent } from "react";
import { ChevronDown } from "lucide-react";
import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { getGSAPConfig, DURATION } from '@/utils/motion';
import styles from "./FAQSection.module.scss";
import { faqs } from "@/data/landing";

gsap.registerPlugin(useGSAP, ScrollTrigger);

export default function FAQSection() {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const triggerRefs = useRef<(HTMLButtonElement | null)[]>([]);
  const scope = useRef<HTMLElement>(null);

  // Initialize refs array
  useEffect(() => {
    triggerRefs.current = triggerRefs.current.slice(0, faqs.length);
  }, []);

  useGSAP(
    () => {
      // Header entrance animation
      gsap.from('[data-faq-header]', getGSAPConfig({
        y: 24,
        opacity: 0,
        duration: DURATION.medium,
        scrollTrigger: {
          trigger: scope.current,
          start: 'top 72%',
        },
      }));

      // Staggered FAQ items
      gsap.from('[data-faq-item]', getGSAPConfig({
        y: 20,
        opacity: 0,
        stagger: 0.08,
        duration: DURATION.normal,
        scrollTrigger: {
          trigger: scope.current,
          start: 'top 68%',
        },
      }));
    },
    { scope }
  );

  const handleToggle = (index: number) => {
    setOpenIndex(openIndex === index ? null : index);
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
    let targetIndex: number | null = null;

    switch (event.key) {
      case "ArrowDown":
        event.preventDefault();
        targetIndex = index === faqs.length - 1 ? 0 : index + 1;
        break;
      case "ArrowUp":
        event.preventDefault();
        targetIndex = index === 0 ? faqs.length - 1 : index - 1;
        break;
      case "Home":
        event.preventDefault();
        targetIndex = 0;
        break;
      case "End":
        event.preventDefault();
        targetIndex = faqs.length - 1;
        break;
      default:
        return;
    }

    if (targetIndex !== null) {
      triggerRefs.current[targetIndex]?.focus();
    }
  };

  return (
    <section 
      id="faq" 
      className={`section-shell ${styles.section}`} 
      aria-labelledby="faq-title"
      ref={scope}
    >
      <div className="container">
        <div className={styles.header} data-faq-header>
          <span className="eyebrow">FAQ</span>
          <h2 id="faq-title" className="section-title">
            Perguntas que normalmente travam a decisão comercial
          </h2>
        </div>

        <div className={styles.list} role="region" aria-label="Perguntas frequentes">
          {faqs.map((faq, index) => {
            const isOpen = openIndex === index;
            const headingId = `faq-heading-${index}`;
            const panelId = `faq-panel-${index}`;

            return (
              <article 
                key={faq.id} 
                className={`${styles.item} ${isOpen ? styles.open : ""}`}
                data-faq-item
              >
                <h3 id={headingId} className={styles.heading}>
                  <button
                    ref={(el) => (triggerRefs.current[index] = el)}
                    type="button"
                    className={styles.trigger}
                    onClick={() => handleToggle(index)}
                    onKeyDown={(e) => handleKeyDown(e, index)}
                    aria-expanded={isOpen}
                    aria-controls={panelId}
                  >
                    <span>{faq.question}</span>
                    <ChevronDown size={18} aria-hidden="true" />
                  </button>
                </h3>
                <div
                  id={panelId}
                  role="region"
                  aria-labelledby={headingId}
                  className={styles.content}
                  hidden={!isOpen}
                >
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
