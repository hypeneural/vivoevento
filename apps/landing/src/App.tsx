import styles from "./App.module.scss";
import Navbar from "@/components/Navbar";
import HeroExperience from "@/components/HeroExperience";
import EcosystemSection from "@/components/EcosystemSection";
import DynamicGallerySection from "@/components/DynamicGallerySection";
import InteractiveGamesSection from "@/components/InteractiveGamesSection";
import DynamicWallSection from "@/components/DynamicWallSection";
import ModerationAISection from "@/components/ModerationAISection";
import FaceSearchSection from "@/components/FaceSearchSection";
import TechnicalTrustSection from "@/components/TechnicalTrustSection";
import ComparisonSection from "@/components/ComparisonSection";
import TestimonialsSection from "@/components/TestimonialsSection";
import AudienceSection from "@/components/AudienceSection";
import PricingSection from "@/components/PricingSection";
import FAQSection from "@/components/FAQSection";
import FinalCTASection from "@/components/FinalCTASection";
import FooterSection from "@/components/FooterSection";

export default function App() {
  return (
    <div id="top" className={styles.app}>
      <div className={styles.noise} aria-hidden="true" />
      <Navbar />

      <main className={styles.main}>
        <HeroExperience />
        <EcosystemSection />
        <DynamicGallerySection />
        <InteractiveGamesSection />
        <DynamicWallSection />
        <ModerationAISection />
        <FaceSearchSection />
        <TechnicalTrustSection />
        <ComparisonSection />
        <TestimonialsSection />
        <AudienceSection />
        <PricingSection />
        <FAQSection />
        <FinalCTASection />
      </main>

      <FooterSection />
    </div>
  );
}
