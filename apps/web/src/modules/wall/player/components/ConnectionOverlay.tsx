import { Loader2, Wifi, WifiOff } from 'lucide-react';

import type { WallConnectionStatus } from '../types';

interface ConnectionOverlayProps {
  connectionStatus: WallConnectionStatus;
  isSyncing: boolean;
  lastSyncAt?: string | null;
}

const RECENT_SYNC_GRACE_MS = 90_000;

function isRecentSync(lastSyncAt?: string | null): boolean {
  if (!lastSyncAt) {
    return false;
  }

  const timestamp = Date.parse(lastSyncAt);

  if (!Number.isFinite(timestamp)) {
    return false;
  }

  return Date.now() - timestamp <= RECENT_SYNC_GRACE_MS;
}

export function shouldShowConnectionOverlay(
  connectionStatus: WallConnectionStatus,
  isSyncing: boolean,
  lastSyncAt?: string | null,
): boolean {
  if (connectionStatus === 'connected' && !isSyncing) {
    return false;
  }

  if (isSyncing || connectionStatus === 'connecting' || connectionStatus === 'reconnecting') {
    return true;
  }

  if ((connectionStatus === 'disconnected' || connectionStatus === 'error') && isRecentSync(lastSyncAt)) {
    return false;
  }

  return connectionStatus === 'disconnected' || connectionStatus === 'error';
}

function resolveLabel(
  connectionStatus: WallConnectionStatus,
  isSyncing: boolean,
): string {
  if (connectionStatus === 'connecting') return 'Conectando...';
  if (connectionStatus === 'reconnecting') return 'Reconectando...';
  if (connectionStatus === 'disconnected') return 'Desconectado';
  if (connectionStatus === 'error') return 'Erro de conexao';
  if (isSyncing) return 'Sincronizando...';
  return '';
}

export function ConnectionOverlay({
  connectionStatus,
  isSyncing,
  lastSyncAt,
}: ConnectionOverlayProps) {
  if (!shouldShowConnectionOverlay(connectionStatus, isSyncing, lastSyncAt)) {
    return null;
  }

  const icon =
    connectionStatus === 'error' || connectionStatus === 'disconnected'
      ? <WifiOff className="h-3.5 w-3.5" />
      : isSyncing || connectionStatus === 'connecting' || connectionStatus === 'reconnecting'
        ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
        : <Wifi className="h-3.5 w-3.5" />;

  const colorClass =
    connectionStatus === 'error' || connectionStatus === 'disconnected'
      ? 'border-red-400/30 bg-red-500/15 text-red-200'
      : 'border-amber-400/30 bg-amber-500/15 text-amber-100';

  return (
    <div className="pointer-events-none absolute inset-x-0 top-[max(16px,2vh)] z-40 flex justify-center">
      <div className={`flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-medium uppercase tracking-[0.2em] shadow-lg backdrop-blur-xl ${colorClass}`}>
        {icon}
        {resolveLabel(connectionStatus, isSyncing)}
      </div>
    </div>
  );
}

export default ConnectionOverlay;
