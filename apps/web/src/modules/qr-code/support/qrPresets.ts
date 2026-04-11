import type { QrLinkKey, QrSkinPreset, QrUsagePreset } from './qrTypes';

export const QR_USAGE_PRESET_METADATA: Record<QrUsagePreset, { label: string; description: string }> = {
  telao: {
    label: 'Telao',
    description: 'Melhor para exibicao a distancia e contraste maximo.',
  },
  upload_rapido: {
    label: 'Upload rapido',
    description: 'Melhor para apontar o celular e enviar rapido.',
  },
  galeria_premium: {
    label: 'Galeria premium',
    description: 'Melhor para galeria com branding e acabamento refinado.',
  },
  impresso_pequeno: {
    label: 'Impresso pequeno',
    description: 'Melhor para materiais pequenos com robustez alta.',
  },
  convite_whatsapp: {
    label: 'Convite / WhatsApp',
    description: 'Melhor para compartilhamento em tela pequena.',
  },
};

export const QR_SKIN_PRESET_METADATA: Record<QrSkinPreset, { label: string; description: string }> = {
  classico: {
    label: 'Classico',
    description: 'Padrao limpo com contraste alto.',
  },
  premium: {
    label: 'Premium',
    description: 'Acabamento de marca com olhos mais elaborados.',
  },
  minimalista: {
    label: 'Minimalista',
    description: 'Visual seco e sem excesso de ornamento.',
  },
  escuro: {
    label: 'Escuro',
    description: 'Visual escuro no entorno, sem comprometer o miolo do QR.',
  },
};

const DEFAULT_USAGE_PRESET_BY_LINK_KEY: Record<QrLinkKey, QrUsagePreset> = {
  gallery: 'galeria_premium',
  upload: 'upload_rapido',
  wall: 'telao',
  hub: 'galeria_premium',
  play: 'convite_whatsapp',
  find_me: 'convite_whatsapp',
};

export function getDefaultUsagePresetForLinkKey(linkKey: QrLinkKey): QrUsagePreset {
  return DEFAULT_USAGE_PRESET_BY_LINK_KEY[linkKey];
}
