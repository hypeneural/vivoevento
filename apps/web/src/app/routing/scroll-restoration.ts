import type { Location } from 'react-router-dom';

const MODERATION_SCROLL_PARAMS = [
  'event_id',
  'search',
  'status',
  'featured',
  'pinned',
  'sender_blocked',
  'orientation',
  'per_page',
] as const;

export function buildScrollRestorationKey(location: Location) {
  if (location.pathname !== '/moderation') {
    return location.key;
  }

  const incoming = new URLSearchParams(location.search);
  const stable = new URLSearchParams();

  for (const key of MODERATION_SCROLL_PARAMS) {
    const value = incoming.get(key);

    if (value && value !== 'all') {
      stable.set(key, value);
    }
  }

  const query = stable.toString();

  return query ? `${location.pathname}?${query}` : location.pathname;
}
