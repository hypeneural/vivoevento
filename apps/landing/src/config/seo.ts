/**
 * SEO Configuration
 * 
 * Centralized SEO configuration for the landing page with variations
 * per persona (assessora, social, corporativo).
 * 
 * Includes:
 * - Meta tags (title, description, keywords)
 * - Open Graph tags (Facebook, LinkedIn)
 * - Twitter Card tags
 * - Structured data (JSON-LD for Organization and WebPage)
 */

import { siteConfig } from './site';
import type { SEOConfig, PersonaSEOConfig } from '../types/seo';

// Base URL for assets
const ASSETS_BASE_URL = `${siteConfig.siteUrl}/assets`;

// Default/Base SEO Configuration (no persona)
export const baseSEOConfig: SEOConfig = {
  meta: {
    title: 'Evento Vivo | Transforme fotos em experiências ao vivo no seu evento',
    description: 'Os convidados já estão tirando fotos. Agora elas viram galeria ao vivo, telão dinâmico, jogos interativos e busca facial. Sem app. Moderação por IA. Configurável.',
    keywords: 'evento ao vivo, galeria de fotos, telão dinâmico, jogos interativos, busca facial, moderação IA, casamento, formatura, evento corporativo',
    author: 'Evento Vivo',
    canonical: siteConfig.siteUrl,
  },
  openGraph: {
    title: 'Evento Vivo | Transforme fotos em experiências ao vivo',
    description: 'Fotos dos convidados viram galeria ao vivo, telão, jogos e busca facial. Sem app, com moderação IA.',
    image: `${ASSETS_BASE_URL}/og-image-default.jpg`,
    url: siteConfig.siteUrl,
    type: 'website',
    locale: 'pt_BR',
    siteName: 'Evento Vivo',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Evento Vivo | Transforme fotos em experiências ao vivo',
    description: 'Fotos dos convidados viram galeria ao vivo, telão, jogos e busca facial. Sem app, com moderação IA.',
    image: `${ASSETS_BASE_URL}/twitter-card-default.jpg`,
    site: '@eventovivo',
  },
  structuredData: {
    organization: {
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: 'Evento Vivo',
      url: siteConfig.siteUrl,
      logo: `${ASSETS_BASE_URL}/logo.png`,
      description: 'Plataforma premium de experiências visuais ao vivo para eventos',
      contactPoint: {
        '@type': 'ContactPoint',
        telephone: `+${siteConfig.whatsappNumber}`,
        contactType: 'customer service',
        availableLanguage: ['Portuguese', 'pt-BR'],
      },
      sameAs: [
        siteConfig.instagramUrl,
        ...(siteConfig.linkedinUrl ? [siteConfig.linkedinUrl] : []),
      ],
    },
    webPage: {
      '@context': 'https://schema.org',
      '@type': 'WebPage',
      name: 'Evento Vivo | Transforme fotos em experiências ao vivo',
      description: 'Plataforma premium de experiências visuais ao vivo para eventos',
      url: siteConfig.siteUrl,
      inLanguage: 'pt-BR',
      isPartOf: {
        '@type': 'WebSite',
        name: 'Evento Vivo',
        url: siteConfig.siteUrl,
      },
    },
  },
};

// Persona-specific SEO Configurations
export const personaSEOConfig: PersonaSEOConfig = {
  assessora: {
    meta: {
      title: 'Evento Vivo para Assessoras | Controle total e segurança operacional',
      description: 'Plataforma completa para assessoras e cerimonialistas: galeria ao vivo, moderação IA configurável, busca facial e telão dinâmico. Controle total do evento.',
      keywords: 'assessoria de eventos, cerimonial, galeria de casamento, moderação de fotos, busca facial, telão casamento, evento seguro',
      author: 'Evento Vivo',
      canonical: `${siteConfig.siteUrl}?persona=assessora`,
    },
    openGraph: {
      title: 'Evento Vivo para Assessoras | Controle e segurança',
      description: 'Galeria ao vivo, moderação IA, busca facial e telão. Controle total para assessoras e cerimonialistas.',
      image: `${ASSETS_BASE_URL}/og-image-assessora.jpg`,
      url: `${siteConfig.siteUrl}?persona=assessora`,
      type: 'website',
      locale: 'pt_BR',
      siteName: 'Evento Vivo',
    },
    twitter: {
      card: 'summary_large_image',
      title: 'Evento Vivo para Assessoras | Controle e segurança',
      description: 'Galeria ao vivo, moderação IA, busca facial e telão. Controle total para assessoras.',
      image: `${ASSETS_BASE_URL}/twitter-card-assessora.jpg`,
      site: '@eventovivo',
    },
    structuredData: {
      organization: baseSEOConfig.structuredData.organization,
      webPage: {
        '@context': 'https://schema.org',
        '@type': 'WebPage',
        name: 'Evento Vivo para Assessoras e Cerimonialistas',
        description: 'Plataforma completa com controle total e segurança operacional para assessoras de eventos',
        url: `${siteConfig.siteUrl}?persona=assessora`,
        inLanguage: 'pt-BR',
        isPartOf: {
          '@type': 'WebSite',
          name: 'Evento Vivo',
          url: siteConfig.siteUrl,
        },
      },
    },
  },
  
  social: {
    meta: {
      title: 'Evento Vivo para Casamentos | Fotos ao vivo com emoção e facilidade',
      description: 'Transforme seu casamento, debutante ou festa: fotos dos convidados viram galeria ao vivo, jogos e busca facial. Sem app, super fácil de usar.',
      keywords: 'casamento, debutante, festa, galeria de fotos ao vivo, busca facial casamento, jogos casamento, fotos convidados',
      author: 'Evento Vivo',
      canonical: `${siteConfig.siteUrl}?persona=social`,
    },
    openGraph: {
      title: 'Evento Vivo para Casamentos | Emoção e facilidade',
      description: 'Fotos dos convidados viram galeria ao vivo, jogos e busca facial. Sem app, super fácil.',
      image: `${ASSETS_BASE_URL}/og-image-social.jpg`,
      url: `${siteConfig.siteUrl}?persona=social`,
      type: 'website',
      locale: 'pt_BR',
      siteName: 'Evento Vivo',
    },
    twitter: {
      card: 'summary_large_image',
      title: 'Evento Vivo para Casamentos | Emoção e facilidade',
      description: 'Fotos dos convidados viram galeria ao vivo, jogos e busca facial. Sem app.',
      image: `${ASSETS_BASE_URL}/twitter-card-social.jpg`,
      site: '@eventovivo',
    },
    structuredData: {
      organization: baseSEOConfig.structuredData.organization,
      webPage: {
        '@context': 'https://schema.org',
        '@type': 'WebPage',
        name: 'Evento Vivo para Casamentos e Festas',
        description: 'Transforme fotos em experiências emocionantes para casamentos, debutantes e festas',
        url: `${siteConfig.siteUrl}?persona=social`,
        inLanguage: 'pt-BR',
        isPartOf: {
          '@type': 'WebSite',
          name: 'Evento Vivo',
          url: siteConfig.siteUrl,
        },
      },
    },
  },
  
  corporativo: {
    meta: {
      title: 'Evento Vivo para Eventos Corporativos | Engajamento e escala',
      description: 'Plataforma enterprise para eventos corporativos: telão dinâmico, jogos interativos, moderação IA e analytics. Engajamento em escala para ativações e convenções.',
      keywords: 'evento corporativo, ativação de marca, convenção, telão interativo, jogos corporativos, moderação IA, analytics evento',
      author: 'Evento Vivo',
      canonical: `${siteConfig.siteUrl}?persona=corporativo`,
    },
    openGraph: {
      title: 'Evento Vivo para Corporativo | Engajamento em escala',
      description: 'Telão dinâmico, jogos interativos, moderação IA e analytics para eventos corporativos.',
      image: `${ASSETS_BASE_URL}/og-image-corporativo.jpg`,
      url: `${siteConfig.siteUrl}?persona=corporativo`,
      type: 'website',
      locale: 'pt_BR',
      siteName: 'Evento Vivo',
    },
    twitter: {
      card: 'summary_large_image',
      title: 'Evento Vivo para Corporativo | Engajamento em escala',
      description: 'Telão dinâmico, jogos interativos, moderação IA para eventos corporativos.',
      image: `${ASSETS_BASE_URL}/twitter-card-corporativo.jpg`,
      site: '@eventovivo',
    },
    structuredData: {
      organization: baseSEOConfig.structuredData.organization,
      webPage: {
        '@context': 'https://schema.org',
        '@type': 'WebPage',
        name: 'Evento Vivo para Eventos Corporativos',
        description: 'Plataforma enterprise com engajamento em escala para eventos corporativos e ativações',
        url: `${siteConfig.siteUrl}?persona=corporativo`,
        inLanguage: 'pt-BR',
        isPartOf: {
          '@type': 'WebSite',
          name: 'Evento Vivo',
          url: siteConfig.siteUrl,
        },
      },
    },
  },
};

/**
 * Get SEO configuration for a specific persona
 * Falls back to base config if persona is null
 */
export function getSEOConfig(persona: 'assessora' | 'social' | 'corporativo' | null): SEOConfig {
  if (!persona) {
    return baseSEOConfig;
  }
  
  return personaSEOConfig[persona] || baseSEOConfig;
}
