/**
 * NewPhotoToast — Toast notification when new media arrives via WebSocket.
 *
 * Features:
 * - Single photo: "📸 Maria enviou uma foto!"
 * - Batch (N photos in 5s): "📸 3 novas fotos!"
 * - Auto-dismiss after 5 seconds
 * - Respects reduced-motion (no slide animation)
 * - Queue-safe: new arrivals reset the dismiss timer
 *
 * Inspired by MomentLoop's live-arrival feedback.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';

const BATCH_WINDOW_MS = 5_000;
const DISMISS_TIMEOUT_MS = 5_000;

interface ToastEntry {
  senderName: string;
  timestamp: number;
}

interface NewPhotoToastProps {
  reducedMotion?: boolean;
}

/**
 * Hook: manages the toast queue for new photo arrivals.
 */
export function useNewPhotoToast() {
  const [entries, setEntries] = useState<ToastEntry[]>([]);
  const [visible, setVisible] = useState(false);
  const dismissTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const trigger = useCallback((senderName: string) => {
    const now = Date.now();

    setEntries((prev) => {
      // Keep only entries within the batch window
      const recent = prev.filter((e) => now - e.timestamp < BATCH_WINDOW_MS);
      return [...recent, { senderName, timestamp: now }];
    });

    setVisible(true);

    // Reset dismiss timer
    if (dismissTimerRef.current) {
      clearTimeout(dismissTimerRef.current);
    }
    dismissTimerRef.current = setTimeout(() => {
      setVisible(false);
      // Clear entries after fade-out
      setTimeout(() => setEntries([]), 400);
    }, DISMISS_TIMEOUT_MS);
  }, []);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (dismissTimerRef.current) {
        clearTimeout(dismissTimerRef.current);
      }
    };
  }, []);

  const message = resolveToastMessage(entries);

  return { visible, message, trigger };
}

function resolveToastMessage(entries: ToastEntry[]): string {
  if (entries.length === 0) return '';
  if (entries.length === 1) return `📸 ${entries[0].senderName} enviou uma foto!`;
  return `📸 ${entries.length} novas fotos!`;
}

/**
 * Component: renders the toast notification.
 */
export function NewPhotoToast({
  visible,
  message,
  reducedMotion = false,
}: NewPhotoToastProps & { visible: boolean; message: string }) {
  if (!message) return null;

  const variants = reducedMotion
    ? {
        initial: { opacity: 1 },
        animate: { opacity: 1 },
        exit: { opacity: 0 },
      }
    : {
        initial: { opacity: 0, y: 24, scale: 0.95 },
        animate: { opacity: 1, y: 0, scale: 1 },
        exit: { opacity: 0, y: 12, scale: 0.97 },
      };

  return (
    <div className="pointer-events-none absolute bottom-[max(24px,3vh)] left-[max(24px,3vw)] z-30">
      <AnimatePresence>
        {visible ? (
          <motion.div
            key="new-photo-toast"
            initial={variants.initial}
            animate={variants.animate}
            exit={variants.exit}
            transition={{ duration: reducedMotion ? 0 : 0.35, ease: 'easeOut' }}
            className="pointer-events-auto rounded-2xl border border-white/15 bg-black/50 px-5 py-3 shadow-[0_16px_50px_rgba(0,0,0,0.35)] backdrop-blur-xl"
          >
            <p className="text-sm font-medium text-white/90">{message}</p>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  );
}

export default NewPhotoToast;
