import { lazy, Suspense } from "react";
import styles from "./App.module.scss";
import { SEO } from "@/components/SEO";
import Navbar from "@/components/Navbar";
import HeroExperience from "@/components/HeroExperience";
import { PersonaProvider } from "@/contexts/PersonaContext";
import { AttributionProvider } from "@/contexts/AttributionContext";
import { ScrollUIProvider } from "@/contexts/ScrollUIContext";

// Lazy load seções abaixo da dobra
const HowItWorksSection = lazy(() => import("@/components/HowItWorksSection").then(m => ({ default: m.HowItWorksSection })));
const CaptureChannelsSection = lazy(() => import("@/components/CaptureChannelsSection").then(m => ({ default: m.CaptureChannelsSection })));
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

// Fallback component para Suspense
function SectionFallback() {
  return (
    <div className={styles.sectionFallback}>
      <div className={styles.spinner} aria-label="Carregando seção..." />
    </div>
  );
}

export default function App() {
  return (
    <PersonaProvider>
      <AttributionProvider>
        <ScrollUIProvider>
          <div id="top" className={styles.app}>
            <SEO />
            <div className={styles.noise} aria-hidden="true" />
            <Navbar />

            <main className={styles.main}>
              {/* Hero carregado imediatamente (acima da dobra) */}
              <HeroExperience />

              {/* Seções abaixo da dobra com lazy loading */}
              <Suspense fallback={<SectionFallback />}>
                <HowItWorksSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <CaptureChannelsSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <ExperienceModulesSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <EcosystemSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <AISafetySection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <FaceSearchSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <TechnicalTrustSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <ComparisonSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <TestimonialsSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <AudienceSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <PricingSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <FAQSection />
              </Suspense>

              <Suspense fallback={<SectionFallback />}>
                <FinalCTASection />
              </Suspense>
            </main>

            <Suspense fallback={<SectionFallback />}>
              <FooterSection />
            </Suspense>
          </div>
        </ScrollUIProvider>
      </AttributionProvider>
    </PersonaProvider>
  );
}
