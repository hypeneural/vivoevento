import type { ComponentProps } from 'react';
import { fireEvent, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { WallVideoSurface } from './WallVideoSurface';

function makeProps(overrides: Partial<ComponentProps<typeof WallVideoSurface>> = {}) {
  return {
    itemId: 'video_1',
    src: 'https://cdn.example.com/video-1.mp4',
    poster: 'https://cdn.example.com/video-1-poster.jpg',
    className: 'h-full w-full object-cover',
    playerStatus: 'playing' as const,
    startupDeadlineMs: 1200,
    stallBudgetMs: 1500,
    resumeMode: 'resume_if_same_item_else_restart' as const,
    onStarting: vi.fn(),
    onFirstFrame: vi.fn(),
    onPlaybackReady: vi.fn(),
    onPlaying: vi.fn(),
    onProgress: vi.fn(),
    onWaiting: vi.fn(),
    onStalled: vi.fn(),
    onEnded: vi.fn(),
    onFailure: vi.fn(),
    ...overrides,
  };
}

describe('WallVideoSurface', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockImplementation(() => Promise.resolve());
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it('renders poster-first and promotes the video after playback readiness', () => {
    const props = makeProps();
    const { container } = render(<WallVideoSurface {...props} />);

    const video = container.querySelector('video') as HTMLVideoElement;

    expect(container.querySelector('img')?.getAttribute('src')).toContain('video-1-poster.jpg');
    expect(props.onStarting).toHaveBeenCalled();

    Object.defineProperty(video, 'readyState', { configurable: true, value: 3 });
    fireEvent.loadedData(video);
    fireEvent.canPlay(video);
    fireEvent.playing(video);

    expect(props.onFirstFrame).toHaveBeenCalled();
    expect(props.onPlaybackReady).toHaveBeenCalled();
    expect(props.onPlaying).toHaveBeenCalled();
    expect(container.querySelector('img')).toBeNull();
  });

  it('pauses and resumes the underlying media imperatively when wall status changes', () => {
    const props = makeProps();
    const playSpy = vi.spyOn(HTMLMediaElement.prototype, 'play');
    const pauseSpy = vi.spyOn(HTMLMediaElement.prototype, 'pause');

    const { rerender } = render(<WallVideoSurface {...props} />);

    expect(playSpy).toHaveBeenCalledTimes(1);

    rerender(<WallVideoSurface {...props} playerStatus="paused" />);
    expect(pauseSpy).toHaveBeenCalled();

    rerender(<WallVideoSurface {...props} playerStatus="playing" />);
    expect(playSpy).toHaveBeenCalledTimes(2);
  });

  it('fails fast with poster_then_skip when startup never reaches readiness', () => {
    const props = makeProps();

    render(<WallVideoSurface {...props} />);

    vi.advanceTimersByTime(1200);

    expect(props.onFailure).toHaveBeenCalledWith(expect.objectContaining({
      itemId: 'video_1',
      exitReason: 'poster_then_skip',
    }));
  });

  it('turns waiting/stalled into a bounded operational failure', () => {
    const props = makeProps({ startupDeadlineMs: 3000, stallBudgetMs: 1500 });
    const { container } = render(<WallVideoSurface {...props} />);

    const video = container.querySelector('video') as HTMLVideoElement;

    Object.defineProperty(video, 'readyState', { configurable: true, value: 2 });
    fireEvent.waiting(video);
    vi.advanceTimersByTime(1500);

    expect(props.onWaiting).toHaveBeenCalled();
    expect(props.onFailure).toHaveBeenCalledWith(expect.objectContaining({
      itemId: 'video_1',
      exitReason: 'startup_waiting_timeout',
    }));
  });

  it('emits ended when playback finishes naturally', () => {
    const props = makeProps();
    const { container } = render(<WallVideoSurface {...props} />);

    const video = container.querySelector('video') as HTMLVideoElement;

    fireEvent.ended(video);

    expect(props.onEnded).toHaveBeenCalledWith(expect.objectContaining({
      itemId: 'video_1',
    }));
  });
});
