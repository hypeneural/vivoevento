import { lazy, Suspense, useEffect } from "react";
import styles from "./App.module.scss";
import { SEO } from "@/components/SEO";
import Navbar from "@/components/Navbar";
import HeroExperience from "@/components/HeroExperience";
import CTAFloating from "@/components/CTAFloating";
import { NoScriptFallback } from "@/components/NoScriptFallback";
import { ErrorBoundary } from "@/components/ErrorBoundary";
import { PersonaProvider } from "@/contexts/PersonaContext";
import { AttributionProvider } from "@/contexts/AttributionContext";
import { ScrollUIProvider } from "@/contexts/ScrollUIContext";
import { preloadCriticalImages } from "@/utils/imageOptimization";
import { initWebVitalsOptimizations } from "@/utils/webVitals";
import { initRUM, getRUMConfig } from "@/utils/rum";

// Lazy load seções abaixo da dobra
const HowItWorksSection = lazy(() => import("@/components/HowItWorksSection").then(m => ({ default: m.HowItWorksSection })));
const CaptureChannelsSection = lazy(() => import("@/components/CaptureChannelsSection").then(m => ({ default: m.CaptureChannelsSection })));
const PersonaSelector = lazy(() => import("@/components/PersonaSelector").then(m => ({ default: m.PersonaSelector })));
const ExperienceModulesSection = lazy(() => import("@/components/ExperienceModulesSection"));
const EcosystemSection = lazy(() => import("@/components/EcosystemSection"));
const AISafetySection = lazy(() => import("@/components/AISafetySection"));
const FaceSearchSection = lazy(() => import("@/components/FaceSearchSection"));
const TechnicalTrustSection = lazy(() => import("@/components/TechnicalTrustSection"));
const ComparisonSection = lazy(() => import("@/components/ComparisonSection"));
const TestimonialsSection = lazy(() => import("@/components/TestimonialsSection"));
const AudienceSection = lazy(() => import("@/components/AudienceSection"));
const PricingSection = lazy(() => import("@/components/PricingSection"));
const FAQSection = lazy(() => import("@/components/FAQSection"));
const FinalCTASection = lazy(() => import("@/components/FinalCTASection"));
const FooterSection = lazy(() => import("@/components/FooterSection"));

// Development-only components
const PerformanceMonitor = import.meta.env.MODE === 'development' 
  ? lazy(() => import("@/components/dev/PerformanceMonitor"))
  : null;

// Fallback components para Suspense com diferentes prioridades
function SectionFallback() {
  return (
    <div className={styles.sectionFallback}>
      <div className={styles.spinner} aria-label="Carregando seção..." />
    </div>
  );
}

// Fallback mínimo para conteúdo crítico
function MinimalFallback() {
  return <div className={styles.minimalFallback} aria-hidden="true" />;
}

// Fallback para demos pesadas (Phaser, Rive)
function HeavyDemoFallback() {
  return (
    <div className={styles.heavyDemoFallback}>
      <div className={styles.demoSpinner} />
      <p>Preparando demonstração interativa...</p>
    </div>
  );
}

export default function App() {
  // Preload critical above-the-fold images
  useEffect(() => {
    preloadCriticalImages();
  }, []);
  
  // Initialize Web Vitals monitoring and optimizations
  useEffect(() => {
    initWebVitalsOptimizations();
  }, []);
  
  // Initialize RUM (Real User Monitoring)
  useEffect(() => {
    const env = import.meta.env.MODE as 'development' | 'staging' | 'production';
    const rumConfig = getRUMConfig(env);
    initRUM(rumConfig);
  }, []);
  
  const handleFloatingCTAInteraction = (action: 'primary' | 'secondary' | 'close') => {
    // Track floating CTA interactions
    if (typeof window !== 'undefined' && (window as any).gtag) {
      (window as any).gtag('event', 'floating_cta_interaction', {
        event_category: 'Engagement',
        event_label: `Floating CTA - ${action}`,
        value: action === 'close' ? 0 : 1,
      });
    }
  };

  return (
    <PersonaProvider>
      <AttributionProvider>
        <ScrollUIProvider>
          <div id="top" className={styles.app}>
            <SEO />
            <NoScriptFallback />
            <div className={styles.noise} aria-hidden="true" />
            <Navbar />
            
            {/* Floating CTA - appears after 80% scroll */}
            <CTAFloating onInteraction={handleFloatingCTAInteraction} />

            <main className={styles.main}>
              {/* Hero carregado imediatamente (acima da dobra) */}
              <ErrorBoundary componentName="Hero" showCTA>
                <HeroExperience />
              </ErrorBoundary>

              {/* Seções abaixo da dobra com lazy loading */}
              <ErrorBoundary componentName="Como Funciona">
                <Suspense fallback={<SectionFallback />}>
                  <HowItWorksSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Canais de Captura">
                <Suspense fallback={<SectionFallback />}>
                  <CaptureChannelsSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Seletor de Persona">
                <Suspense fallback={<SectionFallback />}>
                  <PersonaSelector />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Módulos de Experiência">
                <Suspense fallback={<SectionFallback />}>
                  <ExperienceModulesSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Ecossistema">
                <Suspense fallback={<SectionFallback />}>
                  <EcosystemSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="IA e Segurança">
                <Suspense fallback={<SectionFallback />}>
                  <AISafetySection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Busca Facial">
                <Suspense fallback={<SectionFallback />}>
                  <FaceSearchSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Confiança Técnica">
                <Suspense fallback={<SectionFallback />}>
                  <TechnicalTrustSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Comparação">
                <Suspense fallback={<SectionFallback />}>
                  <ComparisonSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Depoimentos" showCTA>
                <Suspense fallback={<SectionFallback />}>
                  <TestimonialsSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Para Quem É">
                <Suspense fallback={<SectionFallback />}>
                  <AudienceSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="Planos" showCTA>
                <Suspense fallback={<SectionFallback />}>
                  <PricingSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="FAQ">
                <Suspense fallback={<SectionFallback />}>
                  <FAQSection />
                </Suspense>
              </ErrorBoundary>

              <ErrorBoundary componentName="CTA Final" showCTA>
                <Suspense fallback={<SectionFallback />}>
                  <FinalCTASection />
                </Suspense>
              </ErrorBoundary>
            </main>

            <ErrorBoundary componentName="Footer">
              <Suspense fallback={<SectionFallback />}>
                <FooterSection />
              </Suspense>
            </ErrorBoundary>
            
            {/* Performance Monitor (development only) */}
            {PerformanceMonitor && (
              <Suspense fallback={null}>
                <PerformanceMonitor />
              </Suspense>
            )}
          </div>
        </ScrollUIProvider>
      </AttributionProvider>
    </PersonaProvider>
  );
}
