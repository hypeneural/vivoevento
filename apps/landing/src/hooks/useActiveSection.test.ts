import { afterEach, describe, expect, it } from "vitest";
import { resolveActiveSection } from "./useActiveSection";

function mockSection(id: string, absoluteTop: number) {
  const element = document.createElement("section");
  element.id = id;
  element.getBoundingClientRect = () =>
    ({
      top: absoluteTop - window.scrollY,
      bottom: absoluteTop - window.scrollY + 320,
      left: 0,
      right: 0,
      width: 1280,
      height: 320,
      x: 0,
      y: absoluteTop - window.scrollY,
      toJSON: () => ({}),
    }) as DOMRect;

  document.body.appendChild(element);
  return element;
}

describe("resolveActiveSection", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("returns the section that crossed the activation threshold", () => {
    mockSection("como-funciona", 200);
    mockSection("recursos", 900);
    mockSection("para-quem-e", 1500);

    Object.defineProperty(window, "scrollY", {
      configurable: true,
      writable: true,
      value: 860,
    });
    Object.defineProperty(window, "innerHeight", {
      configurable: true,
      writable: true,
      value: 900,
    });
    Object.defineProperty(document.documentElement, "scrollHeight", {
      configurable: true,
      writable: true,
      value: 2600,
    });

    expect(resolveActiveSection(["como-funciona", "recursos", "para-quem-e"], 140)).toBe("recursos");
  });

  it("pins the last section when the viewport reaches the page end", () => {
    mockSection("depoimentos", 1800);
    mockSection("planos", 2200);
    mockSection("faq", 2600);

    Object.defineProperty(window, "scrollY", {
      configurable: true,
      writable: true,
      value: 2500,
    });
    Object.defineProperty(window, "innerHeight", {
      configurable: true,
      writable: true,
      value: 900,
    });
    Object.defineProperty(document.documentElement, "scrollHeight", {
      configurable: true,
      writable: true,
      value: 3400,
    });

    expect(resolveActiveSection(["depoimentos", "planos", "faq"], 140)).toBe("faq");
  });
});
