import type { ApiEventPackage } from '@/lib/api-types';

export type CommercialPackageCopy = {
  id: number;
  code: string;
  name: string;
  subtitle: string;
  idealFor: string;
  benefits: string[];
  recommended: boolean;
  badgeLabel: string | null;
  deepLinkKey: string;
  priceLabel: string;
  raw: ApiEventPackage;
};

function formatMoney(amountCents?: number | null, currency = 'BRL') {
  if (typeof amountCents !== 'number') {
    return 'Sob consulta';
  }

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency,
  }).format(amountCents / 100);
}

function pickIdealFor(pkg: ApiEventPackage) {
  if (pkg.modules.play) {
    return 'Eventos que querem experiencia mais interativa para os convidados.';
  }

  if (pkg.modules.wall && pkg.modules.hub) {
    return 'Casamentos, aniversarios e eventos sociais que querem compra rapida e experiencia completa.';
  }

  return 'Eventos que querem uma contratacao simples e segura.';
}

function buildBenefits(pkg: ApiEventPackage) {
  const benefits: string[] = [];

  if (pkg.modules.wall) {
    benefits.push('Telao ao vivo para os convidados');
  }

  if (pkg.modules.hub) {
    benefits.push('Pagina do evento pronta para compartilhar');
  }

  if (pkg.modules.play) {
    benefits.push('Experiencias interativas para engajar o publico');
  }

  if (pkg.limits.max_photos) {
    benefits.push(`Ate ${pkg.limits.max_photos} fotos no evento`);
  }

  if (pkg.limits.retention_days) {
    benefits.push(`Memorias disponiveis por ${pkg.limits.retention_days} dias`);
  }

  return benefits.slice(0, 5);
}

export function mapPackageToCommercialCard(pkg: ApiEventPackage, index = 0): CommercialPackageCopy {
  const amountCents = pkg.default_price?.amount_cents ?? null;
  const currency = pkg.default_price?.currency ?? 'BRL';
  const checkoutMarketing = pkg.checkout_marketing ?? null;
  const recommended = checkoutMarketing?.recommended ?? index === 0;
  const badgeLabel = checkoutMarketing?.badge ?? (recommended ? 'Mais escolhido' : null);

  return {
    id: pkg.id,
    code: pkg.code,
    name: pkg.name,
    subtitle: checkoutMarketing?.subtitle ?? pkg.description ?? 'Pacote pensado para uma compra rapida e sem complicacao.',
    idealFor: checkoutMarketing?.ideal_for ?? pickIdealFor(pkg),
    benefits: checkoutMarketing?.benefits?.length ? checkoutMarketing.benefits : buildBenefits(pkg),
    recommended,
    badgeLabel,
    deepLinkKey: checkoutMarketing?.slug ?? pkg.code,
    priceLabel: formatMoney(amountCents, currency),
    raw: pkg,
  };
}

function normalizeSelectionKey(value: string) {
  return value.trim().toLowerCase();
}

export function findCommercialPackageBySelectionKey(
  packages: CommercialPackageCopy[],
  selectionKey?: string | null,
) {
  if (!selectionKey?.trim()) {
    return null;
  }

  const normalizedSelectionKey = normalizeSelectionKey(selectionKey);

  return packages.find((pkg) => (
    normalizeSelectionKey(pkg.deepLinkKey) === normalizedSelectionKey
    || normalizeSelectionKey(pkg.code) === normalizedSelectionKey
    || String(pkg.id) === selectionKey.trim()
  )) ?? null;
}
