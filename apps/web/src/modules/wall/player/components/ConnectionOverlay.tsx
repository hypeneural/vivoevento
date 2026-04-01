/**
 * ConnectionOverlay — Visual indicator for connection issues.
 * Only visible when connection is not healthy.
 */

import { Wifi, WifiOff, Loader2 } from 'lucide-react';
import type { WallConnectionStatus } from '../types';

interface ConnectionOverlayProps {
  connectionStatus: WallConnectionStatus;
  isSyncing: boolean;
}

export function ConnectionOverlay({ connectionStatus, isSyncing }: ConnectionOverlayProps) {
  const isHealthy = connectionStatus === 'connected' && !isSyncing;
  if (isHealthy) return null;

  const icon =
    connectionStatus === 'error' || connectionStatus === 'disconnected'
      ? <WifiOff className="h-3.5 w-3.5" />
      : isSyncing || connectionStatus === 'connecting' || connectionStatus === 'reconnecting'
        ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
        : <Wifi className="h-3.5 w-3.5" />;

  const label =
    connectionStatus === 'connecting' ? 'Conectando...'
    : connectionStatus === 'reconnecting' ? 'Reconectando...'
    : connectionStatus === 'disconnected' ? 'Desconectado'
    : connectionStatus === 'error' ? 'Erro de conexão'
    : isSyncing ? 'Sincronizando...'
    : '';

  const colorClass =
    connectionStatus === 'error' || connectionStatus === 'disconnected'
      ? 'border-red-400/30 bg-red-500/15 text-red-200'
      : 'border-amber-400/30 bg-amber-500/15 text-amber-100';

  return (
    <div className="pointer-events-none absolute inset-x-0 top-[max(16px,2vh)] z-40 flex justify-center">
      <div className={`flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-medium uppercase tracking-[0.2em] shadow-lg backdrop-blur-xl ${colorClass}`}>
        {icon}
        {label}
      </div>
    </div>
  );
}

export default ConnectionOverlay;
