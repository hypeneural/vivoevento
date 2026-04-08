/**
 * SEO Types and Interfaces
 * 
 * Defines types for meta tags, structured data, and SEO configuration
 * per persona variation.
 */

import type { PersonaId } from '../contexts/PersonaContext';

export type MetaTags = {
  title: string;
  description: string;
  keywords?: string;
  author?: string;
  canonical?: string;
};

export type OpenGraphTags = {
  title: string;
  description: string;
  image: string;
  url: string;
  type?: string;
  locale?: string;
  siteName?: string;
};

export type TwitterCardTags = {
  card: 'summary' | 'summary_large_image' | 'app' | 'player';
  title: string;
  description: string;
  image: string;
  site?: string;
  creator?: string;
};

export type StructuredDataOrganization = {
  '@context': 'https://schema.org';
  '@type': 'Organization';
  name: string;
  url: string;
  logo: string;
  description: string;
  contactPoint?: {
    '@type': 'ContactPoint';
    telephone: string;
    contactType: string;
    availableLanguage: string[];
  };
  sameAs?: string[];
};

export type StructuredDataWebPage = {
  '@context': 'https://schema.org';
  '@type': 'WebPage';
  name: string;
  description: string;
  url: string;
  inLanguage: string;
  isPartOf: {
    '@type': 'WebSite';
    name: string;
    url: string;
  };
};

export type SEOConfig = {
  meta: MetaTags;
  openGraph: OpenGraphTags;
  twitter: TwitterCardTags;
  structuredData: {
    organization: StructuredDataOrganization;
    webPage: StructuredDataWebPage;
  };
};

export type PersonaSEOConfig = Record<PersonaId, SEOConfig>;
