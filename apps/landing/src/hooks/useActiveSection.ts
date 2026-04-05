import { useEffect, useState } from "react";

function getClosestSection(ids: string[], offset: number) {
  let current = ids[0] || "";

  for (const id of ids) {
    const node = document.getElementById(id);
    if (!node) continue;

    const { top } = node.getBoundingClientRect();
    if (top - offset <= 0) {
      current = id;
    }
  }

  return current;
}

export function useActiveSection(ids: string[], offset = 140) {
  const [activeId, setActiveId] = useState(ids[0] || "");

  useEffect(() => {
    if (!ids.length) return undefined;

    const updateActiveSection = () => {
      setActiveId(getClosestSection(ids, offset));
    };

    updateActiveSection();
    window.addEventListener("scroll", updateActiveSection, { passive: true });
    window.addEventListener("resize", updateActiveSection);

    return () => {
      window.removeEventListener("scroll", updateActiveSection);
      window.removeEventListener("resize", updateActiveSection);
    };
  }, [ids.join("|"), offset]);

  return activeId;
}
