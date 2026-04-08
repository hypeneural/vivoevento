/**
 * SEO Validation Script
 * 
 * Automated validation of SEO configuration for all persona variations.
 * Run with: npx tsx scripts/validate-seo.ts
 */

import { baseSEOConfig, personaSEOConfig, getSEOConfig } from '../src/config/seo';
import { validateSEOConfig } from '../src/utils/seoValidation';
import type { SEOConfig } from '../src/types/seo';

// ANSI color codes for terminal output
const colors = {
  reset: '\x1b[0m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  red: '\x1b[31m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
};

function log(message: string, color: keyof typeof colors = 'reset') {
  console.log(`${colors[color]}${message}${colors.reset}`);
}

function logSection(title: string) {
  console.log('\n' + '='.repeat(60));
  log(title, 'cyan');
  console.log('='.repeat(60));
}

function validatePersona(persona: string | null, config: SEOConfig) {
  const personaLabel = persona || 'Base (No Persona)';
  logSection(`Validating: ${personaLabel}`);
  
  const validation = validateSEOConfig(config);
  
  // Meta tags
  console.log('\n📄 Meta Tags:');
  console.log(`  Title: ${config.meta.title}`);
  console.log(`  Length: ${config.meta.title.length} chars ${config.meta.title.length > 60 ? '⚠️' : '✅'}`);
  console.log(`  Description: ${config.meta.description.substring(0, 80)}...`);
  console.log(`  Length: ${config.meta.description.length} chars ${config.meta.description.length > 160 ? '⚠️' : '✅'}`);
  console.log(`  Canonical: ${config.meta.canonical}`);
  
  // Open Graph
  console.log('\n🌐 Open Graph:');
  console.log(`  Title: ${config.openGraph.title}`);
  console.log(`  Image: ${config.openGraph.image}`);
  console.log(`  URL: ${config.openGraph.url}`);
  
  // Twitter
  console.log('\n🐦 Twitter Card:');
  console.log(`  Card Type: ${config.twitter.card}`);
  console.log(`  Title: ${config.twitter.title}`);
  console.log(`  Image: ${config.twitter.image}`);
  
  // Structured Data
  console.log('\n📊 Structured Data:');
  console.log(`  Organization: ${config.structuredData.organization.name}`);
  console.log(`  WebPage: ${config.structuredData.webPage.name}`);
  
  // Validation Results
  console.log('\n✓ Validation Results:');
  if (validation.valid) {
    log('  ✅ Configuration is valid', 'green');
  } else {
    log('  ❌ Configuration has errors', 'red');
  }
  
  if (validation.errors.length > 0) {
    log('\n  Errors:', 'red');
    validation.errors.forEach(error => {
      log(`    • ${error}`, 'red');
    });
  }
  
  if (validation.warnings.length > 0) {
    log('\n  Warnings:', 'yellow');
    validation.warnings.forEach(warning => {
      log(`    • ${warning}`, 'yellow');
    });
  }
  
  return validation;
}

function checkUniqueness() {
  logSection('Checking Uniqueness Across Personas');
  
  const configs = [
    { label: 'Base', config: baseSEOConfig },
    { label: 'Assessora', config: personaSEOConfig.assessora },
    { label: 'Social', config: personaSEOConfig.social },
    { label: 'Corporativo', config: personaSEOConfig.corporativo },
  ];
  
  // Check title uniqueness
  console.log('\n📝 Title Uniqueness:');
  const titles = configs.map(c => c.config.meta.title);
  const uniqueTitles = new Set(titles);
  if (uniqueTitles.size === titles.length) {
    log('  ✅ All titles are unique', 'green');
  } else {
    log('  ❌ Duplicate titles found', 'red');
  }
  configs.forEach(c => {
    console.log(`  ${c.label}: ${c.config.meta.title}`);
  });
  
  // Check description uniqueness
  console.log('\n📝 Description Uniqueness:');
  const descriptions = configs.map(c => c.config.meta.description);
  const uniqueDescriptions = new Set(descriptions);
  if (uniqueDescriptions.size === descriptions.length) {
    log('  ✅ All descriptions are unique', 'green');
  } else {
    log('  ❌ Duplicate descriptions found', 'red');
  }
  
  // Check canonical URL correctness
  console.log('\n🔗 Canonical URLs:');
  configs.forEach(c => {
    const canonical = c.config.meta.canonical;
    const hasPersona = c.label !== 'Base';
    const expectedPersona = c.label.toLowerCase();
    
    if (hasPersona && canonical?.includes(`persona=${expectedPersona}`)) {
      log(`  ✅ ${c.label}: ${canonical}`, 'green');
    } else if (!hasPersona && !canonical?.includes('persona=')) {
      log(`  ✅ ${c.label}: ${canonical}`, 'green');
    } else {
      log(`  ❌ ${c.label}: ${canonical}`, 'red');
    }
  });
  
  // Check image URLs
  console.log('\n🖼️  Image URLs:');
  configs.forEach(c => {
    const ogImage = c.config.openGraph.image;
    const twitterImage = c.config.twitter.image;
    
    if (ogImage.startsWith('http')) {
      log(`  ✅ ${c.label} OG image is absolute URL`, 'green');
    } else {
      log(`  ❌ ${c.label} OG image is not absolute URL`, 'red');
    }
    
    if (twitterImage.startsWith('http')) {
      log(`  ✅ ${c.label} Twitter image is absolute URL`, 'green');
    } else {
      log(`  ❌ ${c.label} Twitter image is not absolute URL`, 'red');
    }
  });
}

function generateSummary(results: Array<{ persona: string | null; validation: ReturnType<typeof validateSEOConfig> }>) {
  logSection('Validation Summary');
  
  const totalErrors = results.reduce((sum, r) => sum + r.validation.errors.length, 0);
  const totalWarnings = results.reduce((sum, r) => sum + r.validation.warnings.length, 0);
  const allValid = results.every(r => r.validation.valid);
  
  console.log('\n📊 Overall Results:');
  console.log(`  Personas Validated: ${results.length}`);
  console.log(`  Total Errors: ${totalErrors}`);
  console.log(`  Total Warnings: ${totalWarnings}`);
  
  if (allValid && totalWarnings === 0) {
    log('\n  ✅ All SEO configurations are valid with no warnings!', 'green');
  } else if (allValid) {
    log('\n  ⚠️  All SEO configurations are valid but have warnings', 'yellow');
  } else {
    log('\n  ❌ Some SEO configurations have errors', 'red');
  }
  
  console.log('\n📋 Next Steps:');
  if (totalErrors > 0) {
    log('  1. Fix validation errors listed above', 'yellow');
    log('  2. Re-run this script to verify fixes', 'yellow');
  } else {
    log('  1. Create preview images (OG + Twitter Card) for all personas', 'yellow');
    log('  2. Create logo.png (512x512px) for structured data', 'yellow');
    log('  3. Deploy to staging environment', 'yellow');
    log('  4. Test with external validators:', 'yellow');
    log('     - Google Rich Results Test', 'yellow');
    log('     - Facebook Debugger', 'yellow');
    log('     - Twitter Card Validator', 'yellow');
    log('     - LinkedIn Post Inspector', 'yellow');
  }
}

// Main execution
function main() {
  log('\n🔍 SEO Configuration Validation', 'blue');
  log('Evento Vivo Landing Page\n', 'blue');
  
  const results = [];
  
  // Validate base config
  results.push({
    persona: null,
    validation: validatePersona(null, baseSEOConfig),
  });
  
  // Validate persona configs
  results.push({
    persona: 'assessora',
    validation: validatePersona('assessora', personaSEOConfig.assessora),
  });
  
  results.push({
    persona: 'social',
    validation: validatePersona('social', personaSEOConfig.social),
  });
  
  results.push({
    persona: 'corporativo',
    validation: validatePersona('corporativo', personaSEOConfig.corporativo),
  });
  
  // Check uniqueness
  checkUniqueness();
  
  // Generate summary
  generateSummary(results);
  
  // Exit with error code if validation failed
  const hasErrors = results.some(r => !r.validation.valid);
  if (hasErrors) {
    process.exit(1);
  }
}

main();
