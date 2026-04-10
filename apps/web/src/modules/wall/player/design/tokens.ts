/**
 * Wall Player — Design Tokens
 *
 * Centralized TailwindCSS class tokens for the wall player.
 * Keeps visual consistency across all layouts and overlays.
 */

// ─── Safe Areas (TV overscan protection) ───────────────────
export const WALL_SAFE_AREA_X = 'px-[max(16px,2vw)]';
export const WALL_SAFE_AREA_Y = 'py-[max(16px,2vh)]';
export const WALL_SAFE_AREA = `${WALL_SAFE_AREA_X} ${WALL_SAFE_AREA_Y}`;
export const WALL_SAFE_LEFT = 'left-[max(16px,2vw)]';
export const WALL_SAFE_RIGHT = 'right-[max(16px,2vw)]';
export const WALL_SAFE_TOP = 'top-[max(16px,2vh)]';
export const WALL_SAFE_BOTTOM = 'bottom-[max(16px,2vh)]';

// ─── Glassmorphism Panels ──────────────────────────────────
export const WALL_PANEL = 'rounded-[32px] border border-white/10 bg-black/30 shadow-[0_30px_120px_rgba(0,0,0,0.45)] backdrop-blur-xl';
export const WALL_CARD = 'rounded-[28px] border border-white/10 bg-black/35 shadow-[0_22px_80px_rgba(0,0,0,0.38)] backdrop-blur-xl';
export const WALL_CAPTION_PANEL = 'rounded-[28px] border border-white/10 bg-black/35 shadow-[0_18px_60px_rgba(0,0,0,0.35)] backdrop-blur-xl';

// ─── Overlays & Gradients ──────────────────────────────────
export const WALL_OVERLAY_GRADIENT = 'bg-[linear-gradient(180deg,_rgba(9,9,11,0.18)_0%,_rgba(9,9,11,0.72)_100%)]';
export const WALL_READING_GRADIENT = 'bg-[linear-gradient(180deg,_rgba(9,9,11,0)_0%,_rgba(9,9,11,0.18)_36%,_rgba(9,9,11,0.82)_100%)]';

// ─── Typography ────────────────────────────────────────────
export const WALL_TEXT_SHADOW = '[text-shadow:0_2px_18px_rgba(0,0,0,0.42)]';
export const WALL_TEXT_PRIMARY = `text-[clamp(1.7rem,3vw,3.2rem)] font-semibold leading-[1.06] text-white ${WALL_TEXT_SHADOW}`;
export const WALL_TEXT_SECONDARY = 'text-[clamp(0.82rem,1vw,1rem)] uppercase tracking-[0.34em] text-white/68';

// ─── Decorative Elements ───────────────────────────────────
export const WALL_ACCENT_BAR = 'h-1.5 w-16 rounded-full bg-orange-400 shadow-[0_0_24px_rgba(249,115,22,0.38)]';
export const WALL_BADGE = 'rounded-full bg-orange-500 px-4 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-white shadow-lg';
export const WALL_LOGO_DOCK = 'rounded-2xl border border-white/10 bg-black/40 p-3 backdrop-blur-md shadow-[0_16px_50px_rgba(0,0,0,0.22)]';
export const WALL_NEON_PANEL = 'rounded-full border border-white/15 bg-black/35 px-5 py-2 shadow-[0_0_40px_rgba(249,115,22,0.25)] backdrop-blur-md';

// Motion foundation
export const WALL_MOTION_VISUAL_DURATION_SINGLE = 0.42;
export const WALL_MOTION_VISUAL_DURATION_BOARD = 0.38;
export const WALL_MOTION_VISUAL_DURATION_PUZZLE = 0.46;
export const WALL_MOTION_BURST_DURATION = 0.24;
export const WALL_MOTION_PUZZLE_BURST_DURATION = 0.28;
export const WALL_MOTION_DRIFT_DURATION_SECONDS = 18;
export const WALL_MOTION_PUZZLE_DRIFT_DURATION_SECONDS = 22;
