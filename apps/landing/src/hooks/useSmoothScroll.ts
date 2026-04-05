import { createContext, useContext } from "react";

export type SmoothScrollApi = {
  scrollToId: (id: string) => void;
};

export const SmoothScrollContext = createContext<SmoothScrollApi | null>(null);

export function useSmoothScroll() {
  const context = useContext(SmoothScrollContext);

  if (!context) {
    throw new Error("useSmoothScroll must be used within SmoothScroller");
  }

  return context;
}
