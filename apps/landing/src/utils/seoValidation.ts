/**
 * SEO Validation Utilities
 * 
 * Utilities for validating and testing SEO implementation.
 * Use these in development to ensure SEO tags are correct.
 */

import type { SEOConfig } from '../types/seo';

/**
 * Validate SEO configuration
 * Checks for required fields and common issues
 */
export function validateSEOConfig(config: SEOConfig): {
  valid: boolean;
  errors: string[];
  warnings: string[];
} {
  const errors: string[] = [];
  const warnings: string[] = [];
  
  // Meta tags validation
  if (!config.meta.title) {
    errors.push('Meta title is required');
  } else if (config.meta.title.length > 60) {
    warnings.push(`Meta title is ${config.meta.title.length} characters (recommended: 50-60)`);
  }
  
  if (!config.meta.description) {
    errors.push('Meta description is required');
  } else if (config.meta.description.length > 160) {
    warnings.push(`Meta description is ${config.meta.description.length} characters (recommended: 150-160)`);
  }
  
  if (!config.meta.canonical) {
    warnings.push('Canonical URL is recommended');
  }
  
  // Open Graph validation
  if (!config.openGraph.title) {
    errors.push('OG title is required');
  }
  
  if (!config.openGraph.description) {
    errors.push('OG description is required');
  }
  
  if (!config.openGraph.image) {
    errors.push('OG image is required');
  } else if (!config.openGraph.image.startsWith('http')) {
    warnings.push('OG image should be an absolute URL');
  }
  
  if (!config.openGraph.url) {
    errors.push('OG URL is required');
  }
  
  // Twitter Card validation
  if (!config.twitter.card) {
    errors.push('Twitter card type is required');
  }
  
  if (!config.twitter.image) {
    errors.push('Twitter image is required');
  }
  
  // Structured data validation
  if (!config.structuredData.organization['@context']) {
    errors.push('Organization structured data @context is required');
  }
  
  if (!config.structuredData.organization.name) {
    errors.push('Organization name is required');
  }
  
  if (!config.structuredData.webPage['@context']) {
    errors.push('WebPage structured data @context is required');
  }
  
  return {
    valid: errors.length === 0,
    errors,
    warnings,
  };
}

/**
 * Log SEO configuration to console (development only)
 */
export function logSEOConfig(config: SEOConfig, persona: string | null = null) {
  if (import.meta.env.DEV) {
    console.group(`🔍 SEO Configuration${persona ? ` (${persona})` : ''}`);
    console.log('Meta:', config.meta);
    console.log('Open Graph:', config.openGraph);
    console.log('Twitter:', config.twitter);
    console.log('Structured Data:', config.structuredData);
    
    const validation = validateSEOConfig(config);
    if (!validation.valid) {
      console.error('❌ Validation Errors:', validation.errors);
    }
    if (validation.warnings.length > 0) {
      console.warn('⚠️ Warnings:', validation.warnings);
    }
    if (validation.valid && validation.warnings.length === 0) {
      console.log('✅ SEO configuration is valid');
    }
    console.groupEnd();
  }
}

/**
 * Get current meta tags from DOM (for debugging)
 */
export function getCurrentMetaTags() {
  const metaTags: Record<string, string> = {};
  
  // Get all meta tags
  document.querySelectorAll('meta').forEach((meta) => {
    const name = meta.getAttribute('name') || meta.getAttribute('property');
    const content = meta.getAttribute('content');
    if (name && content) {
      metaTags[name] = content;
    }
  });
  
  // Get title
  metaTags.title = document.title;
  
  // Get canonical
  const canonical = document.querySelector('link[rel="canonical"]');
  if (canonical) {
    metaTags.canonical = canonical.getAttribute('href') || '';
  }
  
  return metaTags;
}

/**
 * Test structured data with Google Rich Results Test
 * Opens the test tool in a new window
 */
export function testStructuredData(url: string = window.location.href) {
  const testUrl = `https://search.google.com/test/rich-results?url=${encodeURIComponent(url)}`;
  window.open(testUrl, '_blank');
}

/**
 * Test Open Graph preview
 * Opens Facebook debugger in a new window
 */
export function testOpenGraph(url: string = window.location.href) {
  const debugUrl = `https://developers.facebook.com/tools/debug/?q=${encodeURIComponent(url)}`;
  window.open(debugUrl, '_blank');
}

/**
 * Test Twitter Card preview
 * Opens Twitter Card validator in a new window
 */
export function testTwitterCard(url: string = window.location.href) {
  const validatorUrl = `https://cards-dev.twitter.com/validator?url=${encodeURIComponent(url)}`;
  window.open(validatorUrl, '_blank');
}
