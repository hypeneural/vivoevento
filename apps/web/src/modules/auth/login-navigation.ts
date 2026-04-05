export function resolveLoginReturnPath(search: string, fallback = '/') {
  const params = new URLSearchParams(search.startsWith('?') ? search.slice(1) : search);
  const returnTo = params.get('returnTo');

  if (!returnTo || !returnTo.startsWith('/') || returnTo.startsWith('//')) {
    return fallback;
  }

  return returnTo;
}
