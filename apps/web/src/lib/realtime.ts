const realtimeWarnings = new Set<string>();

export interface ReverbRuntimeConfig {
  key: string;
  host: string;
  port: string;
  scheme: string;
}

export function normalizeRealtimeScheme(value: string): 'http' | 'https' {
  return value === 'https' || value === 'https:' ? 'https' : 'http';
}

export function isRealtimeTlsAligned(pageProtocol: string, reverbScheme: string): boolean {
  const pageScheme = normalizeRealtimeScheme(pageProtocol);
  const configuredScheme = normalizeRealtimeScheme(reverbScheme);

  return pageScheme === configuredScheme;
}

export function shouldDisableRealtimeInDev(scope: string, config: ReverbRuntimeConfig): boolean {
  if (!import.meta.env.DEV) {
    return false;
  }

  if (typeof window === 'undefined' || !config.key || !config.host) {
    return false;
  }

  if (isRealtimeTlsAligned(window.location.protocol, config.scheme)) {
    return false;
  }

  const warningKey = `${scope}:${window.location.protocol}:${config.scheme}:${config.host}:${config.port}`;

  if (!realtimeWarnings.has(warningKey)) {
    realtimeWarnings.add(warningKey);

    console.warn(
      `[${scope}] Realtime desabilitado em desenvolvimento: pagina em ${window.location.protocol.replace(':', '').toUpperCase()} e Reverb configurado para ${normalizeRealtimeScheme(config.scheme).toUpperCase()} em ${config.host}:${config.port}. Alinhe o protocolo da pagina com VITE_REVERB_SCHEME.`,
    );
  }

  return true;
}
