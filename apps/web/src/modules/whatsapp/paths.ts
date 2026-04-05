export const WHATSAPP_SETTINGS_PATH = '/settings/whatsapp';

export function buildWhatsAppInstancePath(instanceId: number | string) {
  return `${WHATSAPP_SETTINGS_PATH}/${instanceId}`;
}
