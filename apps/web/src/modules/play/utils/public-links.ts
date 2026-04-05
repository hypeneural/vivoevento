function getPublicOrigin() {
  if (typeof window === 'undefined') {
    return '';
  }

  return window.location.origin;
}

export function buildEventPlayHubUrl(eventSlug: string) {
  const path = `/e/${eventSlug}/play`;
  const origin = getPublicOrigin();

  return origin ? `${origin}${path}` : path;
}

export function buildEventPlayGameUrl(eventSlug: string, gameSlug: string) {
  const path = `/e/${eventSlug}/play/${gameSlug}`;
  const origin = getPublicOrigin();

  return origin ? `${origin}${path}` : path;
}

export async function copyTextToClipboard(value: string) {
  if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value);
    return true;
  }

  if (typeof document === 'undefined') {
    return false;
  }

  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'absolute';
  textarea.style.left = '-9999px';
  document.body.appendChild(textarea);
  textarea.select();

  const copied = document.execCommand('copy');
  document.body.removeChild(textarea);

  return copied;
}
