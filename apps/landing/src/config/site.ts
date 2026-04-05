const DEFAULT_WHATSAPP_NUMBER = "48996553954";
const DEFAULT_WHATSAPP_MESSAGE = "Ola, vim pelo site do Evento Vivo e quero saber mais";
const DEFAULT_INSTAGRAM_URL = "https://instagram.com/eventovivo";

const env = import.meta.env;

function normalizeWhatsAppNumber(value: string) {
  const digits = value.replace(/\D/g, "");

  if (digits.length === 11) {
    return `55${digits}`;
  }

  return digits;
}

function formatWhatsAppNumber(value: string) {
  const localNumber = value.startsWith("55") && value.length === 13 ? value.slice(2) : value;

  if (localNumber.length === 11) {
    return `(${localNumber.slice(0, 2)}) ${localNumber.slice(2, 7)}-${localNumber.slice(7)}`;
  }

  return value;
}

const siteUrl = (env.VITE_PUBLIC_SITE_URL || "https://eventovivo.com.br").replace(/\/$/, "");
const adminUrl = env.VITE_ADMIN_URL || "https://admin.eventovivo.com.br";
const whatsappNumber = normalizeWhatsAppNumber(env.VITE_WHATSAPP_NUMBER || DEFAULT_WHATSAPP_NUMBER);
const whatsappMessage = env.VITE_WHATSAPP_MESSAGE || DEFAULT_WHATSAPP_MESSAGE;
const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappMessage)}`;
const instagramUrl = env.VITE_INSTAGRAM_URL || DEFAULT_INSTAGRAM_URL;

export const siteConfig = {
  siteName: "Evento Vivo",
  siteUrl,
  adminUrl,
  whatsappNumber,
  whatsappDisplay: formatWhatsAppNumber(whatsappNumber),
  whatsappUrl,
  primaryCtaUrl: env.VITE_PRIMARY_CTA_URL || whatsappUrl,
  instagramUrl,
  instagramHandle: "@eventovivo",
  linkedinUrl: env.VITE_LINKEDIN_URL || "",
};

export function isExternalUrl(url: string) {
  return /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(url);
}

export function getLinkProps(url: string) {
  return isExternalUrl(url)
    ? {
        target: "_blank" as const,
        rel: "noreferrer",
      }
    : {};
}
