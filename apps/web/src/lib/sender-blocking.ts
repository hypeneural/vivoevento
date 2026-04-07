export function resolveSenderBlockExpiration(selection: string): string | null {
  const base = new Date();

  if (selection === 'forever') {
    return null;
  }

  if (selection === '24h') {
    base.setHours(base.getHours() + 24);
    return base.toISOString();
  }

  if (selection === '30d') {
    base.setDate(base.getDate() + 30);
    return base.toISOString();
  }

  base.setDate(base.getDate() + 7);

  return base.toISOString();
}
