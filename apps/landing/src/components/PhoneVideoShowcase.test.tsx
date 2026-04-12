import { act, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import PhoneVideoShowcase from "./PhoneVideoShowcase";

vi.mock("motion/react", () => ({
  useReducedMotion: () => false,
}));

type ObserverInstance = {
  callback: IntersectionObserverCallback;
};

let observers: ObserverInstance[] = [];

class MockIntersectionObserver {
  callback: IntersectionObserverCallback;

  constructor(callback: IntersectionObserverCallback) {
    this.callback = callback;
    observers.push({ callback });
  }

  observe() {}

  disconnect() {}

  unobserve() {}

  takeRecords() {
    return [];
  }
}

function emitIntersection(isIntersecting: boolean) {
  const observer = observers[0];

  observer.callback(
    [
      {
        isIntersecting,
        intersectionRatio: isIntersecting ? 0.6 : 0,
        boundingClientRect: {} as DOMRectReadOnly,
        intersectionRect: {} as DOMRectReadOnly,
        rootBounds: null,
        target: document.body,
        time: 0,
      },
    ],
    {} as IntersectionObserver,
  );
}

describe("PhoneVideoShowcase", () => {
  beforeEach(() => {
    observers = [];

    vi.stubGlobal("IntersectionObserver", MockIntersectionObserver);

    Object.defineProperty(HTMLMediaElement.prototype, "play", {
      configurable: true,
      value: vi.fn().mockResolvedValue(undefined),
    });

    Object.defineProperty(HTMLMediaElement.prototype, "pause", {
      configurable: true,
      value: vi.fn(),
    });
  });

  it("shows the poster first and swaps to video when the section becomes visible", async () => {
    const { container } = render(
      <PhoneVideoShowcase
        poster="/poster.jpg"
        previewMp4Src="/preview.mp4"
        previewWebmSrc="/preview.webm"
        fullDemoMp4Src="/full.mp4"
        frameSrc="/frame.svg"
        title="Demo hero"
      />,
    );

    expect(screen.getByAltText("Demo hero")).toBeInTheDocument();
    expect(container.querySelector('video[aria-label="Demo hero"]')).toBeNull();

    await act(async () => {
      emitIntersection(true);
    });

    await waitFor(() => {
      expect(container.querySelector('video[aria-label="Demo hero"]')).toBeInTheDocument();
    });
  });

  it("opens and closes the complete demo modal", async () => {
    render(
      <PhoneVideoShowcase
        poster="/poster.jpg"
        previewMp4Src="/preview.mp4"
        fullDemoMp4Src="/full.mp4"
        frameSrc="/frame.svg"
      />,
    );

    fireEvent.click(screen.getByRole("button", { name: /ver demonstração completa em vídeo/i }));

    const dialog = screen.getByRole("dialog");
    expect(within(dialog).getByText(/demonstração completa/i)).toBeInTheDocument();

    fireEvent.keyDown(document, { key: "Escape" });

    await waitFor(() => {
      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    });
  });
});
