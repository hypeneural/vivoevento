import { usePersona, PersonaId } from '@/contexts/PersonaContext';
import { useEffect, useRef, useState } from 'react';
import styles from './PersonaSelector.module.scss';

type PersonaOption = {
  id: PersonaId;
  title: string;
  description: string;
  icon: string;
};

const personaOptions: PersonaOption[] = [
  {
    id: 'assessora',
    title: 'Sou assessora/cerimonialista',
    description: 'Controle total e segurança operacional',
    icon: '🎯',
  },
  {
    id: 'social',
    title: 'Sou noiva/debutante/família',
    description: 'Experiência inesquecível no meu evento',
    icon: '💝',
  },
  {
    id: 'corporativo',
    title: 'Sou produtor/promotor/corporativo',
    description: 'Engajamento em escala com segurança de marca',
    icon: '🏢',
  },
];

export function PersonaSelector() {
  const { selectedPersona, setSelectedPersona } = usePersona();
  const [focusedIndex, setFocusedIndex] = useState<number>(-1);
  const optionsRef = useRef<(HTMLButtonElement | null)[]>([]);

  // Track persona selection interaction
  const handlePersonaSelect = (personaId: PersonaId) => {
    setSelectedPersona(personaId);
    
    // Track analytics event
    if (typeof window !== 'undefined' && (window as any).gtag) {
      (window as any).gtag('event', 'persona_selected', {
        event_category: 'engagement',
        event_label: personaId,
        value: 1,
      });
    }
  };

  // Keyboard navigation
  const handleKeyDown = (event: React.KeyboardEvent, index: number) => {
    let newIndex = index;

    switch (event.key) {
      case 'ArrowRight':
      case 'ArrowDown':
        event.preventDefault();
        newIndex = (index + 1) % personaOptions.length;
        break;
      case 'ArrowLeft':
      case 'ArrowUp':
        event.preventDefault();
        newIndex = (index - 1 + personaOptions.length) % personaOptions.length;
        break;
      case 'Home':
        event.preventDefault();
        newIndex = 0;
        break;
      case 'End':
        event.preventDefault();
        newIndex = personaOptions.length - 1;
        break;
      case 'Enter':
      case ' ':
        event.preventDefault();
        handlePersonaSelect(personaOptions[index].id);
        return;
      default:
        return;
    }

    setFocusedIndex(newIndex);
    optionsRef.current[newIndex]?.focus();
  };

  // Set initial focus index based on selected persona
  useEffect(() => {
    if (selectedPersona) {
      const index = personaOptions.findIndex(opt => opt.id === selectedPersona);
      if (index !== -1) {
        setFocusedIndex(index);
      }
    }
  }, [selectedPersona]);

  return (
    <section 
      className={styles.personaSelector}
      aria-labelledby="persona-selector-title"
    >
      <div className={styles.container}>
        <div className={styles.header}>
          <p className={styles.eyebrow}>Personalize sua experiência</p>
          <h2 id="persona-selector-title" className={styles.title}>
            Qual é o seu perfil?
          </h2>
          <p className={styles.subtitle}>
            Selecione para ver conteúdo mais relevante para você
          </p>
        </div>

        <div 
          className={styles.options}
          role="radiogroup"
          aria-labelledby="persona-selector-title"
        >
          {personaOptions.map((option, index) => {
            const isSelected = selectedPersona === option.id;
            const isFocused = focusedIndex === index;

            return (
              <button
                key={option.id}
                ref={el => optionsRef.current[index] = el}
                type="button"
                role="radio"
                aria-checked={isSelected}
                aria-label={`${option.title}: ${option.description}`}
                className={`${styles.option} ${isSelected ? styles.selected : ''} ${isFocused ? styles.focused : ''}`}
                onClick={() => handlePersonaSelect(option.id)}
                onKeyDown={(e) => handleKeyDown(e, index)}
                tabIndex={index === 0 || isSelected ? 0 : -1}
              >
                <div className={styles.optionIcon} aria-hidden="true">
                  {option.icon}
                </div>
                <div className={styles.optionContent}>
                  <h3 className={styles.optionTitle}>{option.title}</h3>
                  <p className={styles.optionDescription}>{option.description}</p>
                </div>
                <div className={styles.optionIndicator} aria-hidden="true">
                  {isSelected && (
                    <svg 
                      width="24" 
                      height="24" 
                      viewBox="0 0 24 24" 
                      fill="none" 
                      stroke="currentColor" 
                      strokeWidth="2" 
                      strokeLinecap="round" 
                      strokeLinejoin="round"
                    >
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  )}
                </div>
              </button>
            );
          })}
        </div>

        {selectedPersona && (
          <p className={styles.confirmation} role="status" aria-live="polite">
            ✓ Conteúdo adaptado para {personaOptions.find(opt => opt.id === selectedPersona)?.title.toLowerCase()}
          </p>
        )}
      </div>
    </section>
  );
}
