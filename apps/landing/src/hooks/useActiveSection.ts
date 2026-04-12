import { useEffect, useState } from "react";

export function resolveActiveSection(ids: string[], offset: number) {
  if (!ids.length) {
    return "";
  }

  const availableIds = ids.filter((id) => document.getElementById(id));
  if (!availableIds.length) {
    return ids[0] || "";
  }

  const viewportBottom = window.scrollY + window.innerHeight;
  const documentHeight = document.documentElement.scrollHeight;

  // When the user reaches the end of the page, pin the last visible nav item.
  if (viewportBottom >= documentHeight - 2) {
    return availableIds[availableIds.length - 1];
  }

  let current = availableIds[0];
  const threshold = window.scrollY + offset;

  for (const id of availableIds) {
    const node = document.getElementById(id);
    if (!node) continue;

    const top = node.getBoundingClientRect().top + window.scrollY;
    if (threshold >= top) {
      current = id;
      continue;
    }

    break;
  }

  return current;
}

export function useActiveSection(ids: string[], offset = 140) {
  const [activeId, setActiveId] = useState(ids[0] || "");

  useEffect(() => {
    if (!ids.length) return undefined;

    const updateActiveSection = () => {
      setActiveId(resolveActiveSection(ids, offset));
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
