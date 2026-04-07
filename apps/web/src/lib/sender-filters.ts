export interface SenderScopedPrefill {
  eventId: string;
  search: string;
}

export function buildSenderScopedPath(
  basePath: string,
  eventId: number | string,
  search: string,
): string {
  const params = new URLSearchParams();
  params.set('event_id', String(eventId));
  params.set('search', search);

  return `${basePath}?${params.toString()}`;
}

export function readSenderScopedPrefill(searchParams: URLSearchParams): SenderScopedPrefill {
  return {
    eventId: searchParams.get('event_id') ?? 'all',
    search: searchParams.get('search') ?? '',
  };
}
