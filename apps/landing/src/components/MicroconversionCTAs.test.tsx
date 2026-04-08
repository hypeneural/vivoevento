import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { userEvent } from "@testing-library/user-event";
import MicroconversionCTAs from "./MicroconversionCTAs";

// Mock smooth scroll hook
vi.mock("@/hooks/useSmoothScroll", () => ({
  useSmoothScroll: () => ({
    scrollToId: vi.fn(),
  }),
}));

describe("MicroconversionCTAs", () => {
  it("renders all three microconversion CTAs", () => {
    render(<MicroconversionCTAs />);

    expect(screen.getByRole("button", { name: /ver exemplo de evento real/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /ver explicação rápida de 30 segundos/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /abrir demonstração visual interativa/i })).toBeInTheDocument();
  });

  it("renders with inline variant by default", () => {
    const { container } = render(<MicroconversionCTAs />);
    const wrapper = container.querySelector('[role="group"]');
    
    expect(wrapper?.className).toContain("inline");
  });

  it("renders with stacked variant when specified", () => {
    const { container } = render(<MicroconversionCTAs variant="stacked" />);
    const wrapper = container.querySelector('[role="group"]');
    
    expect(wrapper?.className).toContain("stacked");
  });

  it("calls onMicroconversion callback when view example is clicked", async () => {
    const user = userEvent.setup();
    const onMicroconversion = vi.fn();

    render(<MicroconversionCTAs onMicroconversion={onMicroconversion} />);

    const button = screen.getByRole("button", { name: /ver exemplo de evento real/i });
    await user.click(button);

    expect(onMicroconversion).toHaveBeenCalledWith("view-example");
  });

  it("calls onMicroconversion callback when watch demo is clicked", async () => {
    const user = userEvent.setup();
    const onMicroconversion = vi.fn();

    render(<MicroconversionCTAs onMicroconversion={onMicroconversion} />);

    const button = screen.getByRole("button", { name: /ver explicação rápida de 30 segundos/i });
    await user.click(button);

    expect(onMicroconversion).toHaveBeenCalledWith("watch-demo");
  });

  it("calls onMicroconversion callback when open visual demo is clicked", async () => {
    const user = userEvent.setup();
    const onMicroconversion = vi.fn();

    render(<MicroconversionCTAs onMicroconversion={onMicroconversion} />);

    const button = screen.getByRole("button", { name: /abrir demonstração visual interativa/i });
    await user.click(button);

    expect(onMicroconversion).toHaveBeenCalledWith("open-visual-demo");
  });

  it("applies custom className when provided", () => {
    const { container } = render(<MicroconversionCTAs className="custom-class" />);
    const wrapper = container.querySelector('[role="group"]');
    
    expect(wrapper?.className).toContain("custom-class");
  });

  it("has proper ARIA labels for accessibility", () => {
    render(<MicroconversionCTAs />);

    const group = screen.getByRole("group", { name: /ações de exploração sem compromisso/i });
    expect(group).toBeInTheDocument();

    const viewExampleButton = screen.getByRole("button", { name: /ver exemplo de evento real/i });
    expect(viewExampleButton).toBeInTheDocument();

    const watchDemoButton = screen.getByRole("button", { name: /ver explicação rápida de 30 segundos/i });
    expect(watchDemoButton).toBeInTheDocument();

    const openVisualDemoButton = screen.getByRole("button", { name: /abrir demonstração visual interativa/i });
    expect(openVisualDemoButton).toBeInTheDocument();
  });

  it("renders icons for each CTA", () => {
    const { container } = render(<MicroconversionCTAs />);
    const icons = container.querySelectorAll('svg[aria-hidden="true"]');
    
    // Should have 3 icons (one for each CTA)
    expect(icons).toHaveLength(3);
  });

  it("works without onMicroconversion callback", async () => {
    const user = userEvent.setup();

    render(<MicroconversionCTAs />);

    const button = screen.getByRole("button", { name: /ver exemplo de evento real/i });
    
    // Should not throw error when clicking without callback
    await expect(user.click(button)).resolves.not.toThrow();
  });
});
