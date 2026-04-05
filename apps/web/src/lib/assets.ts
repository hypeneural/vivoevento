export function resolveAssetUrl(path?: string | null) {
  if (!path) return null;
  if (/^https?:\/\//i.test(path)) return path;

  const normalizedPath = path.startsWith('/storage/') || path.startsWith('/api/')
    ? path
    : `/storage/${path.replace(/^\/+/, '')}`;

  return new URL(normalizedPath, window.location.origin).toString();
}
