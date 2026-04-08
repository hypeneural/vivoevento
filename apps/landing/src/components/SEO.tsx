/**
 * SEO Component
 * 
 * Dynamically manages meta tags, Open Graph tags, Twitter Card tags,
 * and structured data (JSON-LD) based on the selected persona.
 * 
 * Usage:
 * <SEO /> - Uses persona from PersonaContext
 * <SEO persona="assessora" /> - Override persona
 */

import { useEffect } from 'react';
import { Helmet } from 'react-helmet-async';
import { usePersona } from '../contexts/PersonaContext';
import { getSEOConfig } from '../config/seo';
import type { PersonaId } from '../contexts/PersonaContext';

type SEOProps = {
  persona?: PersonaId | null;
};

export function SEO({ persona: personaProp }: SEOProps) {
  const { selectedPersona, entryVariation } = usePersona();
  
  // Priority: prop > selectedPersona > entryVariation > null (base)
  const activePersona = personaProp ?? selectedPersona ?? entryVariation;
  
  const seoConfig = getSEOConfig(activePersona);
  
  // Update canonical URL when persona changes
  useEffect(() => {
    const canonicalLink = document.querySelector('link[rel="canonical"]');
    if (canonicalLink) {
      canonicalLink.setAttribute('href', seoConfig.meta.canonical || '');
    }
  }, [seoConfig.meta.canonical]);
  
  return (
    <Helmet>
      {/* Primary Meta Tags */}
      <title>{seoConfig.meta.title}</title>
      <meta name="title" content={seoConfig.meta.title} />
      <meta name="description" content={seoConfig.meta.description} />
      {seoConfig.meta.keywords && (
        <meta name="keywords" content={seoConfig.meta.keywords} />
      )}
      {seoConfig.meta.author && (
        <meta name="author" content={seoConfig.meta.author} />
      )}
      
      {/* Canonical URL */}
      {seoConfig.meta.canonical && (
        <link rel="canonical" href={seoConfig.meta.canonical} />
      )}
      
      {/* Open Graph / Facebook */}
      <meta property="og:type" content={seoConfig.openGraph.type || 'website'} />
      <meta property="og:url" content={seoConfig.openGraph.url} />
      <meta property="og:title" content={seoConfig.openGraph.title} />
      <meta property="og:description" content={seoConfig.openGraph.description} />
      <meta property="og:image" content={seoConfig.openGraph.image} />
      {seoConfig.openGraph.locale && (
        <meta property="og:locale" content={seoConfig.openGraph.locale} />
      )}
      {seoConfig.openGraph.siteName && (
        <meta property="og:site_name" content={seoConfig.openGraph.siteName} />
      )}
      
      {/* Twitter Card */}
      <meta name="twitter:card" content={seoConfig.twitter.card} />
      <meta name="twitter:url" content={seoConfig.openGraph.url} />
      <meta name="twitter:title" content={seoConfig.twitter.title} />
      <meta name="twitter:description" content={seoConfig.twitter.description} />
      <meta name="twitter:image" content={seoConfig.twitter.image} />
      {seoConfig.twitter.site && (
        <meta name="twitter:site" content={seoConfig.twitter.site} />
      )}
      {seoConfig.twitter.creator && (
        <meta name="twitter:creator" content={seoConfig.twitter.creator} />
      )}
      
      {/* Structured Data - Organization */}
      <script type="application/ld+json">
        {JSON.stringify(seoConfig.structuredData.organization)}
      </script>
      
      {/* Structured Data - WebPage */}
      <script type="application/ld+json">
        {JSON.stringify(seoConfig.structuredData.webPage)}
      </script>
    </Helmet>
  );
}
