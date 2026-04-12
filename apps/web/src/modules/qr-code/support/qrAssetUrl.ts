import { resolveAssetUrl } from '@/lib/assets';

function rewriteStorageUrlToCurrentOrigin(url: string): string {
  try {
    const parsed = new URL(url);

    if (parsed.pathname.startsWith('/storage/')) {
      return new URL(`${parsed.pathname}${parsed.search}`, window.location.origin).toString();
    }
  } catch {
    return url;
  }

  return url;
}

export function resolveQrAssetUrl(assetPath?: string | null, assetUrl?: string | null) {
  if (assetPath) {
    return resolveAssetUrl(assetPath);
  }

  if (!assetUrl) {
    return null;
  }

  if (/^https?:\/\//i.test(assetUrl)) {
    return rewriteStorageUrlToCurrentOrigin(assetUrl);
  }

  return resolveAssetUrl(assetUrl);
}
