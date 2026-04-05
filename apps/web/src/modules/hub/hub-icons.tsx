import type { LucideIcon } from 'lucide-react';
import {
  CalendarDays,
  Camera,
  Gamepad2,
  Gift,
  Image,
  Instagram,
  Link2,
  MapPin,
  MessageCircle,
  Monitor,
  Music4,
  Sparkles,
  Ticket,
} from 'lucide-react';

import type { HubButtonIconKey } from '@/lib/api-types';

export const HUB_ICON_MAP: Record<HubButtonIconKey, LucideIcon> = {
  camera: Camera,
  image: Image,
  monitor: Monitor,
  gamepad: Gamepad2,
  link: Link2,
  calendar: CalendarDays,
  'map-pin': MapPin,
  ticket: Ticket,
  music: Music4,
  gift: Gift,
  sparkles: Sparkles,
  'message-circle': MessageCircle,
  instagram: Instagram,
};

export function getHubIcon(icon: HubButtonIconKey): LucideIcon {
  return HUB_ICON_MAP[icon] ?? Link2;
}
