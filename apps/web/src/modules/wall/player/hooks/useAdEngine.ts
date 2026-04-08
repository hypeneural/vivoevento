/**
 * useAdEngine — Manages ad playback within the wall player.
 *
 * This is a standalone hook that sits alongside the main engine,
 * rather than modifying the core reducer. This keeps the ad logic
 * isolated and testable.
 *
 * Features:
 * - Tracks photo advances from the main engine
 * - Decides when to show an ad (by_photos or by_minutes)
 * - Round-robins through available ads
 * - Auto-finishes after duration (image) or on video end
 * - Anti-loop: prevents back-to-back ads
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import type { WallAdItem, WallAdMode } from '../types';
import {
  type AdSchedulerState,
  createAdSchedulerState,
  shouldPlayAd,
  markPhotoAdvanced,
  markAdPlayed,
  selectNextAd,
  updateAdSchedulerMode,
} from '../engine/adScheduler';

interface UseAdEngineOptions {
  mode: WallAdMode;
  frequency: number;
  intervalMinutes: number;
  ads: WallAdItem[];
  /** Current item id from the main engine — changes trigger photo advance */
  currentItemId: string | null | undefined;
  /** Only run when the player is playing */
  isPlaying: boolean;
}

interface AdEngineResult {
  /** Current ad to display, or null if showing slideshow */
  currentAd: WallAdItem | null;
  /** Call when the ad overlay finishes playing */
  onAdFinished: () => void;
  /** Update ads list (e.g. from realtime broadcast) */
  updateAds: (ads: WallAdItem[]) => void;
}

export function useAdEngine({
  mode,
  frequency,
  intervalMinutes,
  ads: initialAds,
  currentItemId,
  isPlaying,
}: UseAdEngineOptions): AdEngineResult {
  const [currentAd, setCurrentAd] = useState<WallAdItem | null>(null);
  const [ads, setAds] = useState<WallAdItem[]>(initialAds);
  const schedulerRef = useRef<AdSchedulerState>(
    createAdSchedulerState(mode, mode === 'by_minutes' ? intervalMinutes : frequency),
  );
  const lastItemIdRef = useRef<string | null | undefined>(currentItemId);

  // Update scheduler when mode/frequency changes
  useEffect(() => {
    schedulerRef.current = updateAdSchedulerMode(
      schedulerRef.current,
      mode,
      mode === 'by_minutes' ? intervalMinutes : frequency,
    );
  }, [mode, frequency, intervalMinutes]);

  // Sync external ads — compare by ID list to avoid infinite re-render from new array refs
  const adsKey = initialAds.map((a) => a.id).join(',');
  useEffect(() => {
    setAds(initialAds);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [adsKey]);

  // Track photo advances from the main engine
  useEffect(() => {
    if (!isPlaying || currentAd) return;
    if (currentItemId === lastItemIdRef.current) return;

    lastItemIdRef.current = currentItemId;

    // Mark a photo advance
    schedulerRef.current = markPhotoAdvanced(schedulerRef.current);

    // Check if we should play an ad
    if (shouldPlayAd(schedulerRef.current, ads.length)) {
      const { ad, nextIndex } = selectNextAd(ads, schedulerRef.current.lastAdIndex);
      if (ad) {
        schedulerRef.current = {
          ...schedulerRef.current,
          lastAdIndex: nextIndex,
        };
        setCurrentAd(ad);
      }
    }
  }, [currentItemId, isPlaying, currentAd, ads]);

  // Minutes mode: check on interval
  useEffect(() => {
    if (mode !== 'by_minutes' || !isPlaying || currentAd || ads.length === 0) return;

    const checkInterval = setInterval(() => {
      if (shouldPlayAd(schedulerRef.current, ads.length)) {
        const { ad, nextIndex } = selectNextAd(ads, schedulerRef.current.lastAdIndex);
        if (ad) {
          schedulerRef.current = {
            ...schedulerRef.current,
            lastAdIndex: nextIndex,
          };
          setCurrentAd(ad);
        }
      }
    }, 30_000); // Check every 30s

    return () => clearInterval(checkInterval);
  }, [mode, isPlaying, currentAd, ads]);

  const onAdFinished = useCallback(() => {
    schedulerRef.current = markAdPlayed(schedulerRef.current);
    setCurrentAd(null);
  }, []);

  const updateAds = useCallback((newAds: WallAdItem[]) => {
    setAds(newAds);
  }, []);

  return {
    currentAd,
    onAdFinished,
    updateAds,
  };
}
