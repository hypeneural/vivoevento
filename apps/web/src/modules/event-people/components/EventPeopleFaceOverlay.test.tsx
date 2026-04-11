import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { EventPeopleFaceOverlay } from './EventPeopleFaceOverlay';

describe('EventPeopleFaceOverlay', () => {
  it('renders clickable face boxes and exposes the guided CTA for unknown faces', () => {
    const onSelectFace = vi.fn();

    render(
      <EventPeopleFaceOverlay
        mediaType="image"
        surfaceUrl="https://example.com/photo.jpg"
        alt="Foto teste"
        selectedFaceId={null}
        onSelectFace={onSelectFace}
        faces={[
          {
            id: 19,
            event_media_id: 321,
            face_index: 0,
            bbox: { x: 0.12, y: 0.18, w: 0.28, h: 0.35 },
            quality: { score: 0.94, tier: 'search_priority', rejection_reason: null },
            assignments: [],
            current_assignment: null,
            review_item: {
              id: 900,
              event_id: 42,
              queue_key: 'unknown-face:19',
              type: 'unknown_person',
              status: 'pending',
              priority: 120,
              event_person_id: null,
              event_media_face_id: 19,
              payload: { question: 'Quem e esta pessoa?' },
              last_signal_at: null,
              resolved_at: null,
            },
          },
        ]}
      />,
    );

    const faceButton = screen.getByRole('button', { name: /Quem e esta pessoa\?/i });
    fireEvent.click(faceButton);

    expect(onSelectFace).toHaveBeenCalledTimes(1);
    expect(screen.getByText(/Quem e esta pessoa\?/i)).toBeInTheDocument();
  });
});
