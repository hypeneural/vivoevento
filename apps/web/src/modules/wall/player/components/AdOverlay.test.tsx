import { act, fireEvent, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { AD_VIDEO_SAFETY_TIMEOUT_MS } from '../engine/autoplay';
import { AdOverlay } from './AdOverlay';

describe('AdOverlay', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('finishes image ads after duration_seconds', () => {
    const onFinished = vi.fn();

    render(
      <AdOverlay
        ad={{
          id: 1,
          url: 'https://cdn.example.com/ad-1.jpg',
          media_type: 'image',
          duration_seconds: 3,
          position: 0,
        }}
        onFinished={onFinished}
      />,
    );

    act(() => {
      vi.advanceTimersByTime(3000);
    });

    expect(onFinished).toHaveBeenCalledTimes(1);
  });

  it('finishes video ads when the video ends', () => {
    const onFinished = vi.fn();
    const { container } = render(
      <AdOverlay
        ad={{
          id: 2,
          url: 'https://cdn.example.com/ad-2.mp4',
          media_type: 'video',
          duration_seconds: 0,
          position: 0,
        }}
        onFinished={onFinished}
      />,
    );

    const video = container.querySelector('video');

    expect(video).not.toBeNull();

    fireEvent.ended(video as HTMLVideoElement);

    expect(onFinished).toHaveBeenCalledTimes(1);
  });

  it('forces finish for stalled video ads after the safety timeout', () => {
    const onFinished = vi.fn();

    render(
      <AdOverlay
        ad={{
          id: 3,
          url: 'https://cdn.example.com/ad-3.mp4',
          media_type: 'video',
          duration_seconds: 0,
          position: 0,
        }}
        onFinished={onFinished}
      />,
    );

    act(() => {
      vi.advanceTimersByTime(AD_VIDEO_SAFETY_TIMEOUT_MS);
    });

    expect(onFinished).toHaveBeenCalledTimes(1);
  });
});
