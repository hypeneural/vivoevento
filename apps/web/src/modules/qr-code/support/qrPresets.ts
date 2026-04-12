import type { QrLinkKey, QrSkinPreset, QrUsagePreset } from './qrTypes';

export const QR_USAGE_PRESET_METADATA: Record<QrUsagePreset, { label: string; description: string; swatchClass: string }> = {
  telao: {
    label: 'Telao',
    description: 'Melhor para exibicao a distancia e contraste maximo.',
    swatchClass: 'bg-slate-900',
  },
  upload_rapido: {
    label: 'Upload rapido',
    description: 'Melhor para apontar o celular e enviar rapido.',
    swatchClass: 'bg-emerald-500',
  },
  galeria_premium: {
    label: 'Galeria premium',
    description: 'Melhor para galeria com branding e acabamento refinado.',
    swatchClass: 'bg-amber-500',
  },
  impresso_pequeno: {
    label: 'Impresso pequeno',
    description: 'Melhor para materiais pequenos com robustez alta.',
    swatchClass: 'bg-slate-700',
  },
  convite_whatsapp: {
    label: 'Convite / WhatsApp',
    description: 'Melhor para compartilhamento em tela pequena.',
    swatchClass: 'bg-green-500',
  },
};

export const QR_SKIN_PRESET_METADATA: Record<QrSkinPreset, { label: string; description: string; swatchClass: string }> = {
  classico: {
    label: 'Classico',
    description: 'Padrao limpo com contraste alto.',
    swatchClass: 'bg-slate-50 border border-slate-200',
  },
  premium: {
    label: 'Premium',
    description: 'Acabamento de marca com olhos mais elaborados.',
    swatchClass: 'bg-amber-500',
  },
  minimalista: {
    label: 'Minimalista',
    description: 'Visual seco e sem excesso de ornamento.',
    swatchClass: 'bg-white border border-slate-200',
  },
  escuro: {
    label: 'Escuro',
    description: 'Visual escuro no entorno, sem comprometer o miolo do QR.',
    swatchClass: 'bg-slate-900',
  },
  luxo_dourado: {
    label: 'Luxo dourado',
    description: 'Mistura dourado e cafe para convite, mesa e material refinado.',
    swatchClass: 'bg-gradient-to-br from-amber-300 to-yellow-700',
  },
  oceano: {
    label: 'Oceano',
    description: 'Azul profundo com detalhe turquesa para marcas frias e tecnicas.',
    swatchClass: 'bg-gradient-to-br from-sky-500 to-teal-500',
  },
  romantico: {
    label: 'Romantico',
    description: 'Tons rosados e formas suaves para casamento e convite.',
    swatchClass: 'bg-gradient-to-br from-rose-300 to-pink-600',
  },
  festa: {
    label: 'Festa',
    description: 'Visual vibrante para aniversario, pista e experiencias jovens.',
    swatchClass: 'bg-gradient-to-br from-violet-700 to-orange-500',
  },
  grafite: {
    label: 'Grafite',
    description: 'Cinza sofisticado para evento corporativo, premium e urbano.',
    swatchClass: 'bg-gradient-to-br from-slate-900 to-slate-400',
  },
  terracota: {
    label: 'Terracota',
    description: 'Tons terrosos e quentes para casamento rustico e mesa posta.',
    swatchClass: 'bg-gradient-to-br from-orange-800 to-amber-400',
  },
  floresta: {
    label: 'Floresta',
    description: 'Verde organico para eventos ao ar livre e identidade natural.',
    swatchClass: 'bg-gradient-to-br from-green-800 to-lime-500',
  },
  lavanda: {
    label: 'Lavanda',
    description: 'Roxo suave e delicado para convite, cha e experiencia leve.',
    swatchClass: 'bg-gradient-to-br from-violet-700 to-fuchsia-300',
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
